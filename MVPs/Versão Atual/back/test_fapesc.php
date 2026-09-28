<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$edital = App\Models\Edital::where('title', 'like', '%54/2026%')->first();
if(!$edital) {
    echo "Edital não encontrado no banco.\n";
    exit;
}
$url = $edital->source_url;
echo "URL do banco: " . $url . "\n";
$html = @file_get_contents($url);
if(!$html) {
    echo "Falha ao baixar URL: " . $url . "\n";
    exit;
}
use Symfony\Component\DomCrawler\Crawler;
$crawler = new Crawler($html);
$text = $crawler->filter('body')->text();
$text = preg_replace('/\s+/', ' ', $text);
file_put_contents('fapesc_text.txt', $text);
echo "Text written to fapesc_text.txt\n";
