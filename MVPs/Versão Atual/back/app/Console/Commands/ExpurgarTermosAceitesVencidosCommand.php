<?php

namespace App\Console\Commands;

use App\Models\TermoAceite;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExpurgarTermosAceitesVencidosCommand extends Command
{
    protected $signature = 'termos:expurgar-aceites-vencidos {--dry-run}';
    protected $description = 'Descarta evidências vencidas sem preservação excepcional';

    public function handle(): int
    {
        $elegiveis = TermoAceite::whereNotNull('retencao_ate')->where('retencao_ate', '<=', now('UTC'))
            ->where('preservacao_excepcional', false);
        $total = 0;
        $simular = $this->option('dry-run');
        try {
            (clone $elegiveis)->select('id')->chunkById(500, function ($lote) use ($elegiveis, $simular, &$total) {
                $total += $simular ? $lote->count() : (clone $elegiveis)->whereKey($lote->modelKeys())->delete();
            });
        } catch (Throwable) {
            Log::error('Falha no expurgo de aceites.', ['processados' => $total]);
            $this->error('Falha no processamento do lote.');
            return self::FAILURE;
        }
        $mensagem = $simular ? "Aceites elegíveis: {$total}" : "Aceites removidos: {$total}";
        Log::info($mensagem);
        $this->info($mensagem);
        return self::SUCCESS;
    }
}
