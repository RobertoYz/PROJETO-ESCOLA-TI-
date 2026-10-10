<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Edital;
use App\Services\DeepSeekService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Str;

class AnalyzeEditalWithIA implements ShouldQueue
{
    use Queueable, Dispatchable, InteractsWithQueue, SerializesModels;

    public $edital;

    /**
     * Create a new job instance.
     */
    public function __construct(Edital $edital)
    {
        $this->edital = $edital;
    }

    /**
     * Execute the job.
     */
    public function handle(DeepSeekService $deepSeekService): void
    {
        Log::info("Iniciando processamento do edital {$this->edital->id}...");

        try {
            // Se for FAPESC, tentar extrair dados triviais do HTML e o PDF
            if ($this->edital->fonte === 'FAPESC') {
                if (empty($this->edital->max_budget) || empty($this->edital->deadline) || empty($this->edital->publico)) {
                    $this->extrairDadosHtmlFapesc();
                }

                if (empty($this->edital->conteudo_completo)) {
                    $this->extrairConteudoPdfFapesc();
                }
            }

            $titulo = $this->edital->title ?? '';
            $objetivo = $this->edital->conteudo_completo ?? $this->edital->objetivo ?? '';
            $publico = $this->edital->publico ?? '';

            Log::info("Enviando edital {$this->edital->id} para a IA (DeepSeek)...");

            $resultado = $deepSeekService->analyzeEdital($titulo, $objetivo, $publico);

            if ($resultado) {
                $this->edital->update([
                    'ai_analyzed' => true,
                    'ai_match' => $resultado['match'] ?? 50,
                    'ai_trl' => $resultado['trl'] ?? 'A definir',
                    'ai_nicho' => $resultado['nicho'] ?? 'Inovação',
                    'ai_faturamento' => $resultado['faturamento'] ?? 'Não especificado',
                    'ai_diagnosis' => $resultado['diagnostico'] ?? [],
                    'max_budget' => $this->edital->max_budget ?? (isset($resultado['max_budget']) && $resultado['max_budget'] !== 'null' ? (float)str_replace(['.', ','], ['', '.'], preg_replace('/[^\d.,]/', '', $resultado['max_budget'])) : null),
                    'deadline' => $this->edital->deadline ?? (isset($resultado['deadline']) && $resultado['deadline'] !== 'null' && $resultado['deadline'] !== 'A definir' ? date('Y-m-d', strtotime(str_replace('/', '-', $resultado['deadline']))) : null),
                    'publico' => $this->edital->publico ?: ($resultado['publico'] ?? '')
                ]);
                Log::info("Edital {$this->edital->id} analisado com sucesso pela IA.");
            } else {
                Log::error("A IA retornou vazio para o edital {$this->edital->id}.");
                $this->release(60);
            }

        } catch (\Throwable $e) {
            Log::error("Erro fatal no Job do edital {$this->edital->id}: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            $this->release(120);
        }
    }

    /**
     * Extrai as seções chave do PDF da FAPESC
     */
    private function extrairConteudoPdfFapesc(): void
    {
        $url = $this->edital->source_url;
        if (!$url) return;

        Log::info("FAPESC: Acessando página do edital: {$url}");
        
        $html = @file_get_contents($url);
        if (!$html) return;

        // Procura link do PDF (termina em .pdf ou possui 'wp-content/uploads')
        preg_match('/href=["\']([^"\']+\.pdf)["\']/i', $html, $matches);
        
        if (empty($matches[1])) {
            Log::warning("FAPESC: Nenhum link de PDF encontrado na página do edital {$this->edital->id}");
            return;
        }

        $pdfUrl = $matches[1];
        Log::info("FAPESC: Baixando PDF: {$pdfUrl}");

        $pdfContent = @file_get_contents($pdfUrl);
        if (!$pdfContent) return;

        $tempFile = storage_path('app/temp_edital_' . $this->edital->id . '.pdf');
        file_put_contents($tempFile, $pdfContent);

        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf    = $parser->parseFile($tempFile);
            $texto  = $pdf->getText();
            
            // Tratamento de quebras de linha para facilitar a regex
            $textoLimpo = preg_replace('/\r\n|\r|\n/', ' ', $texto);
            $textoLimpo = preg_replace('/\s+/', ' ', $textoLimpo);

            // Mapeamento das seções pedidas
            $secoes = [
                'OBJETIVOS' => '/DOS OBJETIVOS(.*?)(\d+\.\s*D|DOS CRITÉRIOS|DO CRONOGRAMA)/ui',
                'CRITÉRIOS DE ADMISSIBILIDADE' => '/DOS CRITÉRIOS DE ADMISSIBILIDADE(.*?)(\d+\.\s*D|DO CRONOGRAMA|DOS RECURSOS)/ui',
                'CRONOGRAMA' => '/DO CRONOGRAMA(.*?)(\d+\.\s*D|DOS RECURSOS|DA ANÁLISE)/ui',
                'RECURSOS FINANCEIROS' => '/DOS RECURSOS FINANCEIROS(.*?)(\d+\.\s*D|DA ANÁLISE|DA PUBLICAÇÃO)/ui',
                'ANÁLISE E JULGAMENTO' => '/DA ANÁLISE E JULGAMENTO(.*?)(\d+\.\s*D|DA PUBLICAÇÃO|DOS RECURSOS)/ui',
                'PUBLICAÇÃO DOS RESULTADOS' => '/DA PUBLICAÇÃO DOS RESULTADOS(.*?)(\d+\.\s*D|DOS RECURSOS|DA PRESTAÇÃO)/ui',
            ];

            $conteudoExtraido = "RESUMO EXTRAÍDO DO PDF OFICIAL:\n\n";
            
            foreach ($secoes as $titulo => $regex) {
                if (preg_match($regex, $textoLimpo, $matchesExtraidos)) {
                    $conteudoExtraido .= "--- {$titulo} ---\n";
                    $conteudoExtraido .= trim($matchesExtraidos[1]) . "\n\n";
                }
            }

            // Se não encontrou nenhuma seção usando os padrões, salva o texto inteiro (limitado)
            if (strlen($conteudoExtraido) < 100) {
                $conteudoExtraido = substr($textoLimpo, 0, 15000); // Evita estourar tokens da IA
            }

            $this->edital->conteudo_completo = $conteudoExtraido;
            $this->edital->save();
            
            Log::info("FAPESC: PDF processado com sucesso para o edital {$this->edital->id}");

        } catch (\Exception $e) {
            Log::error("Erro ao fazer parse do PDF: " . $e->getMessage());
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Extrai dados básicos do HTML da FAPESC gratuitamente usando Crawler/Regex
     */
    private function extrairDadosHtmlFapesc(): void
    {
        $url = $this->edital->source_url;
        if (!$url) return;

        Log::info("FAPESC: Minerando página HTML (Custo Zero): {$url}");
        
        $html = @file_get_contents($url);
        if (!$html) return;

        try {
            $crawler = new Crawler($html);
            $valorTotal = null;
            $prazoSubmissao = null;
            $elegibilidade = '';

            $crawler->filter('.elementor-text-editor p, .elementor-text-editor li, p, li, tr')->each(function (Crawler $node) use (&$valorTotal, &$prazoSubmissao, &$elegibilidade) {
                $texto = trim($node->text());
                $textoMinusculo = strtolower($texto);

                // 1. Público-alvo / Elegibilidade
                if (
                    preg_match('/(?:público-alvo|proponentes|elegibilidade|quem pode participar|destinado a)[^\w]*(.*)/i', $texto) ||
                    str_contains($textoMinusculo, 'empresas de micro') ||
                    str_contains($textoMinusculo, 'startups catarinenses')
                ) {
                    if (strlen($texto) > 10 && strlen($texto) < 300 && !str_contains($elegibilidade, Str::limit($texto, 20))) {
                        $elegibilidade .= "• " . $texto . "\n";
                    }
                }

                // 2. Orçamento Global
                if (preg_match('/(?:global|total|investimento|aporte|recursos|fomento)[^\d]{0,40}R\$\s*([0-9.,]+)/i', $texto, $matches)) {
                    if (!$valorTotal && strlen($matches[1]) > 5) {
                        $valorStr = str_replace(['.', ','], ['', '.'], trim($matches[1], '.'));
                        if (is_numeric($valorStr)) {
                            $valorTotal = (float) $valorStr;
                        }
                    }
                }

                // 3. Prazo de Submissão
                // Pega a última data na frase (ex: "10/09/2026 a 20/10/2026", pega a última)
                if (preg_match('/(?:submissão|inscriç|prazo)[^\d]{0,40}([0-9]{2}\/[0-9]{2}\/[0-9]{4})(?:.*a.*([0-9]{2}\/[0-9]{2}\/[0-9]{4}))?/i', $texto, $matches)) {
                    if (!$prazoSubmissao) {
                        try {
                            $dataStr = !empty($matches[2]) ? $matches[2] : $matches[1];
                            $prazoSubmissao = \Carbon\Carbon::createFromFormat('d/m/Y', $dataStr)->format('Y-m-d');
                        } catch (\Exception $e) {}
                    }
                }
            });

            if ($valorTotal || $prazoSubmissao || $elegibilidade) {
                $this->edital->max_budget = $valorTotal ?? $this->edital->max_budget;
                $this->edital->deadline = $prazoSubmissao ?? $this->edital->deadline;
                $this->edital->publico = !empty($elegibilidade) ? trim($elegibilidade) : $this->edital->publico;
                $this->edital->save();
                Log::info("FAPESC: Dados triviais (Orçamento/Prazo/Público) salvos com sucesso.");
            }
        } catch (\Exception $e) {
            Log::warning("FAPESC: Erro ao tentar extrair dados do HTML: " . $e->getMessage());
        }
    }
}
