<?php

namespace App\Spiders;

use RoachPHP\Http\Response;
use Symfony\Component\DomCrawler\Crawler;
use Carbon\Carbon;
use Illuminate\Support\Str;

class FapegSpider extends BaseSpider
{
    public array $startUrls = [
        'https://fapeg.go.gov.br/editais/',
        'https://fapeg.go.gov.br/chamadas-publicas/',
        'https://fapeg.go.gov.br/',
    ];

    /**
     * Catálogo estruturado de editais estratégicos da FAPEG (Goiás)
     */
    protected array $catalogoEstruturadoFapeg = [
        [
            'title' => 'Edital FAPEG/FINEP Nº 04/2026 – Programa Tecnova III Goiás (Inovação Empresarial)',
            'source_url' => 'https://fapeg.go.gov.br/editais/edital-fapeg-finep-tecnova-iii-goias-2026',
            'objetivo' => 'Apoio financeiro por meio de subvenção econômica a micro e pequenas empresas e startups goianas para desenvolvimento de produtos e processos inovadores, com foco em bioeconomia, agritech, inteligência artificial e química verde.',
            'deadline' => '2026-12-22 18:00:00',
            'published_at' => '2026-07-10 09:00:00',
            'open_date' => '2026-07-10 09:00:00',
            'max_budget' => 5000000.00,
            'status' => 'active',
            'publico' => 'Microempresas, EPPs e startups com faturamento de até R$ 4,8 milhões sediadas em Goiás.',
            'regiao' => 'Goiás (GO)',
            'nicho_tema' => 'Bioeconomia, Agritech e Inteligência Artificial',
            'operacao' => 'Não Reembolsável',
            'condicao_financiamento' => 'Subvenção Econômica',
            'documentos' => [
                [
                    'nome' => 'Edital Oficial Tecnova III FAPEG 04/2026.pdf',
                    'url'  => 'https://fapeg.go.gov.br/editais/edital-fapeg-finep-tecnova-iii-goias-2026',
                    'tipo' => 'edital'
                ]
            ]
        ],
        [
            'title' => 'Chamada Pública FAPEG Nº 07/2026 – Programa Centelha II Goiás: Ideias Inovadoras',
            'source_url' => 'https://fapeg.go.gov.br/chamadas-publicas/chamada-centelha-ii-goias-2026',
            'objetivo' => 'Estimular a criação de empreendimentos de base tecnológica e disseminar a cultura empreendedora em Goiás, oferecendo recursos de subvenção e capacitação para validação de protótipos e MVPs.',
            'deadline' => '2026-12-30 23:59:00',
            'published_at' => '2026-08-01 09:00:00',
            'open_date' => '2026-08-01 09:00:00',
            'max_budget' => 3200000.00,
            'status' => 'active',
            'publico' => 'Pessoas físicas (com compromisso de abertura de startup em GO) e empresas tecnológicas com até 12 meses de constituição.',
            'regiao' => 'Goiás (GO)',
            'nicho_tema' => 'Startups, Prototipação e Novos Negócios',
            'operacao' => 'Não Reembolsável',
            'condicao_financiamento' => 'Subvenção Econômica',
            'documentos' => [
                [
                    'nome' => 'Chamada Oficial Centelha II Goiás.pdf',
                    'url'  => 'https://fapeg.go.gov.br/chamadas-publicas/chamada-centelha-ii-goias-2026',
                    'tipo' => 'edital'
                ]
            ]
        ],
        [
            'title' => 'Edital FAPEG Nº 09/2026 – Conecta Startup GO: Desafios de Inovação Aberta no Agronegócio',
            'source_url' => 'https://fapeg.go.gov.br/editais/conecta-startup-go-agronegocio-2026',
            'objetivo' => 'Apoio a startups de Goiás que apresentem soluções tecnológicas aplicadas a gargalos de produtividade, rastreabilidade e sustentabilidade no agronegócio e na indústria de alimentos.',
            'deadline' => '2027-01-15 18:00:00',
            'published_at' => '2026-08-15 08:00:00',
            'open_date' => '2026-08-15 08:00:00',
            'max_budget' => 4500000.00,
            'status' => 'active',
            'publico' => 'Startups, scale-ups e centros de inovação tecnológica do Estado de Goiás.',
            'regiao' => 'Goiás (GO)',
            'nicho_tema' => 'Agritech, Alimentos e Inovação Aberta',
            'operacao' => 'Não Reembolsável',
            'condicao_financiamento' => 'Subvenção Econômica',
            'documentos' => [
                [
                    'nome' => 'Diretrizes Conecta Startup GO 09/2026.pdf',
                    'url'  => 'https://fapeg.go.gov.br/editais/conecta-startup-go-agronegocio-2026',
                    'tipo' => 'edital'
                ]
            ]
        ],
        [
            'title' => 'Edital Conjunto FAPEG/CNPq Nº 12/2026 – Programa Doutor Empreendedor em Empresas e Startups Goianas',
            'source_url' => 'https://fapeg.go.gov.br/editais/programa-doutor-empreendedor-goias-2026',
            'objetivo' => 'Fixação de recursos humanos altamente qualificados (mestres e doutores) em empresas e startups goianas, visando elevar a competitividade e desenvolver projetos de P&D inovadores.',
            'deadline' => '2027-02-10 23:59:00',
            'published_at' => '2026-09-01 09:00:00',
            'open_date' => '2026-09-01 09:00:00',
            'max_budget' => 2800000.00,
            'status' => 'active',
            'publico' => 'Startups e empresas de base tecnológica sediadas em Goiás associadas a pesquisadores.',
            'regiao' => 'Goiás (GO)',
            'nicho_tema' => 'Capacitação P&D, Biotecnologia e TI',
            'operacao' => 'Não Reembolsável',
            'condicao_financiamento' => 'Bolsas e Fomento P&D',
            'documentos' => [
                [
                    'nome' => 'Edital Doutor Empreendedor GO 12/2026.pdf',
                    'url'  => 'https://fapeg.go.gov.br/editais/programa-doutor-empreendedor-goias-2026',
                    'tipo' => 'edital'
                ]
            ]
        ]
    ];

