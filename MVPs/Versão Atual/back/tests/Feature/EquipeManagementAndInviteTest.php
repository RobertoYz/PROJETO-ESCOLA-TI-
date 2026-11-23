<?php

namespace Tests\Feature;

use App\Enums\Permissao;
use App\Models\Agencia;
use App\Models\Edital;
use App\Models\KanbanBoard;
use App\Models\KanbanColumn;
use App\Models\Plano;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class EquipeManagementAndInviteTest extends TestCase
{
    use RefreshDatabase;

    private Plano $planoPro;
    private Agencia $agencia;
    private User $titular;

    protected function setUp(): void
    {
        parent::setUp();

        $this->planoPro = Plano::create([
            'nome' => 'Pro',
            'preco' => 99.90,
            'limite_startups' => 10,
            'limite_membros_equipe' => 5,
            'limite_minutas_ia' => 15,
        ]);

        $this->agencia = Agencia::create([
            'nome' => 'Consultoria Anjo Nexus B2B',
            'plano_id' => $this->planoPro->id,
            'status_pagamento' => 'ativo',
            'assinatura_expira_em' => now()->addDays(25),
        ]);

        $this->titular = User::create([
            'name' => 'Roberto Titular',
            'email' => 'roberto.titular@anjonexus.com.br',
            'password' => Hash::make('SenhaTitular@123'),
            'agencia_id' => $this->agencia->id,
            'perfil_acesso' => 'consultor_admin',
            'status' => 'ativo',
        ]);

        $this->agencia->owner_id = $this->titular->id;
        $this->agencia->save();
    }

    public function test_1_titular_cria_convite_gerando_status_pendente_token_e_pin_6_digitos_com_hash_no_banco(): void
    {
        Mail::fake();

        $res = $this->actingAs($this->titular, 'sanctum')->postJson('/api/equipe', [
            'name' => 'Juliana Consultora Plena',
            'email' => 'juliana@anjonexus.com.br',
            'permissoes' => [
                Permissao::STARTUPS_CRIAR_EDITAR->value,
                Permissao::KANBAN_GERENCIAR->value,
                Permissao::MINUTAS_GERENCIAR->value,
            ],
        ]);

        $res->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'membro' => ['id', 'name', 'email', 'status', 'permissoes'],
                'link_convite',
                'codigo_ativacao',
            ])
            ->assertJsonPath('membro.status', 'pendente')
            ->assertJsonPath('membro.is_owner', false);

        $pinRetornado = $res->json('codigo_ativacao');
        $linkRetornado = $res->json('link_convite');

        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $pinRetornado);
        $this->assertStringContainsString('aceitar-convite.html?token=', (string) $linkRetornado);

        $convidado = User::where('email', 'juliana@anjonexus.com.br')->firstOrFail();
        $this->assertEquals('pendente', $convidado->status);
        $this->assertNull($convidado->email_verified_at);
        $this->assertNotNull($convidado->convite_token);
        $this->assertEquals(64, strlen($convidado->convite_token));
        $this->assertNotNull($convidado->convite_codigo_hash);
        $this->assertNotEquals($pinRetornado, $convidado->convite_codigo_hash);
        $this->assertTrue(Hash::check($pinRetornado, $convidado->convite_codigo_hash));
        $this->assertEquals($this->titular->id, $convidado->convidado_por_id);
    }

    public function test_2_bloqueia_login_com_403_para_usuario_pendente_e_inativo(): void
    {
        User::create([
            'name' => 'Membro Pendente',
            'email' => 'pendente@anjonexus.com.br',
            'password' => Hash::make('QualquerSenha@123'),
            'agencia_id' => $this->agencia->id,
            'perfil_acesso' => 'consultor_padrao',
            'status' => 'pendente',
        ]);

        User::create([
            'name' => 'Membro Inativo',
            'email' => 'inativo@anjonexus.com.br',
            'password' => Hash::make('SenhaCorreta@123'),
            'agencia_id' => $this->agencia->id,
            'perfil_acesso' => 'consultor_padrao',
            'status' => 'inativo',
        ]);

        $resPendente = $this->postJson('/api/auth/login', [
            'email' => 'pendente@anjonexus.com.br',
            'password' => 'QualquerSenha@123',
        ]);
        $resPendente->assertStatus(403)
            ->assertJsonPath('codigo', 'CONVITE_PENDENTE');
        $this->assertStringContainsString('Convite pendente de ativação', $resPendente->json('message'));

        $resInativo = $this->postJson('/api/auth/login', [
            'email' => 'inativo@anjonexus.com.br',
            'password' => 'SenhaCorreta@123',
        ]);
        $resInativo->assertStatus(403)
            ->assertJsonPath('codigo', 'ACESSO_INATIVO');
        $this->assertStringContainsString('Acesso desativado pela consultoria', $resInativo->json('message'));
    }

    public function test_3_ativacao_convite_2_canais_erro_422_pin_incorreto_bloqueio_5_tentativas_e_sucesso_200(): void
    {
        Mail::fake();

        $resConvite = $this->actingAs($this->titular, 'sanctum')->postJson('/api/equipe', [
            'name' => 'Carlos Convidado',
            'email' => 'carlos.convidado@anjonexus.com.br',
            'permissoes' => [Permissao::STARTUPS_CRIAR_EDITAR->value],
        ]);
        $resConvite->assertStatus(201);

        $pinCorreto = $resConvite->json('codigo_ativacao');
        $convidado = User::where('email', 'carlos.convidado@anjonexus.com.br')->firstOrFail();
        $token = $convidado->convite_token;

        // Consulta pública do convite (mascara o e-mail e não expõe dados sensíveis)
        $resPublico = $this->getJson("/api/convites/{$token}");
        $resPublico->assertStatus(200)
            ->assertJsonPath('valido', true)
            ->assertJsonPath('agencia_nome', 'Consultoria Anjo Nexus B2B')
            ->assertJsonPath('convidado_nome', 'Carlos Convidado');

        $pinErrado = $pinCorreto === '999999' ? '111111' : '999999';

        // 1ª tentativa incorreta -> 422 e incrementa convite_tentativas = 1
        $resErro1 = $this->postJson('/api/convites/aceitar', [
            'token' => $token,
            'codigo' => $pinErrado,
            'password' => 'NovaSenha@2026',
            'password_confirmation' => 'NovaSenha@2026',
        ]);
        $resErro1->assertStatus(422);
        $this->assertEquals(1, $convidado->fresh()->convite_tentativas);

        // Ativação com PIN correto -> 200, status = ativo e email_verified_at preenchido
        $resSucesso = $this->postJson('/api/convites/aceitar', [
            'token' => $token,
            'codigo' => $pinCorreto,
            'password' => 'NovaSenha@2026',
            'password_confirmation' => 'NovaSenha@2026',
        ]);
        $resSucesso->assertStatus(200)
            ->assertJsonPath('usuario.status', 'ativo');

        $convidado->refresh();
        $this->assertEquals('ativo', $convidado->status);
        $this->assertNotNull($convidado->email_verified_at);
        $this->assertNull($convidado->convite_token);
        $this->assertNull($convidado->convite_codigo_hash);
        $this->assertTrue(Hash::check('NovaSenha@2026', $convidado->password));

        // Testa bloqueio após 5 falhas consecutivas em um segundo convite
        $resConvite2 = $this->actingAs($this->titular, 'sanctum')->postJson('/api/equipe', [
            'name' => 'Tentativas BruteForce',
            'email' => 'bruteforce@anjonexus.com.br',
            'permissoes' => [],
        ]);
        $pinCorreto2 = $resConvite2->json('codigo_ativacao');
        $convidado2 = User::where('email', 'bruteforce@anjonexus.com.br')->firstOrFail();
        $token2 = $convidado2->convite_token;
        $pinErrado2 = $pinCorreto2 === '888888' ? '123456' : '888888';

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/convites/aceitar', [
                'token' => $token2,
                'codigo' => $pinErrado2,
                'password' => 'SenhaTeste@2026',
                'password_confirmation' => 'SenhaTeste@2026',
            ])->assertStatus(422);
        }

        $convidado2->refresh();
        $this->assertEquals(5, $convidado2->convite_tentativas);
        $this->assertNull($convidado2->convite_token);

        // Mesmo enviando o PIN correto na 6ª tentativa, o convite já foi invalidado (422)
        $this->postJson('/api/convites/aceitar', [
            'token' => $token2,
            'codigo' => $pinCorreto2,
            'password' => 'SenhaTeste@2026',
            'password_confirmation' => 'SenhaTeste@2026',
        ])->assertStatus(422);
    }

    public function test_4_proibicao_de_delegacao_transitiva_e_protecao_do_titular_e_outros_gerentes(): void
    {
        Mail::fake();

        $gerenteA = User::create([
            'name' => 'Gerente Operacional A',
            'email' => 'gerente.a@anjonexus.com.br',
            'password' => Hash::make('SenhaGerente@123'),
            'agencia_id' => $this->agencia->id,
            'perfil_acesso' => 'consultor_padrao',
            'status' => 'ativo',
            'permissoes' => [
                Permissao::STARTUPS_CRIAR_EDITAR->value,
                Permissao::STARTUPS_EXCLUIR->value,
                Permissao::KANBAN_GERENCIAR->value,
                Permissao::MINUTAS_GERENCIAR->value,
                Permissao::EQUIPE_GERENCIAR->value,
            ],
        ]);

        $gerenteB = User::create([
            'name' => 'Gerente Operacional B',
            'email' => 'gerente.b@anjonexus.com.br',
            'password' => Hash::make('SenhaGerente@123'),
            'agencia_id' => $this->agencia->id,
            'perfil_acesso' => 'consultor_padrao',
            'status' => 'ativo',
            'permissoes' => [
                Permissao::STARTUPS_CRIAR_EDITAR->value,
                Permissao::EQUIPE_GERENCIAR->value,
            ],
        ]);

        // 1. Gerente A consegue convidar um consultor comum (sem equipe.gerenciar) -> 201
        $resComum = $this->actingAs($gerenteA, 'sanctum')->postJson('/api/equipe', [
            'name' => 'Consultor Comum',
            'email' => 'comum@anjonexus.com.br',
            'permissoes' => [
                Permissao::STARTUPS_CRIAR_EDITAR->value,
                Permissao::KANBAN_GERENCIAR->value,
            ],
        ]);
        $resComum->assertStatus(201);
        $idConsultorComum = $resComum->json('membro.id');

        // 2. Gerente A tenta convidar alguém concedendo equipe.gerenciar -> 403
        $this->actingAs($gerenteA, 'sanctum')->postJson('/api/equipe', [
            'name' => 'Tentativa SubGerente',
            'email' => 'subgerente@anjonexus.com.br',
            'permissoes' => [Permissao::EQUIPE_GERENCIAR->value],
        ])->assertStatus(403);

        // 3. Gerente A tenta conceder assinatura.gerenciar -> 403
        $this->actingAs($gerenteA, 'sanctum')->postJson('/api/equipe', [
            'name' => 'Tentativa Financeiro',
            'email' => 'financeiro@anjonexus.com.br',
            'permissoes' => [Permissao::ASSINATURA_GERENCIAR->value],
        ])->assertStatus(403);

        // 4. Gerente A tenta promover o Consultor Comum para equipe.gerenciar -> 403
        $this->actingAs($gerenteA, 'sanctum')->putJson("/api/equipe/{$idConsultorComum}/permissoes", [
            'permissoes' => [Permissao::EQUIPE_GERENCIAR->value],
        ])->assertStatus(403);

        // 5. Gerente A tenta editar/remover o Titular (owner_id) -> 403
        $this->actingAs($gerenteA, 'sanctum')->putJson("/api/equipe/{$this->titular->id}/permissoes", [
            'permissoes' => [],
        ])->assertStatus(403);
        $this->actingAs($gerenteA, 'sanctum')->deleteJson("/api/equipe/{$this->titular->id}")
            ->assertStatus(403);

        // 6. Gerente A tenta editar/desativar/remover o Gerente B (que possui equipe.gerenciar) -> 403
        $this->actingAs($gerenteA, 'sanctum')->putJson("/api/equipe/{$gerenteB->id}/permissoes", [
            'permissoes' => [],
        ])->assertStatus(403);
        $this->actingAs($gerenteA, 'sanctum')->patchJson("/api/equipe/{$gerenteB->id}/status", [
            'status' => 'inativo',
        ])->assertStatus(403);
        $this->actingAs($gerenteA, 'sanctum')->deleteJson("/api/equipe/{$gerenteB->id}")
            ->assertStatus(403);
    }

    public function test_5_anti_idor_bloqueia_acesso_a_membro_de_outra_agencia(): void
    {
        $outraAgencia = Agencia::create([
            'nome' => 'Consultoria Concorrente',
            'plano_id' => $this->planoPro->id,
            'status_pagamento' => 'ativo',
        ]);

        $membroOutraAgencia = User::create([
            'name' => 'Consultor Outra Agencia',
            'email' => 'outro@concorrente.com.br',
            'password' => Hash::make('Senha@12345'),
            'agencia_id' => $outraAgencia->id,
            'perfil_acesso' => 'consultor_padrao',
            'status' => 'ativo',
            'permissoes' => [],
        ]);

        $this->actingAs($this->titular, 'sanctum')
            ->putJson("/api/equipe/{$membroOutraAgencia->id}/permissoes", [
                'permissoes' => [Permissao::STARTUPS_CRIAR_EDITAR->value],
            ])
            ->assertStatus(403);

        $this->actingAs($this->titular, 'sanctum')
            ->patchJson("/api/equipe/{$membroOutraAgencia->id}/status", [
                'status' => 'inativo',
            ])
            ->assertStatus(403);

        $this->actingAs($this->titular, 'sanctum')
            ->deleteJson("/api/equipe/{$membroOutraAgencia->id}")
            ->assertStatus(403);
    }

    public function test_6_enforcement_real_de_permissoes_em_startups_kanban_minutas_e_assinatura(): void
    {
        $startup = Startup::create([
            'agencia_id' => $this->agencia->id,
            'cnpj' => '11.222.333/0001-44',
            'razao_social' => 'Startup Teste RBAC Ltda',
            'nome_fantasia' => 'Startup RBAC',
        ]);

        $edital = Edital::create([
            'title' => 'Edital FINEP RBAC 2026',
            'fonte' => 'FINEP',
            'status' => 'active',
        ]);

        $board = KanbanBoard::create([
            'agencia_id' => $this->agencia->id,
            'title' => 'Board RBAC',
        ]);
        $column = KanbanColumn::create([
            'board_id' => $board->id,
            'title' => 'Backlog',
            'slug' => 'backlog',
            'position' => 1000.0,
        ]);

        $visualizador = User::create([
            'name' => 'Consultor Visualizador',
            'email' => 'visualizador@anjonexus.com.br',
            'password' => Hash::make('SenhaVis@123'),
            'agencia_id' => $this->agencia->id,
            'perfil_acesso' => 'consultor_padrao',
            'status' => 'ativo',
            'permissoes' => [],
        ]);

        $consultorComExcluir = User::create([
            'name' => 'Consultor Com Excluir',
            'email' => 'excluir@anjonexus.com.br',
            'password' => Hash::make('SenhaExc@123'),
            'agencia_id' => $this->agencia->id,
            'perfil_acesso' => 'consultor_padrao',
            'status' => 'ativo',
            'permissoes' => [Permissao::STARTUPS_EXCLUIR->value],
        ]);

        // Visualizador bloqueado em escrita de Startups, Kanban, Minutas e Assinatura
        $this->actingAs($visualizador, 'sanctum')
            ->postJson('/api/startups', [
                'cnpj' => '44.555.666/0001-77',
                'razao_social' => 'Nova Startup Bloqueada',
            ])
            ->assertStatus(403);

        $this->actingAs($visualizador, 'sanctum')
            ->putJson("/api/startups/{$startup->id}", [
                'razao_social' => 'Tentativa Edicao',
            ])
            ->assertStatus(403);

        $this->actingAs($visualizador, 'sanctum')
            ->deleteJson("/api/startups/{$startup->id}")
            ->assertStatus(403);

        $this->actingAs($visualizador, 'sanctum')
            ->postJson('/api/kanban/cards', [
                'board_id' => $board->id,
                'column_id' => $column->id,
                'title' => 'Card Bloqueado',
            ])
            ->assertStatus(403);

        $this->actingAs($visualizador, 'sanctum')
            ->postJson('/api/minutas/gerar', [
                'startup_id' => $startup->id,
                'edital_id' => $edital->id,
                'ideia_projeto' => 'Ideia de projeto com mais de 15 caracteres para teste.',
            ])
            ->assertStatus(403);

        // Qualquer consultor secundário (mesmo com permissões operacionais) recebe 403 em Assinatura/Faturamento
        $this->actingAs($consultorComExcluir, 'sanctum')
            ->getJson('/api/assinatura/atual')
            ->assertStatus(403);

        $this->actingAs($consultorComExcluir, 'sanctum')
            ->postJson('/api/assinatura/renovar')
            ->assertStatus(403);

        $this->actingAs($consultorComExcluir, 'sanctum')
            ->postJson('/api/assinatura/atualizar-forma-pagamento', ['forma_pagamento' => 'CARD'])
            ->assertStatus(403);

        // Consultor com startups.excluir consegue excluir a startup
        $this->actingAs($consultorComExcluir, 'sanctum')
            ->deleteJson("/api/startups/{$startup->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('startups', ['id' => $startup->id]);
    }

    public function test_7_revogacao_imediata_de_sessao_sanctum_ao_inativar_ou_remover_consultor_preservando_dados(): void
    {
        $consultor = User::create([
            'name' => 'Consultor Sessao Ativa',
            'email' => 'sessao@anjonexus.com.br',
            'password' => Hash::make('SenhaSessao@123'),
            'agencia_id' => $this->agencia->id,
            'perfil_acesso' => 'consultor_padrao',
            'status' => 'ativo',
            'permissoes' => [Permissao::STARTUPS_CRIAR_EDITAR->value],
        ]);

        $startupDaAgencia = Startup::create([
            'agencia_id' => $this->agencia->id,
            'cnpj' => '88.777.666/0001-55',
            'razao_social' => 'Startup Acervo Preservado Ltda',
            'nome_fantasia' => 'Acervo Preservado',
        ]);

        $plainToken = $consultor->createToken('auth_token')->plainTextToken;
        $this->assertEquals(1, PersonalAccessToken::where('tokenable_id', $consultor->id)->count());

        // Titular inativa o consultor
        $this->actingAs($this->titular, 'sanctum')
            ->patchJson("/api/equipe/{$consultor->id}/status", ['status' => 'inativo'])
            ->assertStatus(200)
            ->assertJsonPath('membro.status', 'inativo');

        // Tokens Sanctum foram apagados imediatamente do banco
        $this->assertEquals(0, PersonalAccessToken::where('tokenable_id', $consultor->id)->count());

        // Requisição subsequente com o Bearer token antigo retorna 401 Unauthenticated
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $plainToken)
            ->getJson('/api/me')
            ->assertStatus(401);

        // Reativa, emite novo token e remove definitivamente o consultor
        $this->actingAs($this->titular, 'sanctum')
            ->patchJson("/api/equipe/{$consultor->id}/status", ['status' => 'ativo'])
            ->assertStatus(200);

        $novoToken = $consultor->fresh()->createToken('auth_token')->plainTextToken;
        $this->assertEquals(1, PersonalAccessToken::where('tokenable_id', $consultor->id)->count());

        $this->actingAs($this->titular, 'sanctum')
            ->deleteJson("/api/equipe/{$consultor->id}")
            ->assertStatus(200);

        $this->assertEquals(0, PersonalAccessToken::where('tokenable_id', $consultor->id)->count());
        $this->assertDatabaseMissing('users', ['id' => $consultor->id]);

        // Dados operacionais da agência permanecem 100% intactos
        $this->assertDatabaseHas('startups', [
            'id' => $startupDaAgencia->id,
            'agencia_id' => $this->agencia->id,
            'deleted_at' => null,
        ]);
    }
}
