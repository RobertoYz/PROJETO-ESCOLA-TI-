<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('assinaturas:processar-downgrades')->dailyAt('02:55');
Schedule::command('assinaturas:processar-ciclo')->dailyAt('03:00');
