<?php

namespace App\Spiders;

use RoachPHP\Http\Response;
use RoachPHP\Spider\BasicSpider;
use Symfony\Component\DomCrawler\Crawler;
use Carbon\Carbon;
use Illuminate\Support\Str;

class FapespSpider extends BasicSpider
{
    public array $startUrls = [
        'https://fapesp.br/2185/chamadas-de-propostas-2026'
    ];

    public int $concurrency = 1;

    public array $itemProcessors = [
        \App\Spiders\Processors\SalvarNoBancoProcessor::class,
    ];

    public function parse(Response $response): \Generator
    {
        $itens = $response->filter('.page-body ul.list > li');

        foreach ($itens as $node) {
            $liNode = new Crawler($node);
            
            $linkNode = $liNode->filter('p > a')->first();
            if (!$linkNode->count()) continue;

            $link = $linkNode->attr('href');
            $tituloCompleto = trim($linkNode->text());

            // Tratamento das tags <br>
            $htmlInterno = $liNode->filter('p')->first()->html();
            $textoComQuebras = strip_tags(str_replace(['<br>', '<br />', '<BR>', '<BR />'], "\n", $htmlInterno));
            $linhas = explode("\n", $textoComQuebras);

            $codigoChamada = '';
            $prazoSubmissao = null;
            $objetivoPrevia = '';

            foreach ($linhas as $linha) {
                $linhaLimpa = trim(preg_replace('/\s+/', ' ', $linha));
                $linhaMinusculo = strtolower($linhaLimpa);

                if (str_contains($linhaMinusculo, 'chamada fapesp')) {
                    $codigoChamada = $linhaLimpa;
                }

                if (str_contains($linhaMinusculo, 'data limite') || str_contains($linhaMinusculo, 'prazo para recebimento')) {
                    if (preg_match('/([0-9]{2}\/[0-9]{2}\/[0-9]{4})/', $linhaLimpa, $matches)) {
                        try {
                            $prazoSubmissao = Carbon::createFromFormat('d/m/Y', $matches[1])->format('Y-m-d');
                        } catch (\Exception $e) {}
                    }
                }

                if (str_contains($linhaMinusculo, 'apoiará') || str_contains($linhaMinusculo, 'destinará') || str_contains($linhaMinusculo, 'selecionadas')) {
                    $objetivoPrevia = $linhaLimpa;
                }
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
                'published_at'           => date('Y-m-d'), // A lista não costuma ter data de publicação visível de forma fácil
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
