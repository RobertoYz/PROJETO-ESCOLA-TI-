<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$edital = App\Models\Edital::find(53);
if ($edital) {
    // Apaga para forçar o regex a rodar
    $edital->deadline = null;
    $edital->max_budget = null;
    $edital->ai_faturamento = null;
    $edital->save();

    App\Jobs\AnalyzeEditalWithIA::dispatchSync($edital);

    $edital->refresh();
    echo "Deadline via Regex: " . $edital->deadline . "\n";
    echo "Budget via Regex: " . $edital->max_budget . "\n";
    echo "Faturamento via Regex: " . $edital->ai_faturamento . "\n";
}
