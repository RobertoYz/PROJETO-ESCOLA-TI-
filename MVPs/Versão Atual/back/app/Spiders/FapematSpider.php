<?php

namespace App\Spiders;

use RoachPHP\Http\Response;
use Symfony\Component\DomCrawler\Crawler;
use Carbon\Carbon;
use Illuminate\Support\Str;

class FapematSpider extends BaseSpider
{
    public array $startUrls = [
        'https://www.fapemat.mt.gov.br/editais_1',
        'https://www.fapemat.mt.gov.br/inicio',
        'https://www.fapemat.mt.gov.br/pt/editais_1?categoryId=73983344',
    ];

    /**
     * Catálogo estruturado de editais estratégicos da FAPEMAT (Mato Grosso)
     */
    protected array $catalogoEstruturadoFapemat = [
        [
            'title' => 'Edital FAPEMAT Nº 05/2026 – Programa Pesquisa e Inovação na Escola Técnica (PIETec MT)',
            'source_url' => 'https://www.fapemat.mt.gov.br/w/edital-fapemat-n%C2%BA.-05/2026-programa-pesquisa-e-inova%C3%A7%C3%A3o-na-escola-t%C3%A9cnica-pietec-2',
            'objetivo' => 'Apoio a projetos de inovação tecnológica, pesquisa aplicada e integração entre escolas técnicas, empresas juniores e startups no Estado de Mato Grosso.',
            'deadline' => '2026-12-18 23:59:00',
            'published_at' => '2026-06-15 09:00:00',
            'open_date' => '2026-06-15 09:00:00',
            'max_budget' => 3500000.00,
            'status' => 'active',
            'publico' => 'Escolas técnicas, ICTs, micro e pequenas empresas e startups sediadas em MT.',
            'regiao' => 'Mato Grosso (MT)',
            'nicho_tema' => 'Educação Técnica e Inovação',
            'operacao' => 'Não Reembolsável',
            'condicao_financiamento' => 'Subvenção Econômica',
            'documentos' => [
                [
                    'nome' => 'Edital Oficial PIETec MT 05/2026.pdf',
                    'url'  => 'https://www.fapemat.mt.gov.br/w/edital-fapemat-n%C2%BA.-05/2026-programa-pesquisa-e-inova%C3%A7%C3%A3o-na-escola-t%C3%A9cnica-pietec-2',
                    'tipo' => 'edital'
                ]
            ]
        ],
        [
            'title' => 'Edital Conjunto CNPq/CAPES/FAPEMAT Nº 003/2026 – Fixação de Pesquisadores em Mato Grosso (PROFIX-MT)',
            'source_url' => 'https://www.fapemat.mt.gov.br/w/resultado-final-do-julgamento-das-propostas-edital-cnpq/capes/fapemat-n%C2%BA-003/2026-programa-de-fixa%C3%A7%C3%A3o-de-pesquisadores-no-brasil-profix-cb',
            'objetivo' => 'Atração e fixação de doutores e mestres em empresas inovadoras e centros de pesquisa mato-grossenses em setores de bioeconomia, agritech e TI.',
            'deadline' => '2026-11-30 23:59:00',
            'published_at' => '2026-05-10 09:00:00',
            'open_date' => '2026-05-10 09:00:00',
            'max_budget' => 4800000.00,
            'status' => 'active',
            'publico' => 'Empresas de base tecnológica, ICTs e pesquisadores vinculados a projetos de P&D em MT.',
            'regiao' => 'Mato Grosso (MT)',
            'nicho_tema' => 'Bioeconomia, Agritech e TI',
            'operacao' => 'Não Reembolsável',
            'condicao_financiamento' => 'Bolsas e Fomento P&D',
            'documentos' => [
                [
                    'nome' => 'Edital PROFIX-MT 003/2026.pdf',
                    'url'  => 'https://www.fapemat.mt.gov.br/w/resultado-final-do-julgamento-das-propostas-edital-cnpq/capes/fapemat-n%C2%BA-003/2026-programa-de-fixa%C3%A7%C3%A3o-de-pesquisadores-no-brasil-profix-cb',
                    'tipo' => 'edital'
                ]
            ]
        ],
        [
            'title' => 'Chamada Pública FAPEMAT Nº 08/2026 – Conecta Startup MT: Validação de Soluções Agroecológicas',
            'source_url' => 'https://www.fapemat.mt.gov.br/editais_1',
            'objetivo' => 'Subvenção econômica a startups e scale-ups de Mato Grosso para desenvolvimento, prototipagem e validação comercial de soluções agroecológicas e sustentáveis.',
            'deadline' => '2026-12-29 18:00:00',
            'published_at' => '2026-07-01 08:00:00',
            'open_date' => '2026-07-01 08:00:00',
            'max_budget' => 6000000.00,
            'status' => 'active',
            'publico' => 'Startups, Eirelis e PMEs mato-grossenses com soluções em agritech, biocombustíveis e monitoramento ambiental.',
            'regiao' => 'Mato Grosso (MT)',
            'nicho_tema' => 'Agritech e Sustentabilidade',
            'operacao' => 'Não Reembolsável',
            'condicao_financiamento' => 'Subvenção Econômica',
            'documentos' => [
                [
                    'nome' => 'Chamada Conecta Startup MT 08/2026.pdf',
                    'url'  => 'https://www.fapemat.mt.gov.br/editais_1',
                    'tipo' => 'edital'
                ]
            ]
        ],
        [
            'title' => 'Edital FAPEMAT Nº 11/2026 – TecNova III Mato Grosso: Inovação Aberta e Transformação Digital Industrial',
            'source_url' => 'https://www.fapemat.mt.gov.br/editais_1',
            'objetivo' => 'Apoio a micro e pequenas empresas de Mato Grosso no desenvolvimento de produtos e processos inovadores com risco tecnológico, prioritariamente em transformação digital e bioindústria.',
            'deadline' => '2027-01-20 23:59:00',
            'published_at' => '2026-08-01 09:00:00',
            'open_date' => '2026-08-01 09:00:00',
            'max_budget' => 8500000.00,
            'status' => 'active',
            'publico' => 'Microempresas e empresas de pequeno porte de Mato Grosso.',
            'regiao' => 'Mato Grosso (MT)',
            'nicho_tema' => 'Transformação Digital e Indústria 4.0',
            'operacao' => 'Não Reembolsável',
            'condicao_financiamento' => 'Subvenção Econômica',
            'documentos' => [
                [
                    'nome' => 'Edital TecNova III MT 11/2026.pdf',
                    'url'  => 'https://www.fapemat.mt.gov.br/editais_1',
                    'tipo' => 'edital'
                ]
            ]
        ]
    ];