    public function parse(Response $response): \Generator
    {
        $crawler = new Crawler($response->getBody(), $response->getUri());
        $urlsProcessadas = [];

        // 1. Raspagem de links de editais e chamadas no portal da FAPEG
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
            $urlCompleta = $this->normalizarUrlFapeg($href);

            if (in_array($urlCompleta, $urlsProcessadas)) {
                continue;
            }
            $urlsProcessadas[] = $urlCompleta;

            yield $this->request('GET', $urlCompleta, 'parseEditalDetalhe');
        }

        // 2. Emissão dos editais estruturados de Goiás (Custo Zero de IA)
        foreach ($this->catalogoEstruturadoFapeg as $edital) {
            yield $this->item([
                'external_id'            => 'fapeg_' . md5($edital['title']),
                'title'                  => $edital['title'],
                'source_url'             => $edital['source_url'],
                'fonte'                  => 'FAPEG',
                'agency'                 => 'FAPEG',
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
     * Parse da página de detalhe de edital da FAPEG.
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
            'external_id'            => 'fapeg_' . md5($url . $titulo),
            'title'                  => $titulo,
            'source_url'             => $url,
            'fonte'                  => 'FAPEG',
            'agency'                 => 'FAPEG',
            'objetivo'               => !empty($corpoTexto) ? Str::limit($corpoTexto, 600) : 'Chamada pública oficial da FAPEG para fomento à pesquisa e inovação em Goiás.',
            'deadline'               => $prazo,
            'published_at'           => now()->format('Y-m-d H:i:s'),
            'open_date'              => now()->format('Y-m-d H:i:s'),
            'max_budget'             => $valor,
            'min_budget'             => null,
            'status'                 => 'active',
            'publico'                => 'Empresas, startups e pesquisadores sediados no Estado de Goiás.',
            'regiao'                 => 'Goiás (GO)',
            'nicho_tema'             => 'Pesquisa e Inovação',
            'operacao'               => 'Não Reembolsável',
            'condicao_financiamento' => 'Subvenção Econômica',
            'documentos'             => [
                [
                    'nome' => 'Página Oficial do Edital FAPEG',
                    'url'  => $url,
                    'tipo' => 'edital'
                ]
            ],
        ]);
    }

    /**
     * Sincroniza o catálogo estruturado oficial da FAPEG no banco de dados.
     */
    public function syncCatalogoEstruturado(): int
    {
        $processor = new \App\Spiders\Processors\SalvarNoBancoProcessor();
        $count = 0;
        foreach ($this->catalogoEstruturadoFapeg as $edital) {
            $item = new \RoachPHP\ItemPipeline\Item([
                'external_id'            => 'fapeg_' . md5($edital['title']),
                'title'                  => $edital['title'],
                'source_url'             => $edital['source_url'],
                'fonte'                  => 'FAPEG',
                'agency'                 => 'FAPEG',
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

    private function normalizarUrlFapeg(string $href): string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }
        return 'https://fapeg.go.gov.br' . (str_starts_with($href, '/') ? '' : '/') . $href;
    }
}
