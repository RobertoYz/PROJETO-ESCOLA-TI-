<?php

namespace Tests\Feature;

use App\Models\Agencia;
use App\Models\Edital;
use App\Models\Plano;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminGlobalTest extends TestCase
{
    use RefreshDatabase;

    private User $adminGlobal;
    private User $usuarioComum;
    private Agencia $agencia;
    private Plano $plano;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plano = Plano::create([
            'nome' => 'Pro',
            'preco' => 99.90,
            'id_transacao' => 'prod_teste_pro',
            'limite_startups' => 10,
            'limite_membros_equipe' => 5,
            'limite_minutas_ia' => 15,
        ]);

        $this->agencia = Agencia::create([
            'nome' => 'Consultoria Alfa',
            'plano_id' => $this->plano->id,
            'forma_pagamento' => 'PIX',
            'status_pagamento' => 'ativo',
            'assinatura_expira_em' => now()->addDays(20),
        ]);

        $this->usuarioComum = User::create([
            'name' => 'Carlos Consultor',
            'email' => 'carlos@consultoriaalfa.com.br',
            'password' => bcrypt('Senha@123'),
            'agencia_id' => $this->agencia->id,
            'perfil_acesso' => 'consultor_admin',
            'role' => 'consultor_admin',
            'status' => 'ativo',
            'email_verified_at' => now(),
        ]);

        $this->agencia->update(['owner_id' => $this->usuarioComum->id]);

        $this->adminGlobal = User::create([
            'name' => 'Super Admin Nexus',
            'email' => 'admin@anjonexus.com.br',
            'password' => bcrypt('Admin@2026!'),
            'perfil_acesso' => 'admin_global',
            'role' => 'admin_global',
            'status' => 'ativo',
            'email_verified_at' => now(),
        ]);

        Edital::create([
            'title' => 'Chamada FAPDF Inovação 2026',
            'fonte' => 'FAPDF',
            'regiao' => 'Distrito Federal (DF)',
            'status' => 'active',
            'deadline' => now()->addMonth(),
        ]);

        Edital::create([
            'title' => 'Tecnova III Goiás 2026',
            'fonte' => 'FAPEG',
            'regiao' => 'Goiás (GO)',
            'status' => 'active',
            'deadline' => now()->addMonth(),
        ]);
    }

    public function test_usuario_nao_autenticado_recebe_401_nas_rotas_admin(): void
    {
        $this->getJson('/api/admin/dashboard')->assertUnauthorized();
        $this->getJson('/api/admin/agencias')->assertUnauthorized();
        $this->getJson('/api/admin/radares/status')->assertUnauthorized();
    }

    public function test_usuario_comum_ou_consultor_recebe_403_nas_rotas_admin(): void
    {
        $this->actingAs($this->usuarioComum)
            ->getJson('/api/admin/dashboard')
            ->assertForbidden()
            ->assertJsonPath('codigo_erro', 'ACESSO_NEGADO_ADMIN_GLOBAL');

        $this->actingAs($this->usuarioComum)
            ->getJson('/api/admin/agencias')
            ->assertForbidden()
            ->assertJsonPath('codigo_erro', 'ACESSO_NEGADO_ADMIN_GLOBAL');

        $this->actingAs($this->usuarioComum)
            ->patchJson("/api/admin/agencias/{$this->agencia->id}/status", ['status' => 'suspenso'])
            ->assertForbidden()
            ->assertJsonPath('codigo_erro', 'ACESSO_NEGADO_ADMIN_GLOBAL');

        $this->actingAs($this->usuarioComum)
            ->getJson('/api/admin/radares/status')
            ->assertForbidden()
            ->assertJsonPath('codigo_erro', 'ACESSO_NEGADO_ADMIN_GLOBAL');
    }

    public function test_admin_global_acessa_dashboard_com_metricas_completas(): void
    {
        $resposta = $this->actingAs($this->adminGlobal)
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('status', 'sucesso');

        $dados = $resposta->json('dados');

        $this->assertArrayHasKey('usuarios', $dados);
        $this->assertArrayHasKey('agencias', $dados);
        $this->assertArrayHasKey('planos', $dados);
        $this->assertArrayHasKey('financeiro', $dados);
        $this->assertArrayHasKey('editais', $dados);

        $this->assertGreaterThanOrEqual(2, $dados['usuarios']['total']);
        $this->assertEquals(1, $dados['agencias']['total']);
        $this->assertEquals(1, $dados['agencias']['status']['ativas']);
        $this->assertEquals(99.90, $dados['financeiro']['mrr_estimado']);
        $this->assertEquals(2, $dados['editais']['total']);
        $this->assertEquals(2, $dados['editais']['abertos']);
        $this->assertArrayHasKey('FAPDF', $dados['editais']['por_fonte']);
        $this->assertArrayHasKey('FAPEG', $dados['editais']['por_fonte']);
    }

    public function test_admin_global_lista_agencias_com_paginacao_e_busca(): void
    {
        $resposta = $this->actingAs($this->adminGlobal)
            ->getJson('/api/admin/agencias?busca=Alfa')
            ->assertOk()
            ->assertJsonPath('status', 'sucesso');

        $dados = $resposta->json('dados');
        $this->assertCount(1, $dados);
        $this->assertSame('Consultoria Alfa', $dados[0]['nome']);
        $this->assertSame('carlos@consultoriaalfa.com.br', $dados[0]['owner']['email']);
    }

    public function test_admin_global_obtem_detalhes_completos_de_uma_agencia(): void
    {
        $resposta = $this->actingAs($this->adminGlobal)
            ->getJson("/api/admin/agencias/{$this->agencia->id}")
            ->assertOk()
            ->assertJsonPath('status', 'sucesso');

        $agencia = $resposta->json('dados.agencia');
        $this->assertSame($this->agencia->nome, $agencia['nome']);
        $this->assertTrue($resposta->json('dados.is_ativa'));
    }

    public function test_admin_global_desativa_e_reativa_status_de_uma_agencia(): void
    {
        // 1. Suspender a agência
        $resposta = $this->actingAs($this->adminGlobal)
            ->patchJson("/api/admin/agencias/{$this->agencia->id}/status", [
                'status' => 'suspenso'
            ])
            ->assertOk()
            ->assertJsonPath('status', 'sucesso')
            ->assertJsonPath('dados.status_pagamento', 'suspenso');

        $this->assertSame('suspenso', $this->agencia->fresh()->status_pagamento);
        $this->assertFalse($this->agencia->fresh()->isPagamentoAtivo());

        // 2. Reativar a agência
        $this->actingAs($this->adminGlobal)
            ->patchJson("/api/admin/agencias/{$this->agencia->id}/status", [
                'status' => 'ativo'
            ])
            ->assertOk()
            ->assertJsonPath('dados.status_pagamento', 'ativo');

        $this->assertSame('ativo', $this->agencia->fresh()->status_pagamento);
        $this->assertTrue($this->agencia->fresh()->isPagamentoAtivo());
    }

    public function test_admin_global_ajusta_plano_e_concede_dias_extras(): void
    {
        $novoPlano = Plano::create([
            'nome' => 'Enterprise',
            'preco' => 299.90,
            'id_transacao' => 'prod_teste_ent',
            'limite_startups' => 50,
            'limite_membros_equipe' => 15,
            'limite_minutas_ia' => 50,
        ]);

        $this->actingAs($this->adminGlobal)
            ->postJson("/api/admin/agencias/{$this->agencia->id}/ajustar-plano", [
                'plano_id' => $novoPlano->id,
                'dias_extras' => 60,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'sucesso')
            ->assertJsonPath('dados.plano_id', $novoPlano->id);

        $agenciaAtualizada = $this->agencia->fresh();
        $this->assertSame($novoPlano->id, $agenciaAtualizada->plano_id);
        $this->assertTrue($agenciaAtualizada->assinatura_expira_em->gt(now()->addDays(50)));
    }

    public function test_admin_global_exclui_agencia_em_cascata(): void
    {
        $startup = Startup::create([
            'nome_fantasia' => 'Startup Teste Alfa',
            'razao_social' => 'Startup Teste Alfa LTDA',
            'cnpj' => '12.345.678/0001-90',
            'agencia_id' => $this->agencia->id,
            'status' => 'active',
        ]);

        $this->actingAs($this->adminGlobal)
            ->deleteJson("/api/admin/agencias/{$this->agencia->id}")
            ->assertOk()
            ->assertJsonPath('status', 'sucesso');

        $this->assertDatabaseMissing('agencias', ['id' => $this->agencia->id]);
        $this->assertDatabaseMissing('startups', ['id' => $startup->id]);
        $this->assertDatabaseMissing('users', ['id' => $this->usuarioComum->id]);
    }

    public function test_admin_global_visualiza_status_de_saude_dos_radares(): void
    {
        $resposta = $this->actingAs($this->adminGlobal)
            ->getJson('/api/admin/radares/status')
            ->assertOk()
            ->assertJsonPath('status', 'sucesso');

        $radares = $resposta->json('dados');
        $this->assertIsArray($radares);
        $this->assertGreaterThanOrEqual(6, count($radares));

        $fontes = array_column($radares, 'fonte');
        $this->assertContains('FAPDF', $fontes);
        $this->assertContains('FAPEG', $fontes);
        $this->assertContains('FINEP', $fontes);
        $this->assertContains('FAPESP', $fontes);

        $radarFapdf = collect($radares)->firstWhere('fonte', 'FAPDF');
        $this->assertSame('Distrito Federal (DF)', $radarFapdf['regiao']);
        $this->assertEquals(1, $radarFapdf['total_editais']);
        $this->assertEquals(1, $radarFapdf['editais_abertos']);
        $this->assertSame('Online', $radarFapdf['status_servidor']);
    }

    public function test_admin_global_dispara_sincronizacao_manual_de_radar(): void
    {
        $resposta = $this->actingAs($this->adminGlobal)
            ->postJson('/api/admin/radares/FAPDF/sincronizar')
            ->assertOk()
            ->assertJsonPath('status', 'sucesso')
            ->assertJsonPath('dados.fonte', 'FAPDF')
            ->assertJsonPath('dados.sucesso', true);

        $this->assertNotEmpty($resposta->json('dados.log_saida'));
    }

    public function test_admin_global_obtem_detalhes_completos_de_um_radar(): void
    {
        $resposta = $this->actingAs($this->adminGlobal)
            ->getJson('/api/admin/radares/FAPDF/detalhes')
            ->assertOk()
            ->assertJsonPath('status', 'sucesso')
            ->assertJsonPath('dados.fonte', 'FAPDF')
            ->assertJsonPath('dados.macro_regiao', 'Centro-Oeste');

        $dados = $resposta->json('dados');
        $this->assertArrayHasKey('orgao', $dados);
        $this->assertArrayHasKey('regiao', $dados);
        $this->assertArrayHasKey('url_portal', $dados);
        $this->assertArrayHasKey('status_servidor', $dados);
        $this->assertArrayHasKey('total_editais', $dados);
        $this->assertArrayHasKey('editais_abertos', $dados);
        $this->assertArrayHasKey('resiliencia', $dados);
        $this->assertArrayHasKey('ultimos_editais', $dados);
        $this->assertIsArray($dados['ultimos_editais']);
    }

    public function test_obter_detalhes_de_radar_inexistente_retorna_404(): void
    {
        $this->actingAs($this->adminGlobal)
            ->getJson('/api/admin/radares/FONTE_INEXISTENTE/detalhes')
            ->assertNotFound();
    }

    public function test_admin_global_sincroniza_radar_por_regiao(): void
    {
        $resposta = $this->actingAs($this->adminGlobal)
            ->postJson('/api/admin/radares/CENTRO-OESTE/sincronizar')
            ->assertOk()
            ->assertJsonPath('status', 'sucesso')
            ->assertJsonPath('dados.fonte', 'CENTRO-OESTE')
            ->assertJsonPath('dados.sucesso', true);

        $this->assertIsArray($resposta->json('dados.comandos'));
        $this->assertContains('fapeg:sync', $resposta->json('dados.comandos'));
        $this->assertContains('fapdf:sync', $resposta->json('dados.comandos'));
    }

    public function test_sincronizacao_com_fonte_invalida_retorna_422(): void
    {
        $this->actingAs($this->adminGlobal)
            ->postJson('/api/admin/radares/FONTE_INEXISTENTE/sincronizar')
            ->assertUnprocessable();
    }
}
