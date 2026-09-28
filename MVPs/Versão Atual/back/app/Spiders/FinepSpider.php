<?php

namespace App\Spiders;

use Generator;
use RoachPHP\Http\Response;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Str;

class FinepSpider extends BaseSpider
{
    public array $startUrls = [
        'https://www.finep.gov.br/oportunidades'
    ];

    public function parse(Response $response): Generator
    {
        $url = (string) $response->getUri();

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

                            if (!resp.ok) {
                                continue;
                            }

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

            $jsonString = Browsershot::url($url)
                ->setNodeBinary('C:/nodejs/node.exe')
                ->setNpmBinary('C:/nodejs/npm.cmd')
                ->setChromePath('C:/Program Files/Google/Chrome/Application/chrome.exe')
                ->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox', '--disable-gpu'])
                ->setOption('protocolTimeout', 600000)
                ->timeout(600)
                ->delay(5000)
                ->evaluate($script);

            $itens = json_decode($jsonString, true);

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

        } catch (\Throwable $e) {
            // Trata exceções da execução do Browsershot
        }
    }
}