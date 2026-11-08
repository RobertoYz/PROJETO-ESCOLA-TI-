<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Agencia;
use App\Models\Startup;
use App\Models\Edital;
use App\Models\Minuta;
use App\Models\User;
use App\Models\Plano;
use App\Services\MinutaAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MinutaApiTest extends TestCase
{
    use RefreshDatabase;

    private Agencia $agencia;
    private Startup $startup;
    private Edital $edital;
    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $plano = Plano::create([
            'nome' => 'Plano Pro',
            'preco' => 297.00
        ]);

        $this->agencia = Agencia::create([
            'nome' => 'Agência de Inovação SC',
            'plano_id' => $plano->id,
            'status_pagamento' => 'ativo',
            'pagamento_ativo' => true
        ]);

        $this->usuario = User::create([
            'name' => 'Consultor Chefe',
            'email' => 'consultor@agencia.com.br',
            'password' => bcrypt('Senha@12345'),
            'agencia_id' => $this->agencia->id
        ]);

        $this->startup = Startup::create([
            'agencia_id' => $this->agencia->id,
            'cnpj' => '12.345.678/0001-90',
            'razao_social' => 'Startup BioTech Inovacao Ltda',
            'nome_fantasia' => 'BioTech Inovação',
            'nicho_atuacao' => 'Biotecnologia',
            'porte' => 'ME'
        ]);

        $this->edital = Edital::create([
            'title' => 'Programa Centelha SC 2026',
            'fonte' => 'FAPESC',
            'objetivo' => 'Incentivo ao empreendedorismo inovador.',
            'status' => 'active'
        ]);
    }

    /**
     * RF-29: Teste de geração de minuta com IA e auditoria de versão inicial.
     */
    public function test_gerar_minuta_sucesso(): void
    {
        $this->mock(MinutaAiService::class, function ($mock) {
            $mock->shouldReceive('gerarMinuta')->once()->andReturn([
                'modelo_usado' => 'Google Gemini (gemini-2.5-flash)',
                'titulo_projeto' => 'BioTech Inovação - Plataforma de Biossensores',
                'conteudo_formatado' => '## 1. Objeto e Objetivos\nDesenvolvimento de biossensores.',
                'secoes' => [
                    'objeto_objetivos' => 'Desenvolver biossensores rápidos.',
                    'justificativa_problema' => 'Dificuldade de diagnóstico rápido.'
                ]
            ]);
        });

        $payload = [
            'startup_id' => $this->startup->id,
            'edital_id' => $this->edital->id,
            'ideia_projeto' => 'Desenvolver uma nova plataforma de biossensores de alta precisão.'
        ];

        $response = $this->actingAs($this->usuario, 'sanctum')
            ->postJson('/api/minutas/gerar', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'sucesso' => true,
                'mensagem' => 'Minuta gerada com sucesso pela Inteligência Artificial.'
            ])
            ->assertJsonStructure([
                'sucesso',
                'mensagem',
                'dados' => [
                    'id',
                    'versao',
                    'titulo_projeto',
                    'conteudo_gerado',
                    'status',
                    'modelo_ia_usado'
                ]
            ]);

        $this->assertDatabaseHas('minutas', [
            'startup_id' => $this->startup->id,
            'edital_id' => $this->edital->id,
            'versao' => 1,
            'titulo_projeto' => 'BioTech Inovação - Plataforma de Biossensores',
            'status' => 'concluido'
        ]);
    }

    /**
     * RF-33: Teste de listagem e filtragem de minutas.
     */
    public function test_listar_minutas_com_filtro(): void
    {
        Minuta::create([
            'startup_id' => $this->startup->id,
            'edital_id' => $this->edital->id,
            'user_id' => $this->usuario->id,
            'versao' => 1,
            'titulo_projeto' => 'Minuta 1',
            'conteudo_gerado' => 'Texto da minuta 1',
            'status' => 'concluido'
        ]);

        $outroEdital = Edital::create([
            'title' => 'Edital FINEP Tecnova',
            'fonte' => 'FINEP',
            'status' => 'active'
        ]);

        Minuta::create([
            'startup_id' => $this->startup->id,
            'edital_id' => $outroEdital->id,
            'user_id' => $this->usuario->id,
            'versao' => 1,
            'titulo_projeto' => 'Minuta 2 Outro Edital',
            'conteudo_gerado' => 'Texto da minuta 2',
            'status' => 'concluido'
        ]);

        $response = $this->getJson("/api/minutas?edital_id={$this->edital->id}");

        $response->assertStatus(200)
            ->assertJson([
                'sucesso' => true
            ]);

        $dados = $response->json('dados.data');
        $this->assertCount(1, $dados);
        $this->assertEquals('Minuta 1', $dados[0]['titulo_projeto']);
    }

    /**
     * RF-30 & RF-34: Atualização cria nova versão filha (imutabilidade histórica).
     */
    public function test_atualizar_minuta_cria_versao_filha_auditavel(): void
    {
        $minutaV1 = Minuta::create([
            'startup_id' => $this->startup->id,
            'edital_id' => $this->edital->id,
            'user_id' => $this->usuario->id,
            'versao' => 1,
            'titulo_projeto' => 'Projeto Original V1',
            'ideia_projeto' => 'Ideia inicial da startup.',
            'conteudo_gerado' => 'Texto original V1',
            'status' => 'concluido'
        ]);

        $payload = [
            'titulo_projeto' => 'Projeto Atualizado V2',
            'conteudo_gerado' => 'Texto com revisões e correções feitas pelo consultor humano.',
            'secoes_estruturadas' => [
                'objeto_objetivos' => 'Objetivo refinado.'
            ]
        ];

        $response = $this->actingAs($this->usuario, 'sanctum')
            ->putJson("/api/minutas/{$minutaV1->id}", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'sucesso' => true,
                'mensagem' => 'Proposta atualizada com sucesso para a versão v2.'
            ]);

        // A versão original 1 permanece intacta no banco
        $this->assertDatabaseHas('minutas', [
            'id' => $minutaV1->id,
            'versao' => 1,
            'titulo_projeto' => 'Projeto Original V1'
        ]);

        // Uma nova versão 2 é criada apontando para parent_minuta_id
        $this->assertDatabaseHas('minutas', [
            'parent_minuta_id' => $minutaV1->id,
            'versao' => 2,
            'titulo_projeto' => 'Projeto Atualizado V2',
            'status' => 'revisado'
        ]);
    }

    /**
     * RF-32: Teste de exportação da proposta em formato .docx.
     */
    public function test_exportar_minuta_docx_gera_download(): void
    {
        $minuta = Minuta::create([
            'startup_id' => $this->startup->id,
            'edital_id' => $this->edital->id,
            'user_id' => $this->usuario->id,
            'versao' => 1,
            'titulo_projeto' => 'Proposta Docx Export',
            'ideia_projeto' => 'Ideia para exportação docx.',
            'conteudo_gerado' => 'Texto completo do projeto de fomento.',
            'secoes_estruturadas' => [
                'objeto_objetivos' => 'Objetivo de teste exportação.',
                'metodologia_plano_trabalho' => 'Metodologia ágil e entregáveis.'
            ],
            'status' => 'concluido'
        ]);

        $response = $this->get("/api/minutas/{$minuta->id}/exportar-docx");

        $response->assertStatus(200);
        $this->assertTrue(
            str_contains($response->headers->get('content-type'), 'vnd.openxmlformats-officedocument.wordprocessingml.document') ||
            str_contains($response->headers->get('content-type'), 'octet-stream')
        );
    }
}

