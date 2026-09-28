<?php

namespace App\Spiders;

use RoachPHP\Http\Response;
use Symfony\Component\DomCrawler\Crawler;

class FapeScSpider extends BaseSpider
{
    public array $startUrls = [
        'https://fapesc.sc.gov.br/chamadas-abertas/'
    ];

    public function parse(Response $response): \Generator
    {
        // 1. Filtra os cards da FAPESC usando XPath
        $cards = $response->crawler()->filterXPath('//div[contains(@class, "upk-list-wrap")]//div[contains(@class, "upk-item")]');

        foreach ($cards as $node) {
            $card = new Crawler($node);

            $linkNode = $card->filterXPath('.//h3[contains(@class, "upk-title")]/a | .//div[contains(@class, "upk-title")]/a')->first();
            if (!$linkNode->count()) {
                continue;
            }

            $titulo = $this->limparTexto($linkNode->text());
            $link   = $linkNode->attr('href');

            // 2. Extrai meta (data) usando XPath
            $metaNode = $card->filterXPath('.//div[contains(@class, "upk-meta")]')->first();
            $dataTexto = $metaNode->count() ? $this->limparTexto($metaNode->text()) : null;
            
            $publishedAt = $this->parseDateBr($dataTexto) ?? date('Y-m-d');
            $tituloLimpo = $this->limparTexto($titulo);

            yield $this->item([
                'external_id'            => $this->gerarHashUnico($tituloLimpo, $link),
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
