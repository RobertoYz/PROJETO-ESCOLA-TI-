<?php

use App\Models\EmailVerificacaoCadastro;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('assinaturas:processar-downgrades')->dailyAt('02:55');
Schedule::command('assinaturas:processar-ciclo')->dailyAt('03:00');

Artisan::command('auth:limpar-verificacoes-email', function () {
    $limite = now()->subDay();
    $removidos = EmailVerificacaoCadastro::whereRaw(
        'COALESCE(consumido_em, token_expira_em, codigo_expira_em, ultimo_envio_em, created_at) < ?',
        [$limite]
    )->delete();

    $this->info("Verificações removidas: {$removidos}");
})->purpose('Remove verificações de cadastro vencidas ou consumidas há mais de 24 horas');

Schedule::command('auth:limpar-verificacoes-email')->dailyAt('03:10');

Schedule::command('termos:expurgar-aceites-vencidos')->dailyAt('03:25');

// Sincronização escalonada dos radares de editais na madrugada (evita pico de memória/CPU)
$radaresEscalonados = [
    'FINEP'   => '03:30',
    'FAPESP'  => '03:45',
    'FAPESC'  => '04:00',
    'FAPEMIG' => '04:15',
    'FAPERJ'  => '04:30',
    'FAPERGS' => '04:45',
    'FAPEMAT' => '05:00',
    'FAPEG'   => '05:15',
    'FAPDF'   => '05:30',
];

foreach ($radaresEscalonados as $fonte => $horario) {
    Schedule::command("editais:sync --fonte={$fonte}")
        ->dailyAt($horario)
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/cron_editais.log'));
}

Schedule::call(fn () => app(\App\Services\ArmazenamentoKanbanService::class)->limparPendencias())
    ->name('kanban:limpar-anexos')->hourly()->withoutOverlapping();
