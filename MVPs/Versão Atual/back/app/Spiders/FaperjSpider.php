<?php

namespace App\Spiders;

use RoachPHP\Http\Response;
use Symfony\Component\DomCrawler\Crawler;
use Carbon\Carbon;
use Illuminate\Support\Str;

class FaperjSpider extends BaseSpider
{
    public array $startUrls = [
        'https://www.faperj.br/?id=28.5.7',
        'https://www.faperj.br/?id=lista-de-editais--chamadas-(2026)',
    ];

    public function parse(Response $response): \Generator
    {
        $crawler = new Crawler($response->getBody(), $response->getUri());

        // Cada edital na FAPERJ fica em blocos <p> que contêm link com texto "Edital FAPERJ" ou que apontam para .pdf
        $paragraphs = $crawler->filter('p');

        $editaisProcessados = [];

        foreach ($paragraphs as $pElement) {
            $pNode = new Crawler($pElement, $response->getUri());
            
            // Procura o link principal do edital
            $linkNode = $pNode->filter('a[href*=".pdf"], a')->reduce(function (Crawler $node) {
                $texto = $node->text();
                $href  = $node->attr('href') ?? '';
                return (stripos($texto, 'Edital FAPERJ') !== false || stripos($texto, 'Chamada') !== false) 
                    || (stripos($href, 'Edital_FAPERJ') !== false);
            })->first();

            if (!$linkNode->count()) {
                continue;
            }

            $tituloBruto = $this->limparTexto($linkNode->text());
            if (empty($tituloBruto) || strlen($tituloBruto) < 10) {
                continue;
            }

            // Normaliza a URL do PDF (se for relativa, prefixa com https://www.faperj.br/)
            $hrefPdf = $linkNode->attr('href');
            $urlPdf = $this->normalizarUrlFaperj($hrefPdf);

            // Evita duplicatas da mesma página caso o crawler pegue sub-nós
            if (in_array($urlPdf, $editaisProcessados)) {
                continue;
            }
            $editaisProcessados[] = $urlPdf;

            // Pega todo o texto do parágrafo para extrair datas e detalhes
            $htmlParagrafo = $pNode->html();
            $textoParagrafo = $this->limparTexto($pNode->text());

            // 1. Extrai data de publicação (Publicado em DO em DD/MM/YYYY ou Lançamento em DD/MM/YYYY)
            $publishedAt = $this->extrairDataPublicacao($textoParagrafo);

            // 2. Extrai data limite de submissão (removendo datas riscadas / prorrogadas)
            $deadline = $this->extrairDeadline($htmlParagrafo, $textoParagrafo);

            // 3. Define status (active / closed)
            $status = 'active';
            if ($deadline && Carbon::parse($deadline)->isPast()) {
                $status = 'closed';
            }

            // 4. Coleta documentos adicionais no mesmo bloco (Resultados, Erratas, Anexos)
            $documentos = $this->extrairDocumentosAnexos($pNode, $urlPdf);

            yield $this->item([
                'external_id'            => $this->gerarHashUnico($tituloBruto, $urlPdf),
                'title'                  => $tituloBruto,
                'published_at'           => $publishedAt,
                'open_date'              => $publishedAt,
                'deadline'               => $deadline,
                'status'                 => $status,
                'source_url'             => $urlPdf, // link direto do PDF/Chamada
                'fonte'                  => 'FAPERJ',
                'agency'                 => 'FAPERJ',
                'objetivo'               => $this->extrairResumo($textoParagrafo, $tituloBruto),
                'condicao_financiamento' => 'Subvenção Econômica / Auxílio à Pesquisa',
                'operacao'               => 'Não Reembolsável',
                'publico'                => 'Pesquisadores, Universidades e Startups/Empresas de Base Tecnológica do RJ',
                'documentos'             => $documentos,
            ]);
        }
    }

