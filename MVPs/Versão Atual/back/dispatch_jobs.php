<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

App\Models\Edital::where('fonte', 'FAPESC')->update(['ai_analyzed' => false]);
foreach(App\Models\Edital::where('fonte', 'FAPESC')->get() as $edital) {
    dispatch(new App\Jobs\AnalyzeEditalWithIA($edital));
}
echo "Jobs dispatched!\n";
