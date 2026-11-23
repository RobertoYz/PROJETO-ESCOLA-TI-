<?php

namespace Tests\Feature;

use App\Models\Agencia;
use App\Models\Plano;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StartupQuotaAndPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Plano $planoStandard;
    private Plano $planoPro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->planoStandard = Plano::create([
            'nome' => 'Standard',
            'preco' => 49.90,
            'limite_startups' => 2,
            'limite_membros_equipe' => 1,
            'limite_minutas_ia' => 3,
        ]);

        $this->planoPro = Plano::create([
            'nome' => 'Pro',
            'preco' => 99.90,
            'limite_startups' => 10,
            'limite_membros_equipe' => 5,
            'limite_minutas_ia' => 15,
        ]);
    }

    public function test_permite_criar_startups_dentro_do_limite_e_bloqueia_com_403_ao_atingir_teto_do_plano(): void
    {
        $agencia = Agencia::create([
            'nome' => 'Consultoria Standard Limit',
            'plano_id' => $this->planoStandard->id,
            'status_pagamento' => 'ativo',
            'assinatura_expira_em' => now()->addDays(20),
        ]);

        $admin = User::create([
            'name' => 'Consultor Titular',
            'email' => 'titular@consultoria.com',
            'password' => bcrypt('Senha@12345'),
            'agencia_id' => $agencia->id,
            'perfil_acesso' => 'consultor_admin',
        ]);

        $agencia->owner_id = $admin->id;
        $agencia->save();

        // 1ª Startup (1/2) -> Permitida (201)
        $res1 = $this->actingAs($admin, 'sanctum')->postJson('/api/startups', [
            'cnpj' => '11.111.111/0001-11',
            'razao_social' => 'Startup Um Tecnologia Ltda',
            'nome_fantasia' => 'Startup 01',
        ]);
        $res1->assertStatus(201)
            ->assertJsonPath('cota.startups_atual', 1)
            ->assertJsonPath('cota.startups_limite', 2)
            ->assertJsonPath('cota.pode_criar_startup', true);

        // 2ª Startup (2/2) -> Permitida (201), mas atinge o teto (pode_criar_startup = false)
        $res2 = $this->actingAs($admin, 'sanctum')->postJson('/api/startups', [
            'cnpj' => '22.222.222/0001-22',
            'razao_social' => 'Startup Dois Inovacao Ltda',
            'nome_fantasia' => 'Startup 02',
        ]);
        $res2->assertStatus(201)
            ->assertJsonPath('cota.startups_atual', 2)
            ->assertJsonPath('cota.startups_limite', 2)
            ->assertJsonPath('cota.pode_criar_startup', false);

        // 3ª Startup (excedente) -> Bloqueada com 403 LIMITE_STARTUPS_ATINGIDO
        $res3 = $this->actingAs($admin, 'sanctum')->postJson('/api/startups', [
            'cnpj' => '33.333.333/0001-33',
            'razao_social' => 'Startup Tres Excedente Ltda',
            'nome_fantasia' => 'Startup 03',
        ]);
        $res3->assertStatus(403)
            ->assertJson([
                'code' => 'LIMITE_STARTUPS_ATINGIDO',
                'codigo' => 'LIMITE_STARTUPS_ATINGIDO',
                'atual' => 2,
                'limite' => 2,
                'plano_atual' => 'Standard',
            ]);
    }

    public function test_exclusao_soft_delete_libera_vaga_imediatamente_para_nova_startup(): void
    {
        $agencia = Agencia::create([
            'nome' => 'Consultoria Soft Delete',
            'plano_id' => $this->planoStandard->id,
            'status_pagamento' => 'ativo',
            'assinatura_expira_em' => now()->addDays(20),
        ]);

        $admin = User::create([
            'name' => 'Admin Exclusao',
            'email' => 'admin.exclusao@consultoria.com',
            'password' => bcrypt('Senha@12345'),
            'agencia_id' => $agencia->id,
            'perfil_acesso' => 'consultor_admin',
        ]);

        $agencia->owner_id = $admin->id;
        $agencia->save();

        $s1 = Startup::create([
            'agencia_id' => $agencia->id,
            'cnpj' => '11.222.333/0001-44',
            'razao_social' => 'Startup Alpha Ltda',
            'nome_fantasia' => 'Alpha',
        ]);

        $s2 = Startup::create([
            'agencia_id' => $agencia->id,
            'cnpj' => '55.666.777/0001-88',
            'razao_social' => 'Startup Beta Ltda',
            'nome_fantasia' => 'Beta',
        ]);

        // Exclui a 1ª startup via DELETE /api/startups/{id}
        $resDel = $this->actingAs($admin, 'sanctum')->deleteJson("/api/startups/{$s1->id}");
        $resDel->assertStatus(200)
            ->assertJsonPath('sucesso', true)
            ->assertJsonPath('cota.startups_atual', 1)
            ->assertJsonPath('cota.startups_limite', 2)
            ->assertJsonPath('cota.pode_criar_startup', true);

        $this->assertSoftDeleted('startups', ['id' => $s1->id]);

        // Agora consegue criar uma nova startup na vaga liberada
        $resCreate = $this->actingAs($admin, 'sanctum')->postJson('/api/startups', [
            'cnpj' => '99.888.777/0001-66',
            'razao_social' => 'Startup Gamma Ltda',
            'nome_fantasia' => 'Gamma',
        ]);
        $resCreate->assertStatus(201)
            ->assertJsonPath('cota.startups_atual', 2)
            ->assertJsonPath('cota.pode_criar_startup', false);
    }

    public function test_bloqueia_exclusao_anti_idor_de_outra_agencia_e_bloqueia_consultor_padrao_nao_titular(): void
    {
        $agenciaA = Agencia::create([
            'nome' => 'Agencia A',
            'plano_id' => $this->planoPro->id,
            'status_pagamento' => 'ativo',
        ]);

        $titularA = User::create([
            'name' => 'Titular A',
            'email' => 'titular.a@agencia.com',
            'password' => bcrypt('Senha@12345'),
            'agencia_id' => $agenciaA->id,
            'perfil_acesso' => 'consultor_admin',
        ]);
        $agenciaA->owner_id = $titularA->id;
        $agenciaA->save();

        $membroPadraoA = User::create([
            'name' => 'Consultor Padrao A',
            'email' => 'padrao.a@agencia.com',
            'password' => bcrypt('Senha@12345'),
            'agencia_id' => $agenciaA->id,
            'perfil_acesso' => 'consultor_padrao',
        ]);

        $startupA = Startup::create([
            'agencia_id' => $agenciaA->id,
            'cnpj' => '12.345.678/0001-99',
            'razao_social' => 'Startup Protegida A Ltda',
        ]);

        $agenciaB = Agencia::create([
            'nome' => 'Agencia B Concorrente',
            'plano_id' => $this->planoPro->id,
            'status_pagamento' => 'ativo',
        ]);

        $adminB = User::create([
            'name' => 'Admin B',
            'email' => 'admin.b@agencia.com',
            'password' => bcrypt('Senha@12345'),
            'agencia_id' => $agenciaB->id,
            'perfil_acesso' => 'consultor_admin',
        ]);

        // 1. Tentativa Anti-IDOR: Admin da Agência B tentando excluir startup da Agência A -> 403
        $resIdor = $this->actingAs($adminB, 'sanctum')->deleteJson("/api/startups/{$startupA->id}");
        $resIdor->assertStatus(403);

        // 2. Tentativa RBAC: Consultor padrão (não titular) da própria Agência A tentando excluir -> 403
        $resRbac = $this->actingAs($membroPadraoA, 'sanctum')->deleteJson("/api/startups/{$startupA->id}");
        $resRbac->assertStatus(403);

        $this->assertDatabaseHas('startups', [
            'id' => $startupA->id,
            'deleted_at' => null,
        ]);
    }

    public function test_get_startups_retorna_estrutura_de_cota_e_flag_pode_excluir(): void
    {
        $agencia = Agencia::create([
            'nome' => 'Consultoria Listagem Cota',
            'plano_id' => $this->planoStandard->id,
            'status_pagamento' => 'ativo',
        ]);

        $admin = User::create([
            'name' => 'Admin Listagem',
            'email' => 'admin.listagem@consultoria.com',
            'password' => bcrypt('Senha@12345'),
            'agencia_id' => $agencia->id,
            'perfil_acesso' => 'consultor_admin',
        ]);
        $agencia->owner_id = $admin->id;
        $agencia->save();

        Startup::create([
            'agencia_id' => $agencia->id,
            'cnpj' => '77.888.999/0001-00',
            'razao_social' => 'Startup Listada Ltda',
            'nome_fantasia' => 'Startup Listada',
        ]);

        // Formato padrão (array de startups com metadados de cota embutidos)
        $resPadrao = $this->actingAs($admin, 'sanctum')->getJson('/api/startups');
        $resPadrao->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.cota.startups_atual', 1)
            ->assertJsonPath('0.cota.startups_limite', 2)
            ->assertJsonPath('0.cota.pode_criar_startup', true)
            ->assertJsonPath('0.cota.plano_nome', 'Standard')
            ->assertJsonPath('0.pode_excluir_startup', true);

        // Formato com envelope de cota (?incluir_cota=1)
        $resEnvelope = $this->actingAs($admin, 'sanctum')->getJson('/api/startups?incluir_cota=1');
        $resEnvelope->assertStatus(200)
            ->assertJsonPath('cota.startups_atual', 1)
            ->assertJsonPath('cota.startups_limite', 2)
            ->assertJsonPath('cota.pode_criar_startup', true)
            ->assertJsonPath('cota.plano_nome', 'Standard')
            ->assertJsonPath('pode_excluir_startup', true);
    }
}