    /**
     * Normaliza URLs relativas da FAPERJ.
     */
    private function normalizarUrlFaperj(?string $url): string
    {
        if (empty($url)) {
            return 'https://www.faperj.br/?id=28.5.7';
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        $url = ltrim($url, '/');
        return 'https://www.faperj.br/' . $url;
    }

    /**
     * Extrai a data de lançamento ou publicação em Diário Oficial.
     */
    private function extrairDataPublicacao(string $texto): string
    {
        // Ex: Publicado em DO em 12/02/2026 ou Lançamento do edital: 19/02/2026
        if (preg_match('/(?:Publicado em D\.?O\.? em|Lançamento(?: do edital| do Programa)?[:\s]+)([0-9]{2}\/[0-9]{2}\/[0-9]{4})/iu', $texto, $m)) {
            $data = $this->parseDateBr($m[1]);
            if ($data) return $data;
        }

        if (preg_match('/([0-9]{2}\/[0-9]{2}\/[0-9]{4})/', $texto, $m)) {
            $data = $this->parseDateBr($m[1]);
            if ($data) return $data;
        }

        return date('Y-m-d');
    }

    /**
     * Extrai a data limite de submissão tratando erratas e prorrogações (ignora tags line-through).
     */
    private function extrairDeadline(string $html, string $texto): ?string
    {
        // Se houver texto riscado (strike ou text-decoration: line-through), nós removemos para não pegar a data antiga
        $htmlLimpo = preg_replace('/<span[^>]*style="[^"]*line-through[^"]*"[^>]*>.*?<\/span>/is', '', $html);
        $htmlLimpo = preg_replace('/<strike>.*?<\/strike>/is', '', $htmlLimpo);
        $textoSemRiscados = strip_tags($htmlLimpo);

        // Procura: Submissão de propostas on-line: ... a DD/MM/YYYY
        if (preg_match('/Submiss[aã]o de propostas(?:\s+on-line)?[:\s]+(?:de\s+[0-9]{2}\/[0-9]{2}\/[0-9]{4}\s+)?a\s+([0-9]{2}\/[0-9]{2}\/[0-9]{4})/iu', $textoSemRiscados, $m)) {
            return $this->parseDateBr($m[1]);
        }

        // Fallback: busca padrão "a DD/MM/YYYY" ou até DD/MM/YYYY
        if (preg_match('/(?:até|a)\s+([0-9]{2}\/[0-9]{2}\/[0-9]{4})/iu', $textoSemRiscados, $m)) {
            return $this->parseDateBr($m[1]);
        }

        return null;
    }

    /**
     * Extrai anexos, retificações e resultados do mesmo bloco.
     */
    private function extrairDocumentosAnexos(Crawler $pNode, string $urlPrincipal): array
    {
        $docs = [];
        $docs[] = [
            'nome' => 'Edital Completo (PDF)',
            'url'  => $urlPrincipal,
            'tipo' => 'edital'
        ];

        $outrosLinks = $pNode->filter('a');
        foreach ($outrosLinks as $linkEl) {
            $aNode = new Crawler($linkEl);
            $href = $aNode->attr('href');
            $texto = $this->limparTexto($aNode->text());

            if (!$href || empty($texto)) {
                continue;
            }

            $urlCompleta = $this->normalizarUrlFaperj($href);
            if ($urlCompleta === $urlPrincipal) {
                continue;
            }

            $tipo = 'anexo';
            if (stripos($texto, 'Resultado') !== false) {
                $tipo = 'resultado';
            } elseif (stripos($texto, 'Errata') !== false) {
                $tipo = 'errata';
            }

            $docs[] = [
                'nome' => $texto,
                'url'  => $urlCompleta,
                'tipo' => $tipo
            ];
        }

        return $docs;
    }

    /**
     * Extrai um resumo objetivo a partir do parágrafo.
     */
    private function extrairResumo(string $texto, string $titulo): string
    {
        $resumo = str_replace($titulo, '', $texto);
        $resumo = $this->limparTexto($resumo);
        return Str::limit($resumo, 400);
    }
}
