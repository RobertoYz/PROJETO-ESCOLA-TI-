<?php

namespace App\Spiders;

use RoachPHP\Http\Response;
use RoachPHP\Spider\BasicSpider;
use Symfony\Component\DomCrawler\Crawler;

class FapeScSpider extends BasicSpider
{
    public array $startUrls = [
        'https://fapesc.sc.gov.br/chamadas-abertas/'
    ];

    public int $concurrency = 1;

    public array $itemProcessors = [
        \App\Spiders\Processors\SalvarNoBancoProcessor::class,
    ];

    public function parse(Response $response): \Generator
    {
        $cards = $response->filter('.upk-list-wrap .upk-item');

        foreach ($cards as $node) {
            $card = new Crawler($node);

            if (!$card->filter('.upk-title a')->count()) {
                continue;
            }

            $titulo = $card->filter('.upk-title a')->text();
            $link   = $card->filter('.upk-title a')->attr('href');

            $dataTexto = $card->filter('.upk-meta')->count() ? trim($card->filter('.upk-meta')->text()) : date('Y-m-d');
            
            try {
                // Tenta fazer o parse do formato dd/mm/yyyy
                $publishedAt = \Carbon\Carbon::createFromFormat('d/m/Y', $dataTexto)->format('Y-m-d');
            } catch (\Exception $e) {
                // Tenta fallback com parse direto do Carbon ou data atual
                try {
                    $publishedAt = \Carbon\Carbon::parse($dataTexto)->format('Y-m-d');
                } catch (\Exception $ex) {
                    $publishedAt = date('Y-m-d');
                }
            }

            $tituloLimpo = trim(preg_replace('/\s+/', ' ', $titulo));

            yield $this->item([
                'external_id'            => md5($tituloLimpo . $link),
                'title'                  => $tituloLimpo,
                'published_at'           => $publishedAt, 
                'source_url'             => $link,
                'fonte'                  => 'FAPESC',
                'objetivo'               => '', 
                'condicao_financiamento' => '', 
                'operacao'               => '', 
                'publico'                => '', 
            ]);
        }
    }
}
