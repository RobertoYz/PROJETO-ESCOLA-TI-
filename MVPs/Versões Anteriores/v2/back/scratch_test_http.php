<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

$url = 'https://finep.gov.br/e/chamada-publica/222684/1019381'; // Exemplo
try {
    $response = Http::get($url);

    if ($response->successful()) {
        echo "Sucesso!\n";
        
        $crawler = new Crawler($response->body());
        
        // Vamos tentar pegar o texto puro primeiro
        $texto = strip_tags($response->body());
        $textoLimpo = preg_replace('/\s+/', ' ', $texto); // Remove espaços duplos
        
        echo substr($textoLimpo, 0, 1000);
    } else {
        echo "Falha: " . $response->status();
    }
} catch (\Exception $e) {
    echo "Erro: " . $e->getMessage();
}
