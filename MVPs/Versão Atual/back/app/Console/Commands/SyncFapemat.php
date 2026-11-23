<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RoachPHP\Roach;
use App\Spiders\FapematSpider;
use App\Models\Edital;

class SyncFapemat extends Command
{
    /**
     * O nome e a assinatura do comando no terminal.
     *
     * @var string
     */
    protected $signature = 'fapemat:sync';

    /**
     * A descrição do comando.
     *
     * @var string
     */
    protected $description = 'Executa o Spider da FAPEMAT (Mato Grosso) e atualiza o banco de dados com os editais abertos';

    /**
     * Executa o comando.
     */
    public function handle()
    {
        $this->info("==================================================");
        $this->info(" 🌿 INICIANDO SPIDER FAPEMAT (MATO GROSSO) ");
        $this->info("==================================================");

        $totalAntes = Edital::where('fonte', 'FAPEMAT')->count();
        $this->line("Editais da FAPEMAT atualmente no banco: {$totalAntes}");

        try {
            $this->warn("Acessando portal da FAPEMAT e extraindo chamadas públicas...");
            Roach::startSpider(FapematSpider::class);
            $this->info("✔ Spider FAPEMAT finalizado com sucesso!");
        } catch (\Throwable $e) {
            $this->error("❌ Falha na execução do Spider FAPEMAT: " . $e->getMessage());
            return Command::FAILURE;
        }

        $totalDepois = Edital::where('fonte', 'FAPEMAT')->count();
        $novos = max(0, $totalDepois - $totalAntes);

        $this->info("==================================================");
        $this->info(" Total de editais FAPEMAT no banco: {$totalDepois}");
        if ($novos > 0) {
            $this->info(" ✨ Novos editais cadastrados: {$novos}");
        } else {
            $this->line(" ℹ Nenhum edital novo inserido (editais já atualizados).");
        }
        $this->info("==================================================");

        return Command::SUCCESS;
    }
}
