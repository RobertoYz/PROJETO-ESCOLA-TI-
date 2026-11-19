<?php

namespace App\Spiders;

use RoachPHP\Http\Response;
use Symfony\Component\DomCrawler\Crawler;
use Carbon\Carbon;
use Illuminate\Support\Str;

class FapergsSpider extends BaseSpider
{
    public array $startUrls = [
        'https://fapergs.rs.gov.br/abertos',
        'https://fapergs.rs.gov.br/em-julgamento',
    ];

    public function parse(Response $response): \Generator
    {
        $crawler = new Crawler($response->getBody(), $response->getUri());

        // Identifica os links para as páginas individuais de cada edital
        // No portal da FAPERGS, os itens de editais ficam dentro de listas de conteúdo
        $links = $crawler->filter('a')->reduce(function (Crawler $node) {
            $href = $node->attr('href') ?? '';
            $text = trim($node->text());
            
            // Ignora âncoras, links para o próprio menu/topo e links externos comuns
            if (empty($href) || $href === '#' || str_starts_with($href, 'javascript:') || str_starts_with($href, 'mailto:')) {
                return false;
            }

            // Seleciona links que parecem editais ou que estão associados à tag ABERTO / EM JULGAMENTO
            if (stripos($href, '/programa-') !== false || stripos($href, '/edital-') !== false || stripos($href, '/chamada-') !== false) {
                return true;
            }

            return false;
        });

        $urlsProcessadas = [];

        foreach ($links as $linkElement) {
            $linkNode = new Crawler($linkElement, $response->getUri());
            $href = $linkNode->attr('href');
            $urlCompleta = $this->normalizarUrlFapergs($href);

            if (in_array($urlCompleta, $urlsProcessadas)) {
                continue;
            }
            $urlsProcessadas[] = $urlCompleta;

            // Dispara requisição filha para dissecar a página detalhada do edital
            yield $this->request('GET', $urlCompleta, 'parseEditalDetalhe');
        }

        // Fallback: se a listagem direta não trouxer classes específicas, busca links sob os artigos
        $artigosLinks = $crawler->filter('main a, .conteudo a, article a')->reduce(function (Crawler $node) {
            $href = $node->attr('href') ?? '';
            return !empty($href) && !str_starts_with($href, '#') && !str_contains($href, '/abertos') && !str_contains($href, '/em-julgamento') && strlen($node->text()) > 15;
        });

        foreach ($artigosLinks as $linkEl) {
            $node = new Crawler($linkEl, $response->getUri());
            $href = $node->attr('href');
            $urlCompleta = $this->normalizarUrlFapergs($href);

            if (in_array($urlCompleta, $urlsProcessadas)) {
                continue;
            }
            $urlsProcessadas[] = $urlCompleta;

            yield $this->request('GET', $urlCompleta, 'parseEditalDetalhe');
        }
    }

    /**
     * Faz o parse da página detalhada do edital na FAPERGS.
     */
    public function parseEditalDetalhe(Response $response): \Generator
    {
        $crawler = new Crawler($response->getBody(), $response->getUri());
        $url = $response->getUri();

        // 1. Título do edital
        // No portal FAPERGS, o primeiro h1 é o logo institucional; o segundo h1 é o título real do edital
        $h1Nodes = $crawler->filter('h1');
        $titulo = null;

        foreach ($h1Nodes as $h1) {
            $t = $this->limparTexto($h1->textContent);
            if (stripos($t, 'Fundação de Amparo') === false && stripos($t, 'Editais Abertos') === false && strlen($t) > 5) {
                $titulo = $t;
                break;
            }
        }

        if (empty($titulo)) {
            $altNode = $crawler->filter('article h1, .materia h1, .conteudo-artigo h1, h2.titulo')->first();
            if ($altNode->count()) {
                $titulo = $this->limparTexto($altNode->text());
            }
        }

        if (empty($titulo) || strlen($titulo) < 6) {
            return;
        }

        $html = $response->getBody();
        $textoCompleto = $this->limparTexto($crawler->filter('body')->text());

        // 2. Extrai documentos anexos (seção ARQUIVOS ANEXOS e links com .pdf)
        $documentos = $this->extrairAnexos($crawler);

        // URL principal do edital: se houver PDF nos anexos, usa o primeiro; senão a própria página
        $sourcePdfUrl = !empty($documentos) ? $documentos[0]['url'] : $url;

        // 3. Extrai data limite (deadline) e data de publicação
        $deadline = $this->extrairDeadline($textoCompleto);
        $publishedAt = $this->extrairPublishedAt($textoCompleto) ?? date('Y-m-d');

        // 4. Status
        $status = 'open';
        if (stripos($html, 'ENCERRADO') !== false || ($deadline && Carbon::parse($deadline)->isPast())) {
            $status = 'closed';
        }

        // 5. Extração de orçamento se houver
        $maxBudget = $this->extrairOrcamento($textoCompleto);

        // 6. Resumo / Objetivo
        $objetivo = $this->extrairObjetivo($crawler, $titulo);

        yield $this->item([
            'external_id'            => $this->gerarHashUnico($titulo, $url),
            'title'                  => $titulo,
            'published_at'           => $publishedAt,
            'open_date'              => $publishedAt,
            'deadline'               => $deadline,
            'status'                 => $status,
            'source_url'             => $url,
            'fonte'                  => 'FAPERGS',
            'agency'                 => 'FAPERGS',
            'max_budget'             => $maxBudget,
            'objetivo'               => $objetivo,
            'condicao_financiamento' => 'Subvenção Econômica / Apoio à Pesquisa e Inovação',
            'operacao'               => 'Não Reembolsável',
            'publico'                => 'Pesquisadores, Universidades, ICTs e Startups do Rio Grande do Sul',
            'documentos'             => $documentos,
        ]);
    }

