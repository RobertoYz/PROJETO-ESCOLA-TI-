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
use Illuminate\Support\Facades\DB;
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

    public function test_1_titular_cria_convite_gerando_status_pendente_token_e_pin_6_digitos_criptografado_no_banco(): void
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
                'membro' => ['id', 'name', 'email', 'status', 'permissoes', 'codigo_ativacao', 'link_convite'],
                'link_convite',
                'codigo_ativacao',
            ])
            ->assertJsonPath('membro.status', 'pendente')
            ->assertJsonPath('membro.is_owner', false);

        $pinRetornado = (string) $res->json('codigo_ativacao');
        $linkRetornado = (string) $res->json('link_convite');

        $this->assertMatchesRegularExpression('/^\d{6}$/', $pinRetornado);
        $this->assertStringContainsString('aceitar-convite.html?token=', $linkRetornado);

        $convidado = User::where('email', 'juliana@anjonexus.com.br')->firstOrFail();
        $this->assertEquals('pendente', $convidado->status);
        $this->assertNull($convidado->email_verified_at);
        $this->assertNotNull($convidado->convite_token);
        $this->assertEquals(64, strlen($convidado->convite_token));

        $valorBrutoNoBanco = DB::table('users')
            ->where('id', $convidado->id)
            ->value('convite_codigo_criptografado');

        $this->assertNotNull($valorBrutoNoBanco);
        $this->assertNotEquals($pinRetornado, $valorBrutoNoBanco);
        $this->assertEquals($pinRetornado, $convidado->convite_codigo_criptografado);
        $this->assertEquals($this->titular->id, $convidado->convidado_por_id);

        // Serialização padrão do Model User oculta convite_token e convite_codigo_criptografado
        $serializado = $convidado->toArray();
        $this->assertArrayNotHasKey('convite_token', $serializado);
        $this->assertArrayNotHasKey('convite_codigo_criptografado', $serializado);
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

    public function test_3_exposicao_controlada_do_pin_no_get_equipe_e_limpeza_apos_ativacao_ou_5_tentativas_e_rota_publica_sem_pin(): void
    {
        Mail::fake();

        $resConvite = $this->actingAs($this->titular, 'sanctum')->postJson('/api/equipe', [
            'name' => 'Carlos Convidado',
            'email' => 'carlos.convidado@anjonexus.com.br',
            'permissoes' => [Permissao::STARTUPS_CRIAR_EDITAR->value],
        ]);
        $resConvite->assertStatus(201);

        $pinCorreto = (string) $resConvite->json('codigo_ativacao');
        $convidado = User::where('email', 'carlos.convidado@anjonexus.com.br')->firstOrFail();
        $token = $convidado->convite_token;

        // 1. GET /api/equipe retorna codigo_ativacao e link_convite para o Titular enquanto pendente
        $resListaPendente = $this->actingAs($this->titular, 'sanctum')->getJson('/api/equipe');
        $resListaPendente->assertStatus(200);
        $membroListado = collect($resListaPendente->json('membros'))->firstWhere('id', $convidado->id);
        $this->assertNotNull($membroListado);
        $this->assertEquals($pinCorreto, $membroListado['codigo_ativacao']);
        $this->assertStringContainsString($token, (string) $membroListado['link_convite']);

        // 2. Rota pública GET /api/convites/{token} valida o convite mas NUNCA expõe codigo_ativacao
        $resPublico = $this->getJson("/api/convites/{$token}");
        $resPublico->assertStatus(200)
            ->assertJsonPath('valido', true)
            ->assertJsonPath('agencia_nome', 'Consultoria Anjo Nexus B2B')
            ->assertJsonPath('convidado_nome', 'Carlos Convidado')
            ->assertJsonMissing(['codigo_ativacao', 'convite_codigo_criptografado']);

        $pinErrado = $pinCorreto === '999999' ? '111111' : '999999';

        // 3. 1ª tentativa incorreta -> 422 e incrementa convite_tentativas = 1
        $resErro1 = $this->postJson('/api/convites/aceitar', [
            'token' => $token,
            'codigo' => $pinErrado,
            'password' => 'NovaSenha@2026',
            'password_confirmation' => 'NovaSenha@2026',
        ]);
        $resErro1->assertStatus(422);
        $this->assertEquals(1, $convidado->fresh()->convite_tentativas);

        // 4. Ativação com PIN correto -> 200, status = ativo, email_verified_at preenchido e campos de convite limpos no banco
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
        $this->assertNull($convidado->convite_codigo_criptografado);
        $this->assertNull($convidado->convite_expira_em);
        $this->assertTrue(Hash::check('NovaSenha@2026', $convidado->password));

        // 5. Após ativado, GET /api/equipe retorna codigo_ativacao = null e link_convite = null
        $resListaAtivo = $this->actingAs($this->titular, 'sanctum')->getJson('/api/equipe');
        $membroAtivoListado = collect($resListaAtivo->json('membros'))->firstWhere('id', $convidado->id);
        $this->assertNull($membroAtivoListado['codigo_ativacao']);
        $this->assertNull($membroAtivoListado['link_convite']);

        // 6. Testa bloqueio após 5 falhas consecutivas em um segundo convite: limpa banco e retorna null no GET /api/equipe
        $resConvite2 = $this->actingAs($this->titular, 'sanctum')->postJson('/api/equipe', [
            'name' => 'Tentativas BruteForce',
            'email' => 'bruteforce@anjonexus.com.br',
            'permissoes' => [],
        ]);
        $pinCorreto2 = (string) $resConvite2->json('codigo_ativacao');
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
        $this->assertNull($convidado2->convite_codigo_criptografado);
        $this->assertNull($convidado2->convite_expira_em);

        // GET /api/equipe após 5 tentativas retorna codigo_ativacao = null e convite_expirado = true
        $resListaBloqueado = $this->actingAs($this->titular, 'sanctum')->getJson('/api/equipe');
        $membroBloqueadoListado = collect($resListaBloqueado->json('membros'))->firstWhere('id', $convidado2->id);
        $this->assertNull($membroBloqueadoListado['codigo_ativacao']);
        $this->assertNull($membroBloqueadoListado['link_convite']);
        $this->assertTrue($membroBloqueadoListado['convite_expirado']);
    }

    public function test_4_proibicao_de_delegacao_transitiva_e_visibilidade_restrita_de_pin_entre_gerentes(): void
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

        // Titular convida um segundo Gerente B (pendente, com equipe.gerenciar)
        $resConviteGerenteB = $this->actingAs($this->titular, 'sanctum')->postJson('/api/equipe', [
            'name' => 'Gerente Operacional B Pendente',
            'email' => 'gerente.b@anjonexus.com.br',
            'permissoes' => [
                Permissao::STARTUPS_CRIAR_EDITAR->value,
                Permissao::EQUIPE_GERENCIAR->value,
            ],
        ]);
        $resConviteGerenteB->assertStatus(201);
        $idGerenteB = $resConviteGerenteB->json('membro.id');
        $pinGerenteB = $resConviteGerenteB->json('codigo_ativacao');

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
        $pinConsultorComum = $resComum->json('codigo_ativacao');

        // 2. No GET /api/equipe autenticado como Gerente A:
        //    - Vê codigo_ativacao do Consultor Comum pendente
        //    - Recebe null para codigo_ativacao e link_convite do Gerente B pendente (criado pelo Titular)
        $resListaGerenteA = $this->actingAs($gerenteA, 'sanctum')->getJson('/api/equipe');
        $resListaGerenteA->assertStatus(200);
        $membrosParaGerenteA = collect($resListaGerenteA->json('membros'));

        $comumVistoPorGerenteA = $membrosParaGerenteA->firstWhere('id', $idConsultorComum);
        $this->assertEquals($pinConsultorComum, $comumVistoPorGerenteA['codigo_ativacao']);
        $this->assertNotNull($comumVistoPorGerenteA['link_convite']);

        $gerenteBVistoPorGerenteA = $membrosParaGerenteA->firstWhere('id', $idGerenteB);
        $this->assertNull($gerenteBVistoPorGerenteA['codigo_ativacao']);
        $this->assertNull($gerenteBVistoPorGerenteA['link_convite']);
        $this->assertFalse($gerenteBVistoPorGerenteA['pode_gerenciar']);

        // Já o Titular vê o codigo_ativacao de ambos (Consultor Comum e Gerente B)
        $resListaTitular = $this->actingAs($this->titular, 'sanctum')->getJson('/api/equipe');
        $membrosParaTitular = collect($resListaTitular->json('membros'));
        $this->assertEquals($pinGerenteB, $membrosParaTitular->firstWhere('id', $idGerenteB)['codigo_ativacao']);
        $this->assertEquals($pinConsultorComum, $membrosParaTitular->firstWhere('id', $idConsultorComum)['codigo_ativacao']);

        // 3. Gerente A tenta convidar alguém concedendo equipe.gerenciar ou assinatura.gerenciar -> 403
        $this->actingAs($gerenteA, 'sanctum')->postJson('/api/equipe', [
            'name' => 'Tentativa SubGerente',
            'email' => 'subgerente@anjonexus.com.br',
            'permissoes' => [Permissao::EQUIPE_GERENCIAR->value],
        ])->assertStatus(403);

        $this->actingAs($gerenteA, 'sanctum')->postJson('/api/equipe', [
            'name' => 'Tentativa Financeiro',
            'email' => 'financeiro@anjonexus.com.br',
            'permissoes' => [Permissao::ASSINATURA_GERENCIAR->value],
        ])->assertStatus(403);

        // 4. Gerente A tenta promover o Consultor Comum para equipe.gerenciar -> 403
        $this->actingAs($gerenteA, 'sanctum')->putJson("/api/equipe/{$idConsultorComum}/permissoes", [
            'permissoes' => [Permissao::EQUIPE_GERENCIAR->value],
        ])->assertStatus(403);

        // 5. Gerente A tenta editar/remover o Titular (owner_id) ou o Gerente B -> 403
        $this->actingAs($gerenteA, 'sanctum')->putJson("/api/equipe/{$this->titular->id}/permissoes", [
            'permissoes' => [],
        ])->assertStatus(403);
        $this->actingAs($gerenteA, 'sanctum')->deleteJson("/api/equipe/{$this->titular->id}")
            ->assertStatus(403);

        $this->actingAs($gerenteA, 'sanctum')->putJson("/api/equipe/{$idGerenteB}/permissoes", [
            'permissoes' => [],
        ])->assertStatus(403);
        $this->actingAs($gerenteA, 'sanctum')->postJson("/api/equipe/{$idGerenteB}/reenviar-convite")
            ->assertStatus(403);
        $this->actingAs($gerenteA, 'sanctum')->deleteJson("/api/equipe/{$idGerenteB}")
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

        $this->actingAs($consultorComExcluir, 'sanctum')
            ->getJson('/api/assinatura/atual')
            ->assertStatus(403);

        $this->actingAs($consultorComExcluir, 'sanctum')
            ->postJson('/api/assinatura/renovar')
            ->assertStatus(403);

        $this->actingAs($consultorComExcluir, 'sanctum')
            ->postJson('/api/assinatura/atualizar-forma-pagamento', ['forma_pagamento' => 'CARD'])
            ->assertStatus(403);

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

        $this->actingAs($this->titular, 'sanctum')
            ->patchJson("/api/equipe/{$consultor->id}/status", ['status' => 'inativo'])
            ->assertStatus(200)
            ->assertJsonPath('membro.status', 'inativo');

        $this->assertEquals(0, PersonalAccessToken::where('tokenable_id', $consultor->id)->count());

        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $plainToken)
            ->getJson('/api/me')
            ->assertStatus(401);

        $this->actingAs($this->titular, 'sanctum')
            ->patchJson("/api/equipe/{$consultor->id}/status", ['status' => 'ativo'])
            ->assertStatus(200);

        $consultor->fresh()->createToken('auth_token')->plainTextToken;
        $this->assertEquals(1, PersonalAccessToken::where('tokenable_id', $consultor->id)->count());

        $this->actingAs($this->titular, 'sanctum')
            ->deleteJson("/api/equipe/{$consultor->id}")
            ->assertStatus(200);

        $this->assertEquals(0, PersonalAccessToken::where('tokenable_id', $consultor->id)->count());
        $this->assertDatabaseMissing('users', ['id' => $consultor->id]);

        $this->assertDatabaseHas('startups', [
            'id' => $startupDaAgencia->id,
            'agencia_id' => $this->agencia->id,
            'deleted_at' => null,
        ]);
    }

    public function test_8_link_convite_utiliza_config_front_url_e_ignora_headers_de_origem_do_cliente(): void
    {
        Mail::fake();
        config(['app.front_url' => 'https://app.anjonexus.com.br']);

        $res = $this->actingAs($this->titular, 'sanctum')
            ->withHeaders([
                'Referer' => 'http://evil.test/paginas/equipe.html',
                'X-Frontend-Url' => 'capacitor://localhost/paginas/equipe.html',
            ])
            ->postJson('/api/equipe', [
                'name' => 'Consultor Blindado',
                'email' => 'blindado@anjonexus.com.br',
                'permissoes' => [Permissao::STARTUPS_CRIAR_EDITAR->value],
            ]);

        $res->assertStatus(201);

        $linkRetornado = (string) $res->json('link_convite');
        $this->assertStringStartsWith('https://app.anjonexus.com.br/paginas/aceitar-convite.html?token=', $linkRetornado);
        $this->assertStringNotContainsString('evil.test', $linkRetornado);
        $this->assertStringNotContainsString('capacitor://localhost', $linkRetornado);

        $resListagem = $this->actingAs($this->titular, 'sanctum')
            ->withHeaders([
                'Referer' => 'http://evil.test/paginas/equipe.html',
                'X-Frontend-Url' => 'capacitor://localhost/paginas/equipe.html',
            ])
            ->getJson('/api/equipe');

        $resListagem->assertStatus(200);
        $membroBlindado = collect($resListagem->json('membros'))->firstWhere('email', 'blindado@anjonexus.com.br');
        $this->assertNotNull($membroBlindado);
        $this->assertStringStartsWith('https://app.anjonexus.com.br/paginas/aceitar-convite.html?token=', (string) $membroBlindado['link_convite']);
    }
}
