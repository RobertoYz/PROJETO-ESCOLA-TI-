<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncFapesc extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'spider:fapesc';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza os editais da FAPESC';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Iniciando spider FAPESC...");
        \RoachPHP\Roach::startSpider(\App\Spiders\FapeScSpider::class);
        $this->info("Spider FAPESC finalizado com sucesso!");
    }
}
