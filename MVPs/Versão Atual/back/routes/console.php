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
