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
        $titulo = $this->edital->title ?? '';
        // Se o Deep Scrape funcionou, usamos o texto gigante. Se não, usamos o resumo.
        $objetivo = $this->edital->conteudo_completo ?? $this->edital->objetivo ?? '';
        $publico = $this->edital->publico ?? '';

        Log::info("Analisando edital {$this->edital->id} com IA...");

        $resultado = $deepSeekService->analyzeEdital($titulo, $objetivo, $publico);

        if ($resultado) {
            $this->edital->update([
                'ai_analyzed' => true,
                'ai_match' => $resultado['match'] ?? 50,
                'ai_trl' => $resultado['trl'] ?? 'A definir',
                'ai_nicho' => $resultado['nicho'] ?? 'Inovação',
                'ai_faturamento' => $resultado['faturamento'] ?? 'Não especificado',
                'ai_diagnosis' => $resultado['diagnostico'] ?? []
            ]);
            Log::info("Edital {$this->edital->id} analisado com sucesso.");
        } else {
            Log::error("Falha ao analisar edital {$this->edital->id}.");
            $this->release(60); // Tenta de novo daqui a 60 segundos se falhar
        }
    }
}
