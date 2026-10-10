<?php
require __DIR__.'/vendor/autoload.php';
$html = file_get_contents('https://fapesc.sc.gov.br/edital-de-chamada-publica-fapesc-sed-n-o-054-2026-programa-de-fomento-a-pesquisa-e-inovacao-para-o-desenvolvimento-da-educacao-catarinense/');
file_put_contents('test_fapesc_54.html', $html);
$crawler = new \Symfony\Component\DomCrawler\Crawler($html);
$nodes = $crawler->filter('.elementor-text-editor p, .elementor-text-editor li, p, li, tr')->extract(['_text']);
foreach ($nodes as $node) {
    if (strpos(strtolower($node), 'r$') !== false) {
        echo "FOUND R$: " . trim($node) . "\n";
    }
    if (strpos(strtolower($node), 'quem pode') !== false || strpos(strtolower($node), 'proponentes') !== false || strpos(strtolower($node), 'elegibilidade') !== false || strpos(strtolower($node), 'público') !== false) {
        echo "FOUND PUBLICO: " . trim($node) . "\n";
    }
}
