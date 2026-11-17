<?php

namespace App\Spiders;

use RoachPHP\Http\Response;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class FapemigSpider extends BaseSpider
{
    public array $startUrls = [
        'https://fapemig.br/oportunidades/chamadas-e-editais'
    ];

    /**
     * Faz o parse da página de chamadas da FAPEMIG.
     */
    public function parse(Response $response): \Generator
    {
        $html = $response->getBody();

        // 1. O portal da FAPEMIG é construído em Nuxt.js SSR.
        // O estado hidratado dos editais fica no script JSON ou nos links de chamadas no HTML.
        // Buscamos todos os slugs das chamadas disponíveis
        preg_match_all('/href=["\'](\/oportunidades\/chamadas-e-editais\/([a-zA-Z0-9_-]+))["\']/', $html, $matches);

        $slugs = [];
        if (!empty($matches[2])) {
            $slugs = array_unique($matches[2]);
        }

        // Caso a regex de href não capture todos devido ao CSR/SSR, tentamos capturar slugs via Nuxt data embutido
        if (empty($slugs)) {
            preg_match_all('/"slug":"([a-zA-Z0-9_-]+)"/', $html, $nuxtMatches);
            if (!empty($nuxtMatches[1])) {
                $slugs = array_unique($nuxtMatches[1]);
            }
        }

        // Se ainda assim não capturou por padrão, capturamos padrões do tipo chamada-fapemig-*
        if (empty($slugs)) {
            preg_match_all('/chamada-fapemig-[a-zA-Z0-9_-]+/i', $html, $rawMatches);
            if (!empty($rawMatches[0])) {
                $slugs = array_unique($rawMatches[0]);
            }
        }

        foreach ($slugs as $slug) {
            $urlDetalhe = "https://fapemig.br/oportunidades/chamadas-e-editais/{$slug}";

            try {
                // Acessa a página de detalhe da chamada para obter os dados completos e documentos anexos
                $detalheResponse = Http::timeout(15)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    ])
                    ->get($urlDetalhe);

                if (!$detalheResponse->successful()) {
                    continue;
                }

                $detalheHtml = $detalheResponse->body();
                $dados = $this->extrairDadosDetalhe($slug, $urlDetalhe, $detalheHtml);

                if (!empty($dados['title'])) {
                    yield $this->item($dados);
                }
            } catch (\Throwable $e) {
                // Em caso de falha de conexão num edital específico, continua os outros
                continue;
            }
        }
    }

    /**
     * Extrai os dados específicos da página de detalhe da chamada FAPEMIG.
     */
    protected function extrairDadosDetalhe(string $slug, string $url, string $html): array
    {
        // 1. Título do edital
        $titulo = '';
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/si', $html, $m)) {
            $titulo = $this->limparTexto(strip_tags($m[1]));
        }
        if (empty($titulo)) {
            $titulo = ucwords(str_replace('-', ' ', $slug));
        }

        // 2. Extrai valor / orçamento
        $maxBudget = null;
        if (preg_match('/Valor(?:\s+total)?\s+da\s+chamada:?\s*R\$\s*([0-9\.,]+)\s*(milh[õo]es|mil)?/iu', $html, $m)) {
            $num = (float) str_replace(['.', ','], ['', '.'], $m[1]);
            $multiplicador = strtolower($m[2] ?? '');
            if (str_contains($multiplicador, 'milh')) {
                $maxBudget = $num * 1000000;
            } elseif (str_contains($multiplicador, 'mil')) {
                $maxBudget = $num * 1000;
            } else {
                $maxBudget = $num;
            }
        } elseif (preg_match('/R\$\s*([0-9\.,]+)\s*(milh[õo]es|mil)/iu', $html, $m)) {
            $num = (float) str_replace(['.', ','], ['', '.'], $m[1]);
            $multiplicador = strtolower($m[2] ?? '');
            if (str_contains($multiplicador, 'milh')) {
                $maxBudget = $num * 1000000;
            } elseif (str_contains($multiplicador, 'mil')) {
                $maxBudget = $num * 1000;
            } else {
                $maxBudget = $num;
            }
        } elseif (preg_match('/Valor\s*\([^\)]*\):\s*R\$\s*([0-9\.,]+)/iu', $html, $m)) {
            $maxBudget = (float) str_replace(['.', ','], ['', '.'], $m[1]);
        }

        // 3. Extrai datas (Publicação / Lançamento, Prazo de Submissão, Resultado)
        $publishedAt = null;
        if (preg_match('/Lançamento da chamada:\s*([0-9]{1,2}\s+de\s+[a-zA-ZçÇ]+\.?\s+de\s+[0-9]{4}|[0-9]{2}\/[0-9]{2}\/[0-9]{4})/i', $html, $m)) {
            $publishedAt = $this->converterDataExtensoOuBr($m[1]);
        }

        $deadline = null;
        if (preg_match('/(Limite para submissão|Data fim de submissão):\s*([0-9]{1,2}\s+de\s+[a-zA-ZçÇ]+\.?\s+de\s+[0-9]{4}|[0-9]{2}\/[0-9]{2}\/[0-9]{4})/i', $html, $m)) {
            $deadline = $this->converterDataExtensoOuBr($m[2]);
        }

        $resultDate = null;
        if (preg_match('/(Divulgação do Resultado|Resultado \(Previsto\)):\s*([0-9]{1,2}\s+de\s+[a-zA-ZçÇ]+\.?\s+de\s+[0-9]{4}|[0-9]{2}\/[0-9]{2}\/[0-9]{4})/i', $html, $m)) {
            $resultDate = $this->converterDataExtensoOuBr($m[2]);
        }

        // 4. Público e Linha de fomento
        $publico = '';
        if (preg_match('/Público-alvo:\s*([^<\n\r]+)/i', $html, $m)) {
            $publico = $this->limparTexto(strip_tags($m[1]));
        }

        $condicao = '';
        if (preg_match('/Linha de fomento:\s*([^<\n\r]+)/i', $html, $m)) {
            $condicao = $this->limparTexto(strip_tags($m[1]));
        }

        // 5. Descrição / Objetivo
        $objetivo = '';
        if (preg_match('/Descrição da chamada\s*<\/h[1-6]>\s*<p[^>]*>(.*?)<\/p>/si', $html, $m)) {
            $objetivo = $this->limparTexto(strip_tags($m[1]));
        } elseif (preg_match('/Descrição da chamada\s*<\/button>\s*<div[^>]*>(.*?)<\/div>/si', $html, $m)) {
            $objetivo = $this->limparTexto(strip_tags($m[1]));
        } elseif (preg_match('/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/si', $html, $m)) {
            $objetivo = $this->limparTexto($m[1]);
        } elseif (preg_match('/<p[^>]*class=["\'][^"\']*description[^"\']*["\'][^>]*>(.*?)<\/p>/si', $html, $m)) {
            $objetivo = $this->limparTexto(strip_tags($m[1]));
        }

        // 6. Extração de Documentos e Anexos (PDFs e DOCXs da FAPEMIG)
        $documentos = [];
        $pdfPrincipal = null;

        // Padrão de uploads da FAPEMIG (api.site.fapemig.br/wp-content/uploads/...)
        preg_match_all('/https:\\\\u002F\\\\u002Fapi\.site\.fapemig\.br\\\\u002Fwp-content\\\\u002Fuploads\\\\u002F[^\s"\'<>]+/i', $html, $fileMatchesEscaped);
        preg_match_all('/https:\/\/api\.site\.fapemig\.br\/wp-content\/uploads\/[^\s"\'<>]+/i', $html, $fileMatchesDirect);

        $todosLinks = array_merge(
            array_map(fn($l) => str_replace('\\u002F', '/', $l), $fileMatchesEscaped[0] ?? []),
            $fileMatchesDirect[0] ?? []
        );
        $linksUnicos = array_unique($todosLinks);

        foreach ($linksUnicos as $link) {
            $nomeArquivo = basename(parse_url($link, PHP_URL_PATH));
            $ext = strtolower(pathinfo($nomeArquivo, PATHINFO_EXTENSION));

            if (in_array($ext, ['pdf', 'doc', 'docx', 'zip'])) {
                // Tenta extrair um título legível
                $tituloDoc = str_replace(['_', '-'], ' ', pathinfo($nomeArquivo, PATHINFO_FILENAME));
                // Remove hashes do final do arquivo FAPEMIG (ex: __fsYlG2W2)
                $tituloDoc = preg_replace('/__[a-zA-Z0-9]+$/', '', $tituloDoc);

                $documentos[] = [
                    'titulo' => trim($tituloDoc),
                    'link'   => $link,
                ];

                // Identifica se é o texto completo do edital/chamada
                if ($ext === 'pdf' && ($pdfPrincipal === null || str_contains(strtolower($link), 'texto-completo') || str_contains(strtolower($link), 'chamada'))) {
                    $pdfPrincipal = $link;
                }
            }
        }

        // Status ativo/fechado
        $status = 'active';
        if ($deadline && Carbon::parse($deadline)->isPast()) {
            $status = 'closed';
        }

        return [
            'external_id'            => $this->gerarHashUnico($titulo, $url),
            'title'                  => $titulo,
            'source_url'             => $url,
            'original_file_path'     => $pdfPrincipal,
            'min_budget'             => null,
            'max_budget'             => $maxBudget,
            'open_date'              => $publishedAt,
            'published_at'           => $publishedAt ?? date('Y-m-d'),
            'deadline'               => $deadline,
            'result_date'            => $resultDate,
            'status'                 => $status,
            'fonte'                  => 'FAPEMIG',
            'regiao'                 => 'MG',
            'objetivo'               => $objetivo,
            'condicao_financiamento' => $condicao,
            'publico'                => $publico,
            'documentos'             => !empty($documentos) ? $documentos : null,
        ];
    }

    /**
     * Converte datas em extenso brasileiro (ex: '12 de fev. de 2027' ou '16 de nov. de 2026')
     */
    protected function converterDataExtensoOuBr(?string $texto): ?string
    {
        if (empty($texto)) {
            return null;
        }

        // Se já for DD/MM/YYYY
        if (preg_match('/([0-9]{2})\/([0-9]{2})\/([0-9]{4})/', $texto, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        $meses = [
            'jan' => '01', 'janeiro' => '01',
            'fev' => '02', 'fevereiro' => '02',
            'mar' => '03', 'março' => '03', 'marco' => '03',
            'abr' => '04', 'abril' => '04',
            'mai' => '05', 'maio' => '05',
            'jun' => '06', 'junho' => '06',
            'jul' => '07', 'julho' => '07',
            'ago' => '08', 'agosto' => '08',
            'set' => '09', 'setembro' => '09',
            'out' => '10', 'outubro' => '10',
            'nov' => '11', 'novembro' => '11',
            'dez' => '12', 'dezembro' => '12'
        ];

        if (preg_match('/([0-9]{1,2})\s+de\s+([a-zA-ZçÇ]+)\.?\s+de\s+([0-9]{4})/i', $texto, $m)) {
            $dia = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $mesTexto = strtolower(trim($m[2]));
            $mes = $meses[$mesTexto] ?? null;
            $ano = $m[3];

            if ($mes) {
                return "{$ano}-{$mes}-{$dia}";
            }
        }

        return $this->parseDateBr($texto);
    }
}
