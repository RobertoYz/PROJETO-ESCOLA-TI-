<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RoachPHP\Roach;
use App\Spiders\FinepSpider;
use App\Spiders\FapespSpider;
use App\Spiders\FapeScSpider;
use App\Models\Edital;

class SyncAllEditais extends Command
{
    /**
     * O nome e a assinatura do comando de console.
     *
     * @var string
     */
    protected $signature = 'editais:sync {--fonte= : Especifica a fonte para sincronizar (FINEP, FAPESP, FAPESC)}';

    /**
     * A descrição do comando de console.
     *
     * @var string
     */
    protected $description = 'Sincroniza os editais dos portais FINEP, FAPESP e FAPESC no banco de dados';

    /**
     * Executa o comando de console.
     */
    public function handle()
    {
        $fonte = strtoupper($this->option('fonte') ?? '');

        $this->info("==================================================");
        $this->info(" 🚀 INICIANDO SINCRONIZAÇÃO GERAL DE EDITAIS ");
        $this->info("==================================================");

        $totalAntes = Edital::count();

        // 1. FINEP
        if (empty($fonte) || $fonte === 'FINEP') {
            $this->warn("\n[1/3] Rodando Spider FINEP...");
            try {
                Roach::startSpider(FinepSpider::class);
                $this->info("✔ FINEP sincronizado com sucesso!");
            } catch (\Throwable $e) {
                $this->error("✖ Erro ao sincronizar FINEP: " . $e->getMessage());
            }
        }

        // 2. FAPESP
        if (empty($fonte) || $fonte === 'FAPESP') {
            $this->warn("\n[2/3] Rodando Spider FAPESP...");
            try {
                Roach::startSpider(FapespSpider::class);
                $this->info("✔ FAPESP sincronizado com sucesso!");
            } catch (\Throwable $e) {
                $this->error("✖ Erro ao sincronizar FAPESP: " . $e->getMessage());
            }
        }

        // 3. FAPESC
        if (empty($fonte) || $fonte === 'FAPESC') {
            $this->warn("\n[3/3] Rodando Spider FAPESC...");
            try {
                Roach::startSpider(FapeScSpider::class);
                $this->info("✔ FAPESC sincronizado com sucesso!");
            } catch (\Throwable $e) {
                $this->error("✖ Erro ao sincronizar FAPESC: " . $e->getMessage());
            }
        }

        $totalDepois = Edital::count();
        $novos = max(0, $totalDepois - $totalAntes);

        $this->info("\n==================================================");
        $this->info(" 🎉 SINCRONIZAÇÃO CONCLUÍDA ");
        $this->info(" Total de Editais no Banco: {$totalDepois} (Novos nesta rodada: {$novos})");
        $this->info("==================================================");

        return Command::SUCCESS;
    }
}
