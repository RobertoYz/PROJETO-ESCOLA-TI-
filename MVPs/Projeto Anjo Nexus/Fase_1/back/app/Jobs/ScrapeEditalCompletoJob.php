<?php

namespace App\Jobs;

use App\Models\Edital;
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
     * Execute the job.
     */
    public function handle(): void
    {
        // Se não tiver URL, não tem como baixar o texto
        if (!$this->edital->source_url) {
            Log::warning("Edital ID {$this->edital->id} não tem source_url. Indo direto para a IA.");
            AnalyzeEditalWithIA::dispatch($this->edital);
            return;
        }

        try {
            // Passo 1: Acessar a página oficial do edital (Deep Scrape)
            $response = Http::get($this->edital->source_url);

            if ($response->successful()) {
                // Passo 2: Extrair apenas o texto puro, removendo HTML
                // Usamos strip_tags para remover a formatação e preg_replace para limpar espaços duplos
                $html = $response->body();
                $textoPuro = strip_tags($html);
                $textoLimpo = preg_replace('/\s+/', ' ', $textoPuro);

                // Passo 3: Salvar no banco
                $this->edital->update([
                    'conteudo_completo' => $textoLimpo
                ]);
                Log::info("Edital ID {$this->edital->id} raspado com sucesso.");
            } else {
                Log::error("Falha ao raspar Edital ID {$this->edital->id}. Status: " . $response->status());
            }
        } catch (\Exception $e) {
            Log::error("Erro no Deep Scrape do Edital ID {$this->edital->id}: " . $e->getMessage());
        }

        // Passo 4: Chamar a IA (agora ela terá o texto completo se tudo deu certo)
        AnalyzeEditalWithIA::dispatch($this->edital);
    }
}
