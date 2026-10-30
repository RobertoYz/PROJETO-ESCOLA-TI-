<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Edital;

class DebugEditalPdf extends Command
{
    /**
     * Assinatura do comando.
     *
     * @var string
     */
    protected $signature = 'editais:debug-pdf {id? : ID do edital para inspecionar}';

    /**
     * Descrição do comando.
     *
     * @var string
     */
    protected $description = 'Audita e inspeciona o texto bruto lido do PDF/HTML e a lista de documentos extraídos';

    /**
     * Executa o comando.
     */
    public function handle()
    {
        $id = $this->argument('id');

        if (!$id) {
            $edital = Edital::whereNotNull('documentos')->latest()->first();
        } else {
            $edital = Edital::find($id);
        }

        if (!$edital) {
            $this->error("Edital não encontrado.");
            return Command::FAILURE;
        }

        $this->info("==================================================");
        $this->info(" 🔍 INSPEÇÃO DE AUDITORIA DO EDITAL #{$edital->id}");
        $this->info(" Título: {$edital->title}");
        $this->info(" Fonte: {$edital->fonte} | URL: {$edital->source_url}");
        $this->info("==================================================");

        $this->warn("\n📄 DOCUMENTOS EXIGIDOS EXTRACTED:");
        if (!empty($edital->documentos) && is_array($edital->documentos)) {
            foreach ($edital->documentos as $index => $doc) {
                $num = $index + 1;
                $this->line(" [{$num}] ✔ " . ($doc['titulo'] ?? 'Documento'));
            }
        } else {
            $this->error("Nenhum documento cadastrado nesta entrada.");
        }

        $this->warn("\n📝 PRÉVIA DO TEXTO COMPLETO LIDO DO PDF/HTML (Primeiros 800 caracteres):");
        $previaTexto = mb_substr($edital->conteudo_completo ?? 'Nenhum conteúdo salvo', 0, 800);
        $this->line($previaTexto);

        $this->info("\n==================================================");
        return Command::SUCCESS;
    }
}
