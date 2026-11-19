<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RoachPHP\Roach;
use App\Spiders\FapergsSpider;
use App\Models\Edital;

class SyncFapergs extends Command
{
    /**
     * O nome e a assinatura do comando de console.
     *
     * @var string
     */
    protected $signature = 'fapergs:sync';

    /**
     * A descrição do comando de console.
     *
     * @var string
     */
    protected $description = 'Sincroniza os editais e chamadas públicas da FAPERGS (Rio Grande do Sul)';

    /**
     * Executa o comando de console.
     */
    public function handle()
    {
        $this->info("==================================================");
        $this->info(" 🚀 INICIANDO SINCRONIZAÇÃO FAPERGS (RIO GRANDE DO SUL) ");
        $this->info("==================================================");

        $totalAntes = Edital::where('fonte', 'FAPERGS')->count();

        try {
            Roach::startSpider(FapergsSpider::class);
            $totalDepois = Edital::where('fonte', 'FAPERGS')->count();
            $novos = max(0, $totalDepois - $totalAntes);

            $this->info("✔ Sincronização da FAPERGS concluída com sucesso!");
            $this->info(" Total de Editais FAPERGS no Banco: {$totalDepois} (Novos nesta rodada: {$novos})");
        } catch (\Throwable $e) {
            $this->error("✖ Erro ao sincronizar FAPERGS: " . $e->getMessage());
            return Command::FAILURE;
        }

        $this->info("==================================================");
        return Command::SUCCESS;
    }
}
