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

            // Extração Universal Custo Zero (Sem IA)
            if (!empty($this->edital->conteudo_completo)) {
                $this->extrairDadosCriticosSemIA();
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

    /**
     * Extrai dados críticos (Orçamento, Data, Faturamento) via Regex antes da IA.
     * Tática de Custo Zero para garantir dados básicos caso a IA falhe.
     */
    private function extrairDadosCriticosSemIA(): void
    {
        // Limpa espaços em branco inquebráveis
        $texto = str_replace(["\xA0", "\xC2\xA0", "&nbsp;"], ' ', $this->edital->conteudo_completo);
        $atualizou = false;

        // 1. Extração de Data Limite (Deadline)
        if (empty($this->edital->deadline)) {
            $dataEncontrada = null;

            // Formato dd/mm/aaaa com contexto
            if (preg_match_all('/(?:limite|prazo|encerramento|submissão|término|até)[^\d]{0,80}([0-9]{2}[\/\.][0-9]{2}[\/\.][0-9]{2,4})/iu', $texto, $matches)) {
                $dataStr = str_replace('.', '/', end($matches[1]));
                try {
                    $formato = strlen($dataStr) == 8 ? 'd/m/y' : 'd/m/Y';
                    $dataEncontrada = \Carbon\Carbon::createFromFormat($formato, $dataStr);
                } catch (\Exception $e) {}
            } 
            // Formato por extenso com contexto
            else if (preg_match_all('/(?:limite|prazo|encerramento|submissão|término|até)[^\d]{0,80}([0-9]{1,2})\s+de\s+([a-zçA-Z]+)\s+de\s+([0-9]{4})/iu', $texto, $matches)) {
                $meses = [
                    'janeiro' => 1, 'fevereiro' => 2, 'março' => 3, 'abril' => 4,
                    'maio' => 5, 'junho' => 6, 'julho' => 7, 'agosto' => 8,
                    'setembro' => 9, 'outubro' => 10, 'novembro' => 11, 'dezembro' => 12
                ];
                $mesNome = end($matches[2]);
                $mes = $meses[strtolower($mesNome)] ?? null;
                if ($mes) {
                    try {
                        $dia = end($matches[1]);
                        $ano = end($matches[3]);
                        $dataEncontrada = \Carbon\Carbon::createFromDate($ano, $mes, $dia);
                    } catch (\Exception $e) {}
                }
            }

            if ($dataEncontrada && $dataEncontrada->year >= 2024 && $dataEncontrada->year <= 2030) {
                $this->edital->deadline = $dataEncontrada->format('Y-m-d');
                $atualizou = true;
            }
        }

        // 2. Extração de Orçamento Global (Max Budget)
        if (empty($this->edital->max_budget)) {
            if (preg_match_all('/(?:global|total|investimento|aporte|recursos|fomento|valor máximo|é de)[^\d]{0,40}R\$\s*([0-9.,]+)\s*(milh[õo]es|mil)?/iu', $texto, $matches)) {
                $maiorValor = 0;
                foreach ($matches[1] as $index => $valorBruto) {
                    $valorStr = str_replace(['.', ','], ['', '.'], trim($valorBruto, '.'));
                    if (is_numeric($valorStr)) {
                        $valor = (float) $valorStr;
                        $multiplicador = strtolower($matches[2][$index] ?? '');
                        if (str_starts_with($multiplicador, 'milh')) {
                            $valor *= 1000000;
                        } else if ($multiplicador === 'mil') {
                            $valor *= 1000;
                        }
                        if ($valor > $maiorValor) {
                            $maiorValor = $valor;
                        }
                    }
                }
                if ($maiorValor > 0) {
                    $this->edital->max_budget = $maiorValor;
                    $atualizou = true;
                }
            }
        }

        // 3. Extração de Faturamento (ai_faturamento fallback)
        if (empty($this->edital->ai_faturamento) || $this->edital->ai_faturamento === 'Não especificado') {
            if (preg_match('/(?:receita operacional bruta|faturamento bruto|faturamento anual|faturamento de até)[^\d]{0,40}R\$\s*([0-9.,]+)\s*(milh[õo]es|mil)?/iu', $texto, $matches)) {
                $multiplicador = strtolower($matches[2] ?? '');
                $sufixo = $multiplicador ? ' ' . $matches[2] : '';
                $this->edital->ai_faturamento = 'Até R$ ' . trim($matches[1]) . $sufixo;
                $atualizou = true;
            } else if (preg_match('/(?:startups|micro e pequenas empresas)/iu', $texto)) {
                $this->edital->ai_faturamento = 'ME/EPP (Até R$ 4,8 Milhões)';
                $atualizou = true;
            }
        }

        if ($atualizou) {
            $this->edital->save();
            Log::info("Dados críticos extraídos via Regex (Custo Zero) para o edital {$this->edital->id}.");
        }
    }
}
