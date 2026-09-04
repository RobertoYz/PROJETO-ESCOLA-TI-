<?php

namespace App\Spiders;

use App\Traits\LimpaTextoTrait;
use Generator;
use RoachPHP\Http\Response;
use RoachPHP\Spider\BasicSpider;
use Spatie\Browsershot\Browsershot;

class FinepSpider extends BasicSpider
{
    use LimpaTextoTrait;

    public array $startUrls = [
        'https://www.finep.gov.br/oportunidades'
    ];

    public int $concurrency = 1;

    public array $itemProcessors = [
        \App\Spiders\Processors\SalvarNoBancoProcessor::class,
    ];

    public function parse(Response $response): Generator
    {
        $url = (string) $response->getUri();

        // dump("Iniciando varredura via API interna (fetch no contexto do browser)...");

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

                        console.log('[FinepSpider] Total de páginas:', lastPage);
                        console.log('[FinepSpider] Itens na página 1:', todosItens.length);

                        for (let pagina = 2; pagina <= lastPage; pagina++) {
                            const resp = await fetch(
                                API_BASE + '?' + SORT + '&search=&page=' + pagina + '&pageSize=' + PAGE_SIZE,
                                { headers: { 'Accept': 'application/json' } }
                            );

                            if (!resp.ok) {
                                console.warn('[FinepSpider] Falha na página ' + pagina + ': HTTP ' + resp.status);
                                continue;
                            }

                            const dados = await resp.json();
                            const itensDaPagina = dados.items || [];
                            todosItens = todosItens.concat(itensDaPagina);
                            console.log('[FinepSpider] Página ' + pagina + '/' + lastPage + ' → ' + itensDaPagina.length + ' itens');
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
                ->delay(5000) // Aguarda 5 segundos em vez de esperar a rede (que pode ficar travada infinitamente)
                ->evaluate($script);

            $itens = json_decode($jsonString, true);

            if (!is_array($itens) || empty($itens)) {
                // dump("AVISO: A API retornou vazio ou JSON inválido. Resposta bruta:");
                // dump($jsonString);
                return;
            }

            // dump("Concluído! API retornou " . count($itens) . " editais no total. Iniciando mapeamento...");

            foreach ($itens as $item) {
                $externalId = $item['externalReferenceCode'] ?? '';
                $titulo = $this->limpaTexto($item['titulo'] ?? 'Sem Título');
                
                // Feedback em tempo real no terminal!
                // dump("Mapeando edital: " . $titulo);
                $slug = \Illuminate\Support\Str::slug($item['titulo'] ?? 'Sem Título', '-');
                $finepId = $item['id'] ?? null;
                $linkCompleto = $finepId ? 'https://finep.gov.br/e/chamada-publica/222684/' . $finepId : 'https://www.finep.gov.br/financiamento-via-credito#' . $slug;
                $objetivo = $this->limpaTexto($item['descricaoRawText'] ?? '');
                $operacao = $this->limpaTexto($item['tipoDeOportunidade']['name'] ?? '');
                
                // Filtra para salvar APENAS Não Reembolsável
                if (stripos($operacao, 'reembolsável') === false || stripos($operacao, 'Não') === false) {
                    continue; // Ignora e não salva no banco
                }

                $condicao = $this->limpaTexto($item['tipoCooperacao']['key'] ?? '');

                $publicoAlvo = $item['publicoAlvo'] ?? [];
                if (is_array($publicoAlvo) && !empty($publicoAlvo)) {
                    $nomes = array_column($publicoAlvo, 'name');
                    $publico = $this->limpaTexto(implode(', ', array_filter($nomes)));
                } else {
                    $publico = 'Não especificado';
                }

                yield $this->item([
                    'external_id'     => $externalId,
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
            // dump("Erro na execução do FinepSpider: " . $e->getMessage());
            // dump($e->getTraceAsString());
        }
    }
}