    /**
     * Normaliza URL para o domínio da FAPERGS.
     */
    private function normalizarUrlFapergs(?string $url): string
    {
        if (empty($url)) {
            return 'https://fapergs.rs.gov.br/abertos';
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        $url = ltrim($url, '/');
        return 'https://fapergs.rs.gov.br/' . $url;
    }

    /**
     * Extrai os arquivos PDF da página de detalhes.
     */
    private function extrairAnexos(Crawler $crawler): array
    {
        $docs = [];
        $pdfLinks = $crawler->filter('a[href*=".pdf"], a[href*="download"]');

        foreach ($pdfLinks as $linkEl) {
            $node = new Crawler($linkEl);
            $href = $node->attr('href');
            $nome = $this->limparTexto($node->text());

            if (empty($href)) {
                continue;
            }

            $urlCompleta = $this->normalizarUrlFapergs($href);

            if (empty($nome)) {
                $nome = basename(parse_url($urlCompleta, PHP_URL_PATH));
            }

            $tipo = 'anexo';
            if (stripos($nome, 'Edital') !== false || stripos($nome, 'Guidelines') !== false) {
                $tipo = 'edital';
            } elseif (stripos($nome, 'Resultado') !== false) {
                $tipo = 'resultado';
            } elseif (stripos($nome, 'Retifica') !== false || stripos($nome, 'Errata') !== false) {
                $tipo = 'errata';
            }

            $docs[] = [
                'nome' => $nome,
                'url'  => $urlCompleta,
                'tipo' => $tipo,
            ];
        }

        return $docs;
    }

    /**
     * Extrai deadline procurando termos de submissão e datas no padrão brasileiro.
     */
    private function extrairDeadline(string $texto): ?string
    {
        if (preg_match('/(?:submiss[aã]o|inscri[çc][õo]es|prazo|limite)[^0-9]{1,40}(?:at[ée]|a)\s*([0-9]{2}\/[0-9]{2}\/[0-9]{4})/iu', $texto, $m)) {
            return $this->parseDateBr($m[1]);
        }

        if (preg_match('/(?:at[ée]|a)\s*([0-9]{2}\/[0-9]{2}\/[0-9]{4})/iu', $texto, $m)) {
            return $this->parseDateBr($m[1]);
        }

        return null;
    }

    /**
     * Extrai data de publicação.
     */
    private function extrairPublishedAt(string $texto): ?string
    {
        if (preg_match('/(?:publicado|lan[çc]amento|abertura)[^0-9]{1,30}([0-9]{2}\/[0-9]{2}\/[0-9]{4})/iu', $texto, $m)) {
            return $this->parseDateBr($m[1]);
        }

        return null;
    }

    /**
     * Extrai valor orçamentário se houver no texto.
     */
    private function extrairOrcamento(string $texto): ?float
    {
        if (preg_match('/R\$\s*([0-9\.,]+)\s*(milh[õo]es|mil)?/iu', $texto, $m)) {
            $num = (float) str_replace(['.', ','], ['', '.'], $m[1]);
            $mult = strtolower($m[2] ?? '');
            if (str_contains($mult, 'milh')) {
                return $num * 1000000;
            } elseif (str_contains($mult, 'mil')) {
                return $num * 1000;
            }
            return $num;
        }
        return null;
    }

    /**
     * Extrai o resumo/objetivo da página do edital.
     */
    private function extrairObjetivo(Crawler $crawler, string $titulo): string
    {
        $paragrafos = $crawler->filter('p, .texto-noticia');
        $texto = '';
        foreach ($paragrafos as $p) {
            $t = $this->limparTexto($p->textContent);
            if (strlen($t) > 30 && stripos($t, 'FAPERGS') !== false) {
                $texto .= ' ' . $t;
            }
        }

        if (empty($texto)) {
            $texto = $titulo;
        }

        return Str::limit(trim($texto), 450);
    }
}
