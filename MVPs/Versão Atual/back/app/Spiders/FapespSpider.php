<?php

namespace App\Spiders;

use RoachPHP\Http\Response;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Str;

class FapespSpider extends BaseSpider
{
    public array $startUrls = [
        'https://fapesp.br/2185/chamadas-de-propostas-2026'
    ];

    public function parse(Response $response): \Generator
    {
        // 1. Navega na Árvore XML / DOM usando XPath para isolar cada item da lista
        $itens = $response->filterXPath('//ul[contains(@class, "list")]/li');

        foreach ($itens as $node) {
            $liCrawler = new Crawler($node);

            // 2. Extrai o nó <a> via XPath
            $linkNode = $liCrawler->filterXPath('//p/a')->first();
            if (!$linkNode->count()) {
                continue;
            }

            $link = $linkNode->attr('href');
            $tituloCompleto = $this->limparTexto($linkNode->text());

            // 3. Extrai nós de texto filhos via XPath
            $textoCompleto = $this->limparTexto($liCrawler->text());
            
            $codigoChamada = '';
            if (preg_match('/(Chamada FAPESP\s+[0-9\/\-]+)/i', $textoCompleto, $matchesCodigo)) {
                $codigoChamada = $this->limparTexto($matchesCodigo[1]);
            }

            // 4. Extrai a data limite de submissão via helper BaseSpider parseDateBr
            $prazoSubmissao = null;
            if (preg_match('/(?:Data limite|Prazo)[^:]*:\s*([0-9]{2}\/[0-9]{2}\/[0-9]{4})/i', $textoCompleto, $matchesData)) {
                $prazoSubmissao = $this->parseDateBr($matchesData[1]);
            } else {
                $prazoSubmissao = $this->parseDateBr($textoCompleto);
            }

            // 5. Tenta isolar o objetivo
            $objetivoPrevia = '';
            if (preg_match('/(apoiará|destinará|selecionadas)[^.]*\./i', $textoCompleto, $matchesObj)) {
                $objetivoPrevia = $this->limparTexto($matchesObj[0]);
            }

            $tituloLimpo = $tituloCompleto;
            if (!empty($codigoChamada)) {
                $tituloLimpo = trim(str_replace($codigoChamada, '', $tituloCompleto), " -–\t\n\r\0\x0B");
            }

            preg_match('/\/([0-9]+)$/', $link, $idMatches);
            $idFinalFapesp = $idMatches[1] ?? 'FAPESP-' . Str::random(8);
            
            yield $this->item([
                'external_id'            => $idFinalFapesp,
                'title'                  => $codigoChamada ? "[{$codigoChamada}] {$tituloLimpo}" : $tituloLimpo,
                'published_at'           => date('Y-m-d'),
                'source_url'             => $link,
                'fonte'                  => 'FAPESP',
                'objetivo'               => $objetivoPrevia, 
                'condicao_financiamento' => '', 
                'operacao'               => '', 
                'publico'                => 'Startup / Empresa / Pesquisador',
                'deadline'               => $prazoSubmissao
            ]);
        }
    }
}
