<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Edital;
use App\Services\PdfExtractorService;
use App\Jobs\ScrapeEditalCompletoJob;

class ProcessarPdfEditaisCommand extends Command
{
    /**
     * O nome e a assinatura do comando de console.
     *
     * @var string
     */
    protected $signature = 'editais:processar-pdfs 
                            {--fonte= : Filtra por fonte (FINEP, FAPESP, FAPESC, FAPEMIG)} 
                            {--limit= : Quantidade máxima de editais para processar}
                            {--force : Reprocessa mesmo editais que já possuem conteúdo/documentos extraídos}';

    /**
     * A descrição do comando de console.
     *
     * @var string
     */
    protected $description = 'Varre os editais cadastrados, localiza os links de PDFs e extrai o texto, prazos, orçamentos e checklist de documentos (Custo Zero de IA)';

    /**
     * Executa o comando de console.
     */
    public function handle(PdfExtractorService $pdfService)
    {
        $fonte = $this->option('fonte') ? strtoupper($this->option('fonte')) : null;
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $force = $this->option('force');

        $this->info("==================================================");
        $this->info(" 📑 INICIANDO PROCESSAMENTO DETERMINÍSTICO DE PDFS");
        $this->info("==================================================");

        $query = Edital::query()->whereNotNull('source_url');

        if ($fonte) {
            $query->where('fonte', $fonte);
        }

        if (!$force) {
            // Processa prioritariamente os que ainda não tem conteudo completo ou documentos extraídos
            $query->where(function ($q) {
                $q->whereNull('conteudo_completo')
                  ->orWhere('conteudo_completo', '')
                  ->orWhereNull('documentos');
            });
        }

        if ($limit) {
            $query->limit($limit);
        }

        $editais = $query->get();

        if ($editais->isEmpty()) {
            $this->info("✔ Todos os editais selecionados já possuem documentos e conteúdo extraídos.");
            $this->info("Dica: Use a flag --force para reprocessar tudo.");
            return Command::SUCCESS;
        }

        $total = $editais->count();
        $this->info("Processando {$total} editais...\n");

        $sucessos = 0;
        $erros = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($editais as $edital) {
            try {
                // Executa a extração síncrona usando o Job determinístico do sistema
                $job = new ScrapeEditalCompletoJob($edital);
                $job->handle($pdfService);
                $sucessos++;
            } catch (\Throwable $e) {
                $erros++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("==================================================");
        $this->info(" 🎉 PROCESSAMENTO DE PDFS CONCLUÍDO!");
        $this->info(" Sucessos: {$sucessos} | Falhas/Ignorados: {$erros}");
        $this->info("==================================================");

        return Command::SUCCESS;
    }
}
