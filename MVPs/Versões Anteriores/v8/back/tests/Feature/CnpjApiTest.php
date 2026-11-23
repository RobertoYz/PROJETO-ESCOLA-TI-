<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class CnpjApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * Testa consulta bem-sucedida via GET /api/cnpj/{cnpj} usando BrasilAPI.
     */
    public function test_consulta_cnpj_sucesso_via_brasilapi(): void
    {
        $cnpj = '34406665000111';

        Http::fake([
            'https://brasilapi.com.br/api/cnpj/v1/*' => Http::response([
                'cnpj' => $cnpj,
                'razao_social' => 'NEXUS INOVACOES EM TECNOLOGIA LTDA',
                'nome_fantasia' => 'NEXUS TECH',
                'data_inicio_atividade' => '2019-07-22',
                'descricao_situacao_cadastral' => 'ATIVA',
                'porte' => 'MICRO EMPRESA',
                'opcao_pelo_simples' => true,
                'cnae_fiscal' => '6201501',
                'cnae_fiscal_descricao' => 'Desenvolvimento de programas de computador sob encomenda',
                'cep' => '88015100',
                'logradouro' => 'RUA DAS FLORES',
                'numero' => '100',
                'complemento' => 'SALA 301',
                'bairro' => 'CENTRO',
                'municipio' => 'FLORIANOPOLIS',
                'uf' => 'SC',
                'email' => 'contato@nexustech.com.br',
                'ddd_telefone_1' => '(48) 3222-1234',
                'qsa' => [
                    ['nome_socio' => 'Roberto Silva', 'qualificacao_socio' => 'Sócio-Administrador']
                ]
            ], 200)
        ]);

        $response = $this->getJson("/api/cnpj/{$cnpj}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'sucesso',
                'dados' => [
                    'provedor' => 'brasilapi',
                    'cnpj' => $cnpj,
                    'cnpj_formatado' => '34.406.665/0001-11',
                    'razao_social' => 'NEXUS INOVACOES EM TECNOLOGIA LTDA',
                    'nome_fantasia' => 'NEXUS TECH',
                    'data_abertura' => '2019-07-22',
                    'porte' => 'Microempresa (ME)',
                    'regime_tributario' => 'Simples Nacional',
                    'nicho_atuacao' => 'Desenvolvimento de programas de computador sob encomenda',
                    'endereco' => [
                        'cidade' => 'FLORIANOPOLIS',
                        'estado' => 'SC'
                    ],
                    'startup_payload' => [
                        'razao_social' => 'NEXUS INOVACOES EM TECNOLOGIA LTDA',
                        'cnpj' => '34.406.665/0001-11'
                    ]
                ]
            ]);
    }

    /**
     * Testa fallback automático para ReceitaWS quando a BrasilAPI falhar (500).
     */
    public function test_fallback_automatico_para_receitaws_quando_brasilapi_falhar(): void
    {
        $cnpj = '34406665000111';

        Http::fake([
            'https://brasilapi.com.br/api/cnpj/v1/*' => Http::response(['message' => 'Internal Server Error'], 500),
            'https://receitaws.com.br/v1/cnpj/*' => Http::response([
                'status' => 'OK',
                'cnpj' => '34.406.665/0001-11',
                'nome' => 'NEXUS RECEITAWS RECOVERY LTDA',
                'fantasia' => 'NEXUS FALLBACK',
                'abertura' => '22/07/2019',
                'situacao' => 'ATIVA',
                'porte' => 'EPP',
                'simples' => ['optante' => true],
                'atividade_principal' => [
                    ['code' => '62.01-5-01', 'text' => 'Desenvolvimento de software']
                ],
                'municipio' => 'FLORIANOPOLIS',
                'uf' => 'SC'
            ], 200)
        ]);

        $response = $this->getJson("/api/cnpj/{$cnpj}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'sucesso',
                'dados' => [
                    'provedor' => 'receitaws',
                    'razao_social' => 'NEXUS RECEITAWS RECOVERY LTDA',
                    'nome_fantasia' => 'NEXUS FALLBACK',
                    'data_abertura' => '2019-07-22',
                    'porte' => 'Empresa de Pequeno Porte (EPP)'
                ]
            ]);
    }

    /**
     * Testa rejeição com código 422 para CNPJs com dígitos verificadores matematicamente inválidos.
     */
    public function test_rejeita_cnpj_matematicamente_invalido_sem_chamar_api_externa(): void
    {
        Http::fake();

        $cnpjInvalido = '12345678000199'; // Inválido

        $response = $this->getJson("/api/cnpj/{$cnpjInvalido}");

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'erro'
            ]);

        // Garante que não gastou chamadas HTTP externas para CNPJ inválido
        Http::assertNothingSent();
    }

    /**
     * Testa retorno 404 quando o CNPJ for válido matematicamente, mas não existir na Receita.
     */
    public function test_retorna_404_quando_cnpj_nao_encontrado_na_receita(): void
    {
        $cnpjValidoMasInexistente = '34406665000111';

        Http::fake([
            'https://brasilapi.com.br/api/cnpj/v1/*' => Http::response(['message' => 'CNPJ não encontrado'], 404),
            'https://receitaws.com.br/v1/cnpj/*' => Http::response(['status' => 'ERROR', 'message' => 'CNPJ não encontrado'], 200)
        ]);

        $response = $this->getJson("/api/cnpj/{$cnpjValidoMasInexistente}");

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'erro'
            ]);
    }

    /**
     * Testa endpoint POST /api/cnpj/consultar com validação de payload.
     */
    public function test_consulta_via_post_com_validacao_de_payload(): void
    {
        $cnpj = '34.406.665/0001-11';

        Http::fake([
            'https://brasilapi.com.br/api/cnpj/v1/*' => Http::response([
                'cnpj' => '34406665000111',
                'razao_social' => 'EMPRESA POST TESTE LTDA',
                'porte' => 'DEMAIS'
            ], 200)
        ]);

        // 1. Falha sem o campo obrigatório 'cnpj'
        $responseSemCnpj = $this->postJson('/api/cnpj/consultar', []);
        $responseSemCnpj->assertStatus(422)
            ->assertJsonStructure(['erros' => ['cnpj']]);

        // 2. Sucesso com o campo 'cnpj' formatado
        $responseSucesso = $this->postJson('/api/cnpj/consultar', [
            'cnpj' => $cnpj
        ]);

        $responseSucesso->assertStatus(200)
            ->assertJson([
                'status' => 'sucesso',
                'dados' => [
                    'razao_social' => 'EMPRESA POST TESTE LTDA'
                ]
            ]);
    }

    /**
     * Testa cache: segunda consulta não deve disparar nova requisição HTTP externa.
     */
    public function test_segunda_consulta_utiliza_cache_sem_chamada_http(): void
    {
        $cnpj = '34406665000111';

        Http::fake([
            'https://brasilapi.com.br/api/cnpj/v1/*' => Http::response([
                'cnpj' => $cnpj,
                'razao_social' => 'EMPRESA CACHE TESTE LTDA'
            ], 200)
        ]);

        // Primeira chamada (popula o cache)
        $resp1 = $this->getJson("/api/cnpj/{$cnpj}");
        $resp1->assertStatus(200);

        // Segunda chamada (deve ler do cache)
        $resp2 = $this->getJson("/api/cnpj/{$cnpj}");
        $resp2->assertStatus(200)
            ->assertJson([
                'dados' => [
                    'origem' => 'cache',
                    'razao_social' => 'EMPRESA CACHE TESTE LTDA'
                ]
            ]);

        // Exatamente 1 chamada HTTP foi realizada
        Http::assertSentCount(1);
    }
}
