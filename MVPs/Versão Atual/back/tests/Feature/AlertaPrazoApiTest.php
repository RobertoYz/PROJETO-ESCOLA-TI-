<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Agencia;
use App\Models\Plano;
use App\Models\Startup;
use App\Models\Edital;
use App\Models\AlertaPrazo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AlertaPrazoApiTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private Startup $startup;
    private Edital $editalCritico;
    private Edital $editalUrgente;
    private Edital $editalAtencao;
    private Edital $editalLonge;

    protected function setUp(): void
    {
        parent::setUp();

        $plano = Plano::create([
            'nome' => 'Plano Prazos',
            'preco' => 297.00
        ]);

        $agencia = Agencia::create([
            'nome' => 'Agência Prazos',
            'plano_id' => $plano->id,
            'status_pagamento' => 'ativo',
            'pagamento_ativo' => true
        ]);

        $this->usuario = User::create([
            'name' => 'Consultor Prazos',
            'email' => 'consultor.prazos@inova.com.br',
            'password' => bcrypt('Senha@12345'),
            'agencia_id' => $agencia->id
        ]);

        $this->startup = Startup::create([
            'agencia_id' => $agencia->id,
            'cnpj' => '33.444.555/0001-66',
            'razao_social' => 'AgroTech Brasil Ltda',
            'nome_fantasia' => 'AgroTech',
            'nicho_atuacao' => 'Agronegócio',
            'porte' => 'EPP'
        ]);

        $agora = Carbon::now();

        // 1. Edital Crítico (<= 3 dias)
        $this->editalCritico = Edital::create([
            'title' => 'FINEP Mulheres Inovadoras - Fase Final',
            'fonte' => 'FINEP',
            'deadline' => (clone $agora)->addDays(2)->addHours(3),
            'status' => 'active'
        ]);

        // 2. Edital Urgente (4 a 7 dias)
        $this->editalUrgente = Edital::create([
            'title' => 'FAPESC Tecnova III - Submissão',
            'fonte' => 'FAPESC',
            'deadline' => (clone $agora)->addDays(6),
            'status' => 'active'
        ]);

        // 3. Edital Atenção (8 a 15 dias)
        $this->editalAtencao = Edital::create([
            'title' => 'Finep Mais Inovação Brasil',
            'fonte' => 'FINEP',
            'deadline' => (clone $agora)->addDays(12),
            'status' => 'active'
        ]);

        // 4. Edital com prazo distante (> 15 dias) - não deve gerar alerta no limite padrão
        $this->editalLonge = Edital::create([
            'title' => 'Edital Futuro 2027',
            'fonte' => 'CNPq',
            'deadline' => (clone $agora)->addDays(40),
            'status' => 'active'
        ]);
    }

    public function test_rotas_de_prazos_criticos_sem_token_retornam_401(): void
    {
        $this->getJson('/api/alertas/prazos-criticos')->assertStatus(401);
        $this->postJson('/api/alertas/prazos-criticos/verificar')->assertStatus(401);
        $this->getJson('/api/alertas/prazos-criticos/contador')->assertStatus(401);
        $this->patchJson('/api/alertas/prazos-criticos/1/lido')->assertStatus(401);
        $this->postJson('/api/alertas/prazos-criticos/marcar-todos-lidos')->assertStatus(401);
    }

    public function test_verificar_e_gerar_alertas_de_prazos_criticos_detecta_editais_com_urgencia_correta(): void
    {
        $response = $this->actingAs($this->usuario)
            ->postJson('/api/alertas/prazos-criticos/verificar', [
                'startup_id' => $this->startup->id,
                'dias_maximos' => 15
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('sucesso', true)
            ->assertJsonPath('total_gerados', 3); // 3 editais dentro de 15 dias

        // Confere registros no banco com níveis de urgência esperados
        $this->assertDatabaseHas('alerta_prazos', [
            'user_id' => $this->usuario->id,
            'edital_id' => $this->editalCritico->id,
            'nivel_urgencia' => 'critico',
            'lido' => false
        ]);

        $this->assertDatabaseHas('alerta_prazos', [
            'user_id' => $this->usuario->id,
            'edital_id' => $this->editalUrgente->id,
            'nivel_urgencia' => 'urgente',
            'lido' => false
        ]);

        $this->assertDatabaseHas('alerta_prazos', [
            'user_id' => $this->usuario->id,
            'edital_id' => $this->editalAtencao->id,
            'nivel_urgencia' => 'atencao',
            'lido' => false
        ]);

        // Edital distante não deve gerar registro
        $this->assertDatabaseMissing('alerta_prazos', [
            'user_id' => $this->usuario->id,
            'edital_id' => $this->editalLonge->id
        ]);
    }

    public function test_verificar_prazos_criticos_e_idempotente(): void
    {
        // 1ª execução
        $this->actingAs($this->usuario)
            ->postJson('/api/alertas/prazos-criticos/verificar', [
                'startup_id' => $this->startup->id
            ]);

        $totalAposPrimeira = AlertaPrazo::count();
        $this->assertEquals(3, $totalAposPrimeira);

        // 2ª execução não duplica registros
        $this->actingAs($this->usuario)
            ->postJson('/api/alertas/prazos-criticos/verificar', [
                'startup_id' => $this->startup->id
            ]);

        $this->assertEquals(3, AlertaPrazo::count());
    }

    public function test_listar_alertas_de_prazos_e_contador(): void
    {
        // Gera os alertas
        $this->actingAs($this->usuario)
            ->postJson('/api/alertas/prazos-criticos/verificar', [
                'startup_id' => $this->startup->id
            ]);

        // Verifica contador
        $resContador = $this->actingAs($this->usuario)
            ->getJson('/api/alertas/prazos-criticos/contador');

        $resContador->assertStatus(200)
            ->assertJsonPath('sucesso', true)
            ->assertJsonPath('total_nao_lidos', 3);

        // Listagem geral ordenada por criticidade
        $resListagem = $this->actingAs($this->usuario)
            ->getJson('/api/alertas/prazos-criticos');

        $resListagem->assertStatus(200)
            ->assertJsonPath('sucesso', true)
            ->assertJsonCount(3, 'dados');

        $dados = $resListagem->json('dados');
        // O primeiro deve ser o mais crítico
        $this->assertEquals('critico', $dados[0]['nivel_urgencia']);

        // Filtro por urgência
        $resCriticos = $this->actingAs($this->usuario)
            ->getJson('/api/alertas/prazos-criticos?nivel_urgencia=critico');

        $resCriticos->assertStatus(200)
            ->assertJsonCount(1, 'dados');
    }

    public function test_marcar_alerta_de_prazo_como_lido(): void
    {
        $this->actingAs($this->usuario)
            ->postJson('/api/alertas/prazos-criticos/verificar', [
                'startup_id' => $this->startup->id
            ]);

        $alerta = AlertaPrazo::where('user_id', $this->usuario->id)
            ->where('nivel_urgencia', 'critico')
            ->firstOrFail();

        $this->assertFalse($alerta->lido);

        // Marca como lido
        $res = $this->actingAs($this->usuario)
            ->patchJson("/api/alertas/prazos-criticos/{$alerta->id}/lido");

        $res->assertStatus(200)
            ->assertJsonPath('sucesso', true);

        $alerta->refresh();
        $this->assertTrue($alerta->lido);

        // Contador agora deve ser 2
        $resContador = $this->actingAs($this->usuario)
            ->getJson('/api/alertas/prazos-criticos/contador');
        $resContador->assertJsonPath('total_nao_lidos', 2);
    }

    public function test_marcar_todos_alertas_de_prazos_como_lidos(): void
    {
        $this->actingAs($this->usuario)
            ->postJson('/api/alertas/prazos-criticos/verificar', [
                'startup_id' => $this->startup->id
            ]);

        $res = $this->actingAs($this->usuario)
            ->postJson('/api/alertas/prazos-criticos/marcar-todos-lidos', [
                'startup_id' => $this->startup->id
            ]);

        $res->assertStatus(200)
            ->assertJsonPath('sucesso', true)
            ->assertJsonPath('total_atualizados', 3);

        $resContador = $this->actingAs($this->usuario)
            ->getJson('/api/alertas/prazos-criticos/contador');
        $resContador->assertJsonPath('total_nao_lidos', 0);
    }

    public function test_isolamento_de_alertas_entre_usuarios(): void
    {
        $this->actingAs($this->usuario)
            ->postJson('/api/alertas/prazos-criticos/verificar', [
                'startup_id' => $this->startup->id
            ]);

        // Outro usuário
        $outroUsuario = User::create([
            'name' => 'Outro Consultor',
            'email' => 'outro@inova.com.br',
            'password' => bcrypt('Senha@12345'),
            'agencia_id' => $this->usuario->agencia_id
        ]);

        $resOutro = $this->actingAs($outroUsuario)
            ->getJson('/api/alertas/prazos-criticos');

        $resOutro->assertStatus(200)
            ->assertJsonCount(0, 'dados');

        $resContadorOutro = $this->actingAs($outroUsuario)
            ->getJson('/api/alertas/prazos-criticos/contador');
        $resContadorOutro->assertJsonPath('total_nao_lidos', 0);
    }
}
