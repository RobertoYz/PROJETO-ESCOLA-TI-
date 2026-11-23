<?php

namespace App\Spiders;

use Generator;
use RoachPHP\Http\Response;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FinepSpider extends BaseSpider
{
    public array $startUrls = [
        'https://www.finep.gov.br/oportunidades'
    ];

    public function parse(Response $response): Generator
    {
        $itens = [];

        // 1. Tenta consulta direta à API REST Liferay da FINEP (Ultra rápido, resiliente e sem depender de Puppeteer)
        try {
            $pageSize = 250;
            $urlApi = "https://www.finep.gov.br/o/c/chamadapublicas?sort=dataDePublicacao:desc&search=&page=1&pageSize={$pageSize}";
            $res = Http::withoutVerifying()
                ->withHeaders([
                    'Accept' => 'application/json',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ])
                ->timeout(30)
                ->get($urlApi);

            if ($res->successful()) {
                $primeiroJson = $res->json();
                $lastPage = $primeiroJson['lastPage'] ?? 1;
                $itens = $primeiroJson['items'] ?? [];

                // Puxa as próximas páginas se houver
                for ($pagina = 2; $pagina <= $lastPage; $pagina++) {
                    $nextUrl = "https://www.finep.gov.br/o/c/chamadapublicas?sort=dataDePublicacao:desc&search=&page={$pagina}&pageSize={$pageSize}";
                    $nextRes = Http::withoutVerifying()
                        ->withHeaders([
                            'Accept' => 'application/json',
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                        ])
                        ->timeout(30)
                        ->get($nextUrl);

                    if ($nextRes->successful()) {
                        $nextJson = $nextRes->json();
                        $itensDaPagina = $nextJson['items'] ?? [];
                        $itens = array_merge($itens, $itensDaPagina);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Consulta direta à API da FINEP falhou, tentando Browsershot: ' . $e->getMessage());
        }

        // 2. Se a chamada direta não retornou dados, tenta Browsershot como fallback
        if (empty($itens)) {
            try {
                $script = "
                    new Promise(async (resolve, reject) => {
                        try {
                            const PAGE_SIZE = 250;
                            const API_BASE  = '/o/c/chamadapublicas';
                            const SORT      = 'sort=dataDePublicacao:desc';

                            const primeiraResp = await fetch(
                                API_BASE + '?' + SORT + '&search=&page=1&pageSize=' + PAGE_SIZE,
                                { headers: { 'Accept': 'application/json' } }
                            );

                            if (!primeiraResp.ok) {
                                reject('HTTP ' + primeiraResp.status);
                                return;
                            }

                            const primeiroJson = await primeiraResp.json();
                            const lastPage     = primeiroJson.lastPage || 1;
                            let   todosItens   = primeiroJson.items || [];

                            for (let pagina = 2; pagina <= lastPage; pagina++) {
                                const resp = await fetch(
                                    API_BASE + '?' + SORT + '&search=&page=' + pagina + '&pageSize=' + PAGE_SIZE,
                                    { headers: { 'Accept': 'application/json' } }
                                );

                                if (!resp.ok) continue;

                                const dados = await resp.json();
                                const itensDaPagina = dados.items || [];
                                todosItens = todosItens.concat(itensDaPagina);
                            }

                            resolve(JSON.stringify(todosItens));
                        } catch (err) {
                            reject(err.toString());
                        }
                    });
                ";

                $browsershot = Browsershot::url((string) $response->getUri())
                    ->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox', '--disable-gpu'])
                    ->setOption('protocolTimeout', 600000)
                    ->timeout(600)
                    ->delay(5000);

                if (file_exists('C:/Program Files/Google/Chrome/Application/chrome.exe')) {
                    $browsershot->setChromePath('C:/Program Files/Google/Chrome/Application/chrome.exe');
                }

                $jsonString = $browsershot->evaluate($script);
                $itens = json_decode($jsonString, true) ?? [];
            } catch (\Throwable $e) {
                Log::error('Erro no FinepSpider Browsershot: ' . $e->getMessage());
            }
        }

        if (!is_array($itens) || empty($itens)) {
            return;
        }

        foreach ($itens as $item) {
            $externalId = $item['externalReferenceCode'] ?? '';
            $titulo = $this->limparTexto($item['titulo'] ?? 'Sem Título');
            
            $slug = Str::slug($item['titulo'] ?? 'Sem Título', '-');
            $finepId = $item['id'] ?? null;
            $linkCompleto = $finepId ? 'https://finep.gov.br/e/chamada-publica/222684/' . $finepId : 'https://www.finep.gov.br/financiamento-via-credito#' . $slug;
            $objetivo = $this->limparTexto($item['descricaoRawText'] ?? '');
            $operacao = $this->limparTexto($item['tipoDeOportunidade']['name'] ?? '');
            $condicao = $this->limparTexto($item['tipoCooperacao']['key'] ?? '');

            $publicoAlvo = $item['publicoAlvo'] ?? [];
            if (is_array($publicoAlvo) && !empty($publicoAlvo)) {
                $nomes = array_column($publicoAlvo, 'name');
                $publico = $this->limparTexto(implode(', ', array_filter($nomes)));
            } else {
                $publico = 'Não especificado';
            }

            yield $this->item([
                'external_id'     => $externalId ?: $this->gerarHashUnico($titulo, $linkCompleto),
                'title'           => $titulo,
                'published_at'    => isset($item['dataDePublicacao'])
                    ? date('Y-m-d', strtotime($item['dataDePublicacao']))
                    : date('Y-m-d'),
                'open_date'       => isset($item['vigenciaInicio'])
                    ? date('Y-m-d H:i:s', strtotime($item['vigenciaInicio']))
                    : null,
                'deadline'        => isset($item['prazoProposto']) 
                    ? date('Y-m-d H:i:s', strtotime($item['prazoProposto'])) 
                    : null,
                'result_date'     => isset($item['vigenciaFim'])
                    ? date('Y-m-d H:i:s', strtotime($item['vigenciaFim']))
                    : null,
                'source_url'      => $linkCompleto,
                'fonte'           => 'FINEP',
                'objetivo'        => $objetivo,
                'condicao_financiamento' => $condicao,
                'operacao'        => $operacao,
                'publico'         => $publico,
            ]);
        }
    }
}