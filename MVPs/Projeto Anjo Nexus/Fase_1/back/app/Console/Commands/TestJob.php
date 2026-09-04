<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestJob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-job';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $e = \App\Models\Edital::first();
            if (!$e) {
                $this->error("No edital found");
                return;
            }
            $job = new \App\Jobs\AnalyzeEditalWithIA($e);
            $service = new \App\Services\DeepSeekService();
            $job->handle($service);
            $this->info("Sucesso!");
        } catch (\Exception $ex) {
            $this->error("Erro: " . $ex->getMessage());
            $this->error($ex->getTraceAsString());
        }
    }
}
