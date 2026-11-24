<?php

namespace App\Console\Commands;

use App\Models\Agencia;
use App\Services\TrocaPlanoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessarDowngradesAgendadosCommand extends Command
{
    protected $signature = 'assinaturas:processar-downgrades';
    protected $description = 'Revalida as cotas e aplica os downgrades no fim do ciclo';

    public function handle(TrocaPlanoService $trocas): int
    {
        $agencias = Agencia::whereNotNull('plano_agendado_id')->where('assinatura_expira_em', '<=', now());
        foreach ($agencias->select('id')->lazyById() as $registro) {
            DB::transaction(function () use ($registro, $trocas) {
                $agencia = Agencia::lockForUpdate()->find($registro->id);
                if ($agencia) {
                    $trocas->aplicarDowngradeVencido($agencia);
                }
            });
        }

        return self::SUCCESS;
    }
}
