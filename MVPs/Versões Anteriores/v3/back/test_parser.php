<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$url = 'https://fapesc.sc.gov.br/wp-content/uploads/2026/09/Edital-FAPESC-52-2026-assinado.pdf';
$response = Illuminate\Support\Facades\Http::timeout(30)->get($url);
if ($response->successful()) {
    $tempPath = storage_path('app/temp_edital_test.pdf');
    file_put_contents($tempPath, $response->body());
    try {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($tempPath);
        $text = $pdf->getText();
        echo "TEXT LENGTH: " . strlen($text) . "\n";
        echo "FIRST 500 CHARS:\n";
        echo substr($text, 0, 500) . "\n";
    } catch (\Exception $e) {
        echo "EXCEPTION: " . $e->getMessage() . "\n";
    }
} else {
    echo "FAILED TO DOWNLOAD\n";
}
