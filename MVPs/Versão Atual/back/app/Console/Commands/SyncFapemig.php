<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RoachPHP\Roach;
use App\Spiders\FapemigSpider;
use App\Models\Edital;

class SyncFapemig extends Command
{
    /**
     * O nome e a assinatura do comando de console.
     *
     * @var string
     */
    protected $signature = 'fapemig:sync';

    /**
     * A descrição do comando de console.
     *
     * @var string
     */
    protected $description = 'Sincroniza os editais e chamadas públicas da FAPEMIG (Minas Gerais)';

    /**
     * Executa o comando de console.
     */
    public function handle()
    {
        $this->info("==================================================");
        $this->info(" 🚀 INICIANDO SINCRONIZAÇÃO FAPEMIG (MINAS GERAIS) ");
        $this->info("==================================================");

        $totalAntes = Edital::where('fonte', 'FAPEMIG')->count();

        try {
            Roach::startSpider(FapemigSpider::class);
            $totalDepois = Edital::where('fonte', 'FAPEMIG')->count();
            $novos = max(0, $totalDepois - $totalAntes);

            $this->info("✔ Sincronização da FAPEMIG concluída com sucesso!");
            $this->info(" Total de Editais FAPEMIG no Banco: {$totalDepois} (Novos nesta rodada: {$novos})");
        } catch (\Throwable $e) {
            $this->error("✖ Erro ao sincronizar FAPEMIG: " . $e->getMessage());
            return Command::FAILURE;
        }

        $this->info("==================================================");

        return Command::SUCCESS;
    }
}
