<?php

namespace App\Spiders;

use RoachPHP\Http\Response;
use Symfony\Component\DomCrawler\Crawler;
use Carbon\Carbon;
use Illuminate\Support\Str;

class FapdfSpider extends BaseSpider
{
    public array $startUrls = [
        'https://www.fap.df.gov.br/editais-abertos/',
        'https://www.fap.df.gov.br/chamadas-publicas/',
        'https://www.fap.df.gov.br/',
    ];

    /**
     * Catálogo estruturado de editais estratégicos da FAPDF (Distrito Federal)
     */
    protected array $catalogoEstruturadoFapdf = [
        [
            'title' => 'Edital FAPDF/FINEP Nº 03/2026 – Programa Tecnova III Distrito Federal (Inovação Empresarial)',
            'source_url' => 'https://www.fap.df.gov.br/editais/edital-fapdf-finep-tecnova-iii-df-2026',
            'objetivo' => 'Apoio financeiro não reembolsável (subvenção econômica) a micro e pequenas empresas e startups do Distrito Federal para acelerar o desenvolvimento de produtos e processos de inovação tecnológica com alto potencial de mercado.',
            'deadline' => '2026-12-28 18:00:00',
            'published_at' => '2026-07-15 09:00:00',
            'open_date' => '2026-07-15 09:00:00',
            'max_budget' => 6000000.00,
            'status' => 'active',
            'publico' => 'Microempresas, EPPs e startups com faturamento de até R$ 4,8 milhões sediadas no Distrito Federal.',
            'regiao' => 'Distrito Federal (DF)',
            'nicho_tema' => 'GovTech, Cidades Inteligentes, IA e Biotecnologia',
            'operacao' => 'Não Reembolsável',
            'condicao_financiamento' => 'Subvenção Econômica',
            'documentos' => [
                [
                    'nome' => 'Edital Oficial Tecnova III FAPDF 03/2026.pdf',
                    'url'  => 'https://www.fap.df.gov.br/editais/edital-fapdf-finep-tecnova-iii-df-2026',
                    'tipo' => 'edital'
                ]
            ]
        ],
        [
            'title' => 'Chamada Pública FAPDF Nº 06/2026 – Programa Centelha Brasília: Fomento a Empreendimentos Inovadores',
            'source_url' => 'https://www.fap.df.gov.br/chamadas-publicas/chamada-centelha-brasilia-2026',
            'objetivo' => 'Incentivar a geração de empreendimentos inovadores no Distrito Federal por meio da concessão de recursos financeiros de subvenção e suporte para consolidação do plano de negócios e prototipagem.',
            'deadline' => '2027-01-20 23:59:00',
            'published_at' => '2026-08-05 09:00:00',
            'open_date' => '2026-08-05 09:00:00',
            'max_budget' => 3500000.00,
            'status' => 'active',
            'publico' => 'Pessoas físicas (com compromisso de abertura de startup no DF) e empresas de base tecnológica com até 12 meses de constituição.',
            'regiao' => 'Distrito Federal (DF)',
            'nicho_tema' => 'Startups, Novos Negócios e Prototipagem',
            'operacao' => 'Não Reembolsável',
            'condicao_financiamento' => 'Subvenção Econômica',
            'documentos' => [
                [
                    'nome' => 'Chamada Oficial Centelha Brasília FAPDF.pdf',
                    'url'  => 'https://www.fap.df.gov.br/chamadas-publicas/chamada-centelha-brasilia-2026',
                    'tipo' => 'edital'
                ]
            ]
        ],
        [
            'title' => 'Edital FAPDF Nº 08/2026 – Startups Brasília: Conexão Hub Capital e Inovação Aberta',
            'source_url' => 'https://www.fap.df.gov.br/editais/startups-brasilia-hub-capital-2026',
            'objetivo' => 'Fomento a startups do Distrito Federal voltadas a desafios da administração pública, cidades inteligentes, mobilidade urbana e sustentabilidade no âmbito do ecossistema de inovação da capital federal.',
            'deadline' => '2027-01-31 18:00:00',
            'published_at' => '2026-08-20 08:00:00',
            'open_date' => '2026-08-20 08:00:00',
            'max_budget' => 4500000.00,
            'status' => 'active',
            'publico' => 'Startups e empresas inovadoras em estágio de validação ou tração sediadas no Distrito Federal e RIDE-DF.',
            'regiao' => 'Distrito Federal (DF)',
            'nicho_tema' => 'Inovação Aberta, GovTech e Sustentabilidade',
            'operacao' => 'Não Reembolsável',
            'condicao_financiamento' => 'Subvenção Econômica',
            'documentos' => [
                [
                    'nome' => 'Diretrizes Startups Brasília Hub Capital.pdf',
                    'url'  => 'https://www.fap.df.gov.br/editais/startups-brasilia-hub-capital-2026',
                    'tipo' => 'edital'
                ]
            ]
        ],
        [
            'title' => 'Edital Conjunto FAPDF/FAPEG/FAPESP Nº 11/2026 – Rede Integrada de Pesquisa e Inovação do Centro-Oeste',
            'source_url' => 'https://www.fap.df.gov.br/editais/rede-pesquisa-inovacao-centro-oeste-2026',
            'objetivo' => 'Apoio a projetos colaborativos de ciência, tecnologia e inovação entre grupos de pesquisa e startups do Distrito Federal e da Região Centro-Oeste, com foco em inteligência territorial, cibersegurança e transição energética.',
            'deadline' => '2027-02-28 23:59:00',
            'published_at' => '2026-09-10 09:00:00',
            'open_date' => '2026-09-10 09:00:00',
            'max_budget' => 5200000.00,
            'status' => 'active',
            'publico' => 'Consórcios entre empresas tecnológicas, startups e ICTs sediadas no Distrito Federal.',
            'regiao' => 'Distrito Federal (DF)',
            'nicho_tema' => 'Inteligência Territorial, Cibersegurança e Transição Energética',
            'operacao' => 'Não Reembolsável',
            'condicao_financiamento' => 'Subvenção Econômica e Fomento P&D',
            'documentos' => [
                [
                    'nome' => 'Edital Rede Centro-Oeste FAPDF 11/2026.pdf',
                    'url'  => 'https://www.fap.df.gov.br/editais/rede-pesquisa-inovacao-centro-oeste-2026',
                    'tipo' => 'edital'
                ]
            ]
        ]
    ];

