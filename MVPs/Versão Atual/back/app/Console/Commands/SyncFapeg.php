<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RoachPHP\Roach;
use App\Spiders\FapegSpider;
use App\Models\Edital;

class SyncFapeg extends Command
{
    /**
     * O nome e a assinatura do comando no terminal.
     *
     * @var string
     */
    protected $signature = 'fapeg:sync';

    /**
     * A descrição do comando.
     *
     * @var string
     */
    protected $description = 'Executa o Spider da FAPEG (Goiás) e atualiza o banco de dados com os editais abertos';

    /**
     * Executa o comando.
     */
    public function handle()
    {
        $this->info("==================================================");
        $this->info(" 🌾 INICIANDO SPIDER FAPEG (GOIÁS) ");
        $this->info("==================================================");

        $totalAntes = Edital::where('fonte', 'FAPEG')->count();
        $this->line("Editais da FAPEG atualmente no banco: {$totalAntes}");

        try {
            $this->warn("Acessando portal da FAPEG e extraindo chamadas públicas...");
            Roach::startSpider(FapegSpider::class);
            $this->info("✔ Spider FAPEG finalizado com sucesso!");
        } catch (\Throwable $e) {
            $this->error("❌ Falha na conexão com portal da FAPEG: " . $e->getMessage());
        }

        // Garante a persistência do catálogo estruturado oficial da FAPEG
        $sincronizados = (new FapegSpider())->syncCatalogoEstruturado();
        $this->line(" Catálogo estruturado oficial sincronizado ({$sincronizados} editais garantidos).");

        $totalDepois = Edital::where('fonte', 'FAPEG')->count();
        $novos = max(0, $totalDepois - $totalAntes);

        $this->info("==================================================");
        $this->info(" Total de editais FAPEG no banco: {$totalDepois}");
        if ($novos > 0) {
            $this->info(" ✨ Novos editais cadastrados: {$novos}");
        } else {
            $this->line(" ℹ Nenhum edital novo inserido (editais já atualizados).");
        }
        $this->info("==================================================");

        return Command::SUCCESS;
    }
}
