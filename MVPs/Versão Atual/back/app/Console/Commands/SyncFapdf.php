<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RoachPHP\Roach;
use App\Spiders\FapdfSpider;
use App\Models\Edital;

class SyncFapdf extends Command
{
    /**
     * O nome e a assinatura do comando no terminal.
     *
     * @var string
     */
    protected $signature = 'fapdf:sync';

    /**
     * A descrição do comando.
     *
     * @var string
     */
    protected $description = 'Executa o Spider da FAPDF (Distrito Federal) e atualiza o banco de dados com os editais abertos';

    /**
     * Executa o comando.
     */
    public function handle()
    {
        $this->info("==================================================");
        $this->info(" 🏛️ INICIANDO SPIDER FAPDF (DISTRITO FEDERAL) ");
        $this->info("==================================================");

        $totalAntes = Edital::where('fonte', 'FAPDF')->count();
        $this->line("Editais da FAPDF atualmente no banco: {$totalAntes}");

        try {
            $this->warn("Acessando portal da FAPDF e extraindo chamadas públicas...");
            Roach::startSpider(FapdfSpider::class);
            $this->info("✔ Spider FAPDF finalizado com sucesso!");
        } catch (\Throwable $e) {
            $this->error("❌ Falha na conexão com portal da FAPDF: " . $e->getMessage());
        }

        // Garante a persistência do catálogo estruturado oficial da FAPDF
        $sincronizados = (new FapdfSpider())->syncCatalogoEstruturado();
        $this->line(" Catálogo estruturado oficial sincronizado ({$sincronizados} editais garantidos).");

        $totalDepois = Edital::where('fonte', 'FAPDF')->count();
        $novos = max(0, $totalDepois - $totalAntes);

        $this->info("==================================================");
        $this->info(" Total de editais FAPDF no banco: {$totalDepois}");
        if ($novos > 0) {
            $this->info(" ✨ Novos editais cadastrados: {$novos}");
        } else {
            $this->line(" ℹ Nenhum edital novo inserido (editais já atualizados).");
        }
        $this->info("==================================================");

        return Command::SUCCESS;
    }
}
