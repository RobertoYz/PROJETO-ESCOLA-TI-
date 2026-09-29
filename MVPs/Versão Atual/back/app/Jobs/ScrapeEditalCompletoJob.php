<?php

namespace App\Jobs;

use App\Models\Edital;
use App\Services\PdfExtractorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScrapeEditalCompletoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $edital;

    /**
     * Create a new job instance.
     */
    public function __construct(Edital $edital)
    {
        $this->edital = $edital;
    }

    /**
     * Execute the job (Custo Zero de IA - Extrator Determinístico de HTML e PDF).
     */
    public function handle(PdfExtractorService $pdfService): void
    {
        if (!$this->edital->source_url) {
            return;
        }

        try {
            $url = $this->edital->source_url;

            // 1. Se o edital for um arquivo PDF direto ou contiver um PDF anexado
            if (str_contains(strtolower($url), '.pdf')) {
                Log::info("Processando PDF do Edital ID {$this->edital->id}: {$url}");
                $textoPdf = $pdfService->extrairTexto($url);
                $dados = $pdfService->extrairDadosEstruturados($textoPdf);

                $updateData = [
                    'conteudo_completo' => mb_substr($textoPdf, 0, 10000),
                ];

                if (!empty($dados['deadline'])) {
                    $updateData['deadline'] = $dados['deadline'];
                }
                if (!empty($dados['max_budget'])) {
                    $updateData['max_budget'] = $dados['max_budget'];
                }
                if (!empty($dados['faturamento'])) {
                    $updateData['ai_faturamento'] = $dados['faturamento'];
                }

                $this->edital->update($updateData);
                Log::info("PDF do Edital ID {$this->edital->id} processado com sucesso (Custo Zero de IA).");
                return;
            }

            // 2. Se for uma página HTML tradicional
            $response = Http::timeout(30)->get($url);

            if ($response->successful()) {
                $html = $response->body();
                $textoPuro = strip_tags($html);
                $textoLimpo = preg_replace('/\s+/', ' ', $textoPuro);

                // Procura links de PDF dentro da página HTML
                if (preg_match('/href=["\']([^"\']+\.pdf)["\']/i', $html, $pdfMatches)) {
                    $pdfUrl = $pdfMatches[1];
                    if (!str_starts_with($pdfUrl, 'http')) {
                        $parsedUrl = parse_url($url);
                        $baseUrl = ($parsedUrl['scheme'] ?? 'https') . '://' . ($parsedUrl['host'] ?? '');
                        $pdfUrl = rtrim($baseUrl, '/') . '/' . ltrim($pdfUrl, '/');
                    }

                    Log::info("Link de PDF encontrado na página do Edital ID {$this->edital->id}: {$pdfUrl}");
                    $textoPdf = $pdfService->extrairTexto($pdfUrl);
                    $dadosPdf = $pdfService->extrairDadosEstruturados($textoPdf);

                    $textoLimpo = !empty($textoPdf) ? $textoPdf : $textoLimpo;

                    if (!empty($dadosPdf['deadline'])) {
                        $this->edital->deadline = $dadosPdf['deadline'];
                    }
                    if (!empty($dadosPdf['max_budget'])) {
                        $this->edital->max_budget = $dadosPdf['max_budget'];
                    }
                }

                $this->edital->update([
                    'conteudo_completo' => mb_substr($textoLimpo, 0, 10000)
                ]);

                Log::info("Página do Edital ID {$this->edital->id} raspada e processada com sucesso.");
            }
        } catch (\Throwable $e) {
            Log::error("Erro no processamento do Edital ID {$this->edital->id}: " . $e->getMessage());
        }
    }
}
