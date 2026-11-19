<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RoachPHP\Roach;
use App\Spiders\FaperjSpider;
use App\Models\Edital;

class SyncFaperj extends Command
{
    /**
     * O nome e a assinatura do comando de console.
     *
     * @var string
     */
    protected $signature = 'faperj:sync';

    /**
     * A descrição do comando de console.
     *
     * @var string
     */
    protected $description = 'Sincroniza os editais e chamadas públicas da FAPERJ (Rio de Janeiro)';

    /**
     * Executa o comando de console.
     */
    public function handle()
    {
        $this->info("==================================================");
        $this->info(" 🚀 INICIANDO SINCRONIZAÇÃO FAPERJ (RIO DE JANEIRO) ");
        $this->info("==================================================");

        $totalAntes = Edital::where('fonte', 'FAPERJ')->count();

        try {
            Roach::startSpider(FaperjSpider::class);
            $totalDepois = Edital::where('fonte', 'FAPERJ')->count();
            $novos = max(0, $totalDepois - $totalAntes);

            $this->info("✔ Sincronização da FAPERJ concluída com sucesso!");
            $this->info(" Total de Editais FAPERJ no Banco: {$totalDepois} (Novos nesta rodada: {$novos})");
        } catch (\Throwable $e) {
            $this->error("✖ Erro ao sincronizar FAPERJ: " . $e->getMessage());
            return Command::FAILURE;
        }

        $this->info("==================================================");
        return Command::SUCCESS;
    }
}
