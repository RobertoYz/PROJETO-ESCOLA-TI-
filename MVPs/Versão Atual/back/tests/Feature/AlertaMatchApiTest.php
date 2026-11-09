<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Agencia;
use App\Models\Plano;
use App\Models\Startup;
use App\Models\Edital;
use App\Models\AlertaMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AlertaMatchApiTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private Startup $startup;
    private Edital $editalCompativel;
    private Edital $editalIncompativel;

    protected function setUp(): void
    {
        parent::setUp();

        $plano = Plano::create([
            'nome' => 'Plano Alertas',
            'preco' => 297.00
        ]);

        $agencia = Agencia::create([
            'nome' => 'Agência Inova',
            'plano_id' => $plano->id,
            'status_pagamento' => 'ativo',
            'pagamento_ativo' => true
        ]);

        $this->usuario = User::create([
            'name' => 'Consultor Gestor',
            'email' => 'gestor@inova.com.br',
            'password' => bcrypt('Senha@12345'),
            'agencia_id' => $agencia->id
        ]);

        // Startup no nicho de Biotecnologia, porte ME
        $this->startup = Startup::create([
            'agencia_id' => $agencia->id,
            'cnpj' => '22.333.444/0001-55',
            'razao_social' => 'Biotecnologia Brasil Ltda',
            'nome_fantasia' => 'BioTech Brasil',
            'nicho_atuacao' => 'Biotecnologia',
            'porte' => 'ME'
        ]);

        // Edital aderente (nicho biotecnologia/tecnologia, público startups, com orçamento) -> Score >= 85
        $this->editalCompativel = Edital::create([
            'title' => 'Edital FINEP Subvenção Biotecnologia',
            'fonte' => 'FINEP',
            'publico' => 'Startups, Micro e Pequenas Empresas',
            'ai_nicho' => 'Biotecnologia, Saúde e Tecnologia',
            'max_budget' => 1500000.00,
            'status' => 'active'
        ]);

        // Edital com nicho e público não aderentes
        $this->editalIncompativel = Edital::create([
            'title' => 'Edital Mineração Pesada Industrial',
            'fonte' => 'ANM',
            'publico' => 'Grandes Siderúrgicas',
            'ai_nicho' => 'Metalurgia Pesada',
            'max_budget' => 0,
            'status' => 'active'
        ]);
    }

    /**
     * RF-26: Rotas de alerta exigem autenticação Sanctum.
     */
    public function test_rotas_de_alertas_sem_token_retornam_401(): void
    {
        $this->getJson('/api/alertas/match')->assertStatus(401);
        $this->postJson('/api/alertas/verificar')->assertStatus(401);
        $this->getJson('/api/alertas/contador')->assertStatus(401);
        $this->patchJson('/api/alertas/1/lido')->assertStatus(401);
    }

    /**
     * RF-26: Verificação de match gera alertas para editais com compatibilidade >= 70%.
     */
    public function test_verificar_e_gerar_alertas_cria_registros_para_alto_match(): void
    {
        $response = $this->actingAs($this->usuario, 'sanctum')
            ->postJson('/api/alertas/verificar', [
                'startup_id' => $this->startup->id
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'sucesso' => true,
                'total_gerados' => 1
            ])
            ->assertJsonStructure([
                'dados' => [
                    '*' => [
                        'id',
                        'score_match',
                        'titulo',
                        'mensagem',
                        'criterios',
                        'lido',
                        'edital'
                    ]
                ]
            ]);

        $this->assertDatabaseHas('alerta_matches', [
            'user_id' => $this->usuario->id,
            'startup_id' => $this->startup->id,
            'edital_id' => $this->editalCompativel->id,
            'lido' => false
        ]);
    }

    /**
     * RF-26: A geração de alertas é idempotente (não duplica alertas para o mesmo par edital/startup).
     */
    public function test_verificar_e_gerar_alertas_e_idempotente(): void
    {
        // 1ª execução
        $this->actingAs($this->usuario, 'sanctum')
            ->postJson('/api/alertas/verificar', ['startup_id' => $this->startup->id]);

        $this->assertEquals(1, AlertaMatch::count());

        // 2ª execução
        $this->actingAs($this->usuario, 'sanctum')
            ->postJson('/api/alertas/verificar', ['startup_id' => $this->startup->id]);

        // Mantém 1 registro, sem duplicatas
        $this->assertEquals(1, AlertaMatch::count());
    }

    /**
     * RF-26: Listagem de alertas e contador de não lidos.
     */
    public function test_listar_alertas_e_contador(): void
    {
        AlertaMatch::create([
            'user_id' => $this->usuario->id,
            'startup_id' => $this->startup->id,
            'edital_id' => $this->editalCompativel->id,
            'score_match' => 95,
            'titulo' => '⚡ 95% Match Detectado',
            'mensagem' => 'Edital altamente recomendado.',
            'lido' => false
        ]);

        // Contador
        $respContador = $this->actingAs($this->usuario, 'sanctum')
            ->getJson("/api/alertas/contador?startup_id={$this->startup->id}");

        $respContador->assertStatus(200)
            ->assertJson([
                'sucesso' => true,
                'total_nao_lidos' => 1
            ]);

        // Listagem
        $respListagem = $this->actingAs($this->usuario, 'sanctum')
            ->getJson("/api/alertas/match?startup_id={$this->startup->id}&apenas_nao_lidos=1");

        $respListagem->assertStatus(200)
            ->assertJson([
                'sucesso' => true,
                'total_nao_lidos' => 1
            ]);

        $dados = $respListagem->json('dados');
        $this->assertCount(1, $dados);
        $this->assertEquals(95, $dados[0]['score_match']);
    }

    /**
     * RF-26: Marcar alerta como lido atualiza o banco e reduz o contador.
     */
    public function test_marcar_alerta_como_lido(): void
    {
        $alerta = AlertaMatch::create([
            'user_id' => $this->usuario->id,
            'startup_id' => $this->startup->id,
            'edital_id' => $this->editalCompativel->id,
            'score_match' => 85,
            'titulo' => 'Alerta Teste',
            'mensagem' => 'Mensagem teste',
            'lido' => false
        ]);

        $response = $this->actingAs($this->usuario, 'sanctum')
            ->patchJson("/api/alertas/{$alerta->id}/lido");

        $response->assertStatus(200)
            ->assertJson([
                'sucesso' => true,
                'mensagem' => 'Alerta marcado como lido.'
            ]);

        $this->assertTrue($alerta->fresh()->lido);

        // Contador deve ir a zero
        $respContador = $this->actingAs($this->usuario, 'sanctum')
            ->getJson("/api/alertas/contador?startup_id={$this->startup->id}");

        $respContador->assertJson(['total_nao_lidos' => 0]);
    }

    /**
     * RF-26: Marcar todos os alertas como lidos em lote.
     */
    public function test_marcar_todos_como_lidos(): void
    {
        AlertaMatch::create([
            'user_id' => $this->usuario->id,
            'startup_id' => $this->startup->id,
            'edital_id' => $this->editalCompativel->id,
            'score_match' => 85,
            'titulo' => 'Alerta 1',
            'mensagem' => 'Msg 1',
            'lido' => false
        ]);

        AlertaMatch::create([
            'user_id' => $this->usuario->id,
            'startup_id' => $this->startup->id,
            'edital_id' => $this->editalIncompativel->id,
            'score_match' => 75,
            'titulo' => 'Alerta 2',
            'mensagem' => 'Msg 2',
            'lido' => false
        ]);

        $response = $this->actingAs($this->usuario, 'sanctum')
            ->postJson('/api/alertas/marcar-todos-lidos', [
                'startup_id' => $this->startup->id
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'sucesso' => true,
                'total_afetados' => 2
            ]);

        $this->assertEquals(0, AlertaMatch::where('lido', false)->count());
    }
}
