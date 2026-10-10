<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$edital = App\Models\Edital::find(53);
if ($edital) {
    echo "Baixando conteudo de novo...\n";
    App\Jobs\ScrapeEditalCompletoJob::dispatchSync($edital);

    $edital->refresh();
    echo "Tamanho do texto: " . strlen($edital->conteudo_completo) . "\n";
    echo "Deadline via Regex: " . $edital->deadline . "\n";
    echo "Budget via Regex: " . $edital->max_budget . "\n";
    echo "Faturamento via Regex: " . $edital->ai_faturamento . "\n";
}