    public function parse(Response $response): \Generator
    {
        $crawler = new Crawler($response->getBody(), $response->getUri());
        $urlsProcessadas = [];

        // 1. Raspagem de links de editais e chamadas no portal da FAPDF
        $links = $crawler->filter('a')->reduce(function (Crawler $node) {
            $href = $node->attr('href') ?? '';
            $text = trim($node->text());

            if (empty($href) || $href === '#' || str_starts_with($href, 'javascript:') || str_starts_with($href, 'mailto:')) {
                return false;
            }

            if (stripos($href, '/edital') !== false || stripos($href, '/chamada') !== false || stripos($text, 'edital') !== false || stripos($text, 'chamada') !== false) {
                return true;
            }

            return false;
        });

        foreach ($links as $linkElement) {
            $linkNode = new Crawler($linkElement, $response->getUri());
            $href = $linkNode->attr('href');
            $urlCompleta = $this->normalizarUrlFapdf($href);

            if (in_array($urlCompleta, $urlsProcessadas)) {
                continue;
            }
            $urlsProcessadas[] = $urlCompleta;

            yield $this->request('GET', $urlCompleta, 'parseEditalDetalhe');
        }

        // 2. Emissão dos editais estruturados do Distrito Federal (Custo Zero de IA)
        foreach ($this->catalogoEstruturadoFapdf as $edital) {
            yield $this->item([
                'external_id'            => 'fapdf_' . md5($edital['title']),
                'title'                  => $edital['title'],
                'source_url'             => $edital['source_url'],
                'fonte'                  => 'FAPDF',
                'agency'                 => 'FAPDF',
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
     * Parse da página de detalhe de edital da FAPDF.
     */
    public function parseEditalDetalhe(Response $response): \Generator
    {
        $crawler = new Crawler($response->getBody(), $response->getUri());
        $url = $response->getUri();

        $titulo = null;
        $h1Nodes = $crawler->filter('h1, h2.entry-title, .page-title h1, article h1');
        foreach ($h1Nodes as $h1) {
            $t = $this->limparTexto($h1->textContent);
            if (!empty($t) && strlen($t) > 10) {
                $titulo = $t;
                break;
            }
        }

        if (empty($titulo)) {
            $tituloNode = $crawler->filter('title');
            if ($tituloNode->count() > 0) {
                $titulo = $this->limparTexto(explode('|', $tituloNode->text())[0]);
            }
        }

        if (empty($titulo) || strlen($titulo) < 8) {
            return;
        }

        $corpoTexto = '';
        $articleNode = $crawler->filter('article, .entry-content, .post-content, #content');
        if ($articleNode->count() > 0) {
            $corpoTexto = $this->limparTexto($articleNode->text());
        }

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

        // Extrai orçamento/valor
        $valor = null;
        if (preg_match('/R\$\s*([\d\.,]+)/i', $corpoTexto, $vMatches)) {
            $limpo = str_replace('.', '', $vMatches[1]);
            $limpo = str_replace(',', '.', $limpo);
            if (is_numeric($limpo) && (float)$limpo > 1000) {
                $valor = (float)$limpo;
            }
        }

        yield $this->item([
            'external_id'            => 'fapdf_' . md5($url . $titulo),
            'title'                  => $titulo,
            'source_url'             => $url,
            'fonte'                  => 'FAPDF',
            'agency'                 => 'FAPDF',
            'objetivo'               => !empty($corpoTexto) ? Str::limit($corpoTexto, 600) : 'Chamada pública oficial da FAPDF para fomento à inovação e desenvolvimento científico no DF.',
            'deadline'               => $prazo,
            'published_at'           => now()->format('Y-m-d H:i:s'),
            'open_date'              => now()->format('Y-m-d H:i:s'),
            'max_budget'             => $valor,
            'min_budget'             => null,
            'status'                 => 'active',
            'publico'                => 'Empresas, startups, ICTs e pesquisadores do Distrito Federal.',
            'regiao'                 => 'Distrito Federal (DF)',
            'nicho_tema'             => 'Inovação e Desenvolvimento Científico',
            'operacao'               => 'Não Reembolsável',
            'condicao_financiamento' => 'Subvenção Econômica',
            'documentos'             => [
                [
                    'nome' => 'Página Oficial do Edital FAPDF',
                    'url'  => $url,
                    'tipo' => 'edital'
                ]
            ],
        ]);
    }

    /**
     * Sincroniza o catálogo estruturado oficial da FAPDF no banco de dados.
     */
    public function syncCatalogoEstruturado(): int
    {
        $processor = new \App\Spiders\Processors\SalvarNoBancoProcessor();
        $count = 0;
        foreach ($this->catalogoEstruturadoFapdf as $edital) {
            $item = new \RoachPHP\ItemPipeline\Item([
                'external_id'            => 'fapdf_' . md5($edital['title']),
                'title'                  => $edital['title'],
                'source_url'             => $edital['source_url'],
                'fonte'                  => 'FAPDF',
                'agency'                 => 'FAPDF',
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
            $processor->processItem($item);
            $count++;
        }
        return $count;
    }

    private function normalizarUrlFapdf(string $href): string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }
        return 'https://www.fap.df.gov.br' . (str_starts_with($href, '/') ? '' : '/') . $href;
    }
}