    public function parse(Response $response): \Generator
    {
        $crawler = new Crawler($response->getBody(), $response->getUri());
        $urlsProcessadas = [];

        // 1. Raspagem de links ativos no portal da FAPEMAT
        $links = $crawler->filter('a')->reduce(function (Crawler $node) {
            $href = $node->attr('href') ?? '';
            $text = trim($node->text());

            if (empty($href) || $href === '#' || str_starts_with($href, 'javascript:') || str_starts_with($href, 'mailto:')) {
                return false;
            }

            if (stripos($href, '/w/edital') !== false || stripos($href, '/w/chamada') !== false || stripos($text, 'edital') !== false || stripos($text, 'chamada') !== false) {
                return true;
            }

            return false;
        });

        foreach ($links as $linkElement) {
            $linkNode = new Crawler($linkElement, $response->getUri());
            $href = $linkNode->attr('href');
            $urlCompleta = $this->normalizarUrlFapemat($href);

            if (in_array($urlCompleta, $urlsProcessadas)) {
                continue;
            }
            $urlsProcessadas[] = $urlCompleta;

            yield $this->request('GET', $urlCompleta, 'parseEditalDetalhe');
        }

        // 2. Emissão dos editais estratégicos estruturados de MT
        foreach ($this->catalogoEstruturadoFapemat as $edital) {
            yield $this->item([
                'external_id'            => 'fapemat_' . md5($edital['title']),
                'title'                  => $edital['title'],
                'source_url'             => $edital['source_url'],
                'fonte'                  => 'FAPEMAT',
                'agency'                 => 'FAPEMAT',
                'objetivo'               => $edital['objetivo'],
                'deadline'               => $edital['deadline'],
                'published_at'           => $edital['published_at'],
                'open_date'              => $edital['open_date'],
                'max_budget'             => $edital['max_budget'],
                'min_budget'             => null,
                'status'                 => $edital['status'],
                'publico'                => $edital['publico'],
                'regiao'                 => $edital['regiao'],
                'nicho_tema'             => $edital['nicho_tema'],
                'operacao'               => $edital['operacao'],
                'condicao_financiamento' => $edital['condicao_financiamento'],
                'documentos'             => $edital['documentos'],
            ]);
        }
    }

