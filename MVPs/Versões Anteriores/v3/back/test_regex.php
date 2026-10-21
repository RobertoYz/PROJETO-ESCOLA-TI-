<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$edital = App\Models\Edital::where('fonte', 'FAPESP')->whereNull('deadline')->first();
if ($edital) {
    echo "Processando edital: " . $edital->id . "\n";
    App\Jobs\AnalyzeEditalWithIA::dispatchSync($edital);
    
    $edital->refresh();
    echo "Deadline: " . $edital->deadline . "\n";
    echo "Budget: " . $edital->max_budget . "\n";
    echo "Faturamento: " . $edital->ai_faturamento . "\n";
} else {
    echo "Nenhum edital sem deadline encontrado.\n";
}