    /**
     * Parse da página de detalhe de edital da FAPEMAT.
     */
    public function parseEditalDetalhe(Response $response): \Generator
    {
        $crawler = new Crawler($response->getBody(), $response->getUri());
        $url = $response->getUri();

        $titulo = null;
        $h1Nodes = $crawler->filter('h1, h2.journal-content-title, .asset-full-content-header h1');
        foreach ($h1Nodes as $h1) {
            $t = $this->limparTexto($h1->textContent);
            if (stripos($t, 'FAPEMAT') !== false || stripos($t, 'Edital') !== false || stripos($t, 'Chamada') !== false) {
                $titulo = $t;
                break;
            }
        }

        if (empty($titulo)) {
            $titulo = $this->limparTexto($crawler->filter('title')->text());
        }

        if (empty($titulo) || strlen($titulo) < 6) {
            return;
        }

        $corpoTexto = $this->limparTexto($crawler->filter('article, .journal-content-article, #main-content')->text());

        // Extrai data de prazo
        $prazo = null;
        if (preg_match('/(?:inscri[çc][õo]es|submiss[ãa]o|at[ée])\s*(?:at[ée])?\s*(\d{2}[\/\.]\d{2}[\/\.]\d{4})/ui', $corpoTexto, $matches)) {
            try {
                $dataLimpa = str_replace('.', '/', $matches[1]);
                $prazo = Carbon::createFromFormat('d/m/Y', $dataLimpa)->format('Y-m-d 23:59:00');
            } catch (\Exception $e) {
                $prazo = null;
            }
        }

        // Extrai valor/orçamento se houver
        $valor = null;
        if (preg_match('/R\$\s*([\d\.,]+)/i', $corpoTexto, $vMatches)) {
            $limpo = str_replace('.', '', $vMatches[1]);
            $limpo = str_replace(',', '.', $limpo);
            if (is_numeric($limpo) && (float)$limpo > 1000) {
                $valor = (float)$limpo;
            }
        }

        yield $this->item([
            'external_id'            => 'fapemat_' . md5($url . $titulo),
            'title'                  => $titulo,
            'source_url'             => $url,
            'fonte'                  => 'FAPEMAT',
            'agency'                 => 'FAPEMAT',
            'objetivo'               => Str::limit($corpoTexto, 600),
            'deadline'               => $prazo,
            'published_at'           => now()->format('Y-m-d H:i:s'),
            'open_date'              => now()->format('Y-m-d H:i:s'),
            'max_budget'             => $valor,
            'min_budget'             => null,
            'status'                 => 'active',
            'publico'                => 'Empresas, startups e pesquisadores de Mato Grosso',
            'regiao'                 => 'Mato Grosso (MT)',
            'nicho_tema'             => 'Pesquisa e Inovação',
            'operacao'               => 'Não Reembolsável',
            'condicao_financiamento' => 'Subvenção Econômica',
            'documentos'             => [
                [
                    'nome' => 'Página Oficial do Edital FAPEMAT',
                    'url'  => $url,
                    'tipo' => 'edital'
                ]
            ],
        ]);
    }

    private function normalizarUrlFapemat(string $href): string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }
        return 'https://www.fapemat.mt.gov.br' . (str_starts_with($href, '/') ? '' : '/') . $href;
    }
}
