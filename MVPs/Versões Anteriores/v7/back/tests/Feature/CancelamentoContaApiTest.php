<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Agencia;
use App\Models\Plano;
use App\Models\Startup;
use App\Models\Edital;
use App\Models\Minuta;
use App\Models\KanbanBoard;
use App\Models\KanbanColumn;
use App\Models\KanbanCard;
use App\Models\EditalFavorito;
use App\Models\AlertaMatch;
use App\Models\AlertaPrazo;
use App\Services\AbacatePayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class CancelamentoContaApiTest extends TestCase
{
    use RefreshDatabase;

    private Plano $plano;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plano = Plano::create([
            'nome' => 'Plano Corporativo Mensal',
            'preco' => 297.00,
            'limite_startups' => 25,
            'limite_membros_equipe' => 5,
            'limite_minutas_ia' => 50,
        ]);
    }

    /**
     * EDTAN-158: Cancelamento de conta com sucesso por consultor_admin enviando password correto
     * e ciente_lgpd = true (verificando exclusão física em cascata e revogação de tokens).
     */
    public function test_consultor_admin_cancela_conta_com_sucesso_e_remove_dados_em_cascata(): void
    {
        $agencia = Agencia::create([
            'nome' => 'Consultoria Alpha Nexus',
            'plano_id' => $this->plano->id,
            'status_pagamento' => 'ativo',
            'assinatura_expira_em' => now()->addDays(30),
        ]);

        $admin = User::create([
            'name' => 'Roberto Admin',
            'email' => 'roberto.admin@alphanexus.com.br',
            'password' => Hash::make('SenhaSegura@2026'),
            'agencia_id' => $agencia->id,
            'perfil_acesso' => 'consultor_admin',
        ]);

        $membroEquipe = User::create([
            'name' => 'Julia Consultora',
            'email' => 'julia@alphanexus.com.br',
            'password' => Hash::make('SenhaMembro@2026'),
            'agencia_id' => $agencia->id,
            'perfil_acesso' => 'consultor_padrao',
        ]);

        $tokenAdmin = $admin->createToken('auth_token')->plainTextToken;
        $membroEquipe->createToken('auth_token')->plainTextToken;

        $startup = Startup::create([
            'agencia_id' => $agencia->id,
            'cnpj' => '12.345.678/0001-99',
            'razao_social' => 'STARTUP INOVA TECH LTDA',
            'nome_fantasia' => 'InovaTech',
            'nicho_atuacao' => 'Inteligência Artificial',
        ]);

        $edital = Edital::create([
            'title' => 'Edital FINEP Subvenção 2026',
            'fonte' => 'FINEP',
            'status' => 'active',
        ]);

        $board = KanbanBoard::create([
            'agencia_id' => $agencia->id,
            'title' => 'Pipeline Principal',
        ]);

        $column = KanbanColumn::create([
            'board_id' => $board->id,
            'title' => 'Em Elaboração',
            'position' => 1.0,
        ]);

        $card = KanbanCard::create([
            'board_id' => $board->id,
            'column_id' => $column->id,
            'edital_id' => $edital->id,
            'title' => 'Proposta InovaTech',
        ]);

        $minuta = Minuta::create([
            'startup_id' => $startup->id,
            'edital_id' => $edital->id,
            'user_id' => $admin->id,
            'kanban_card_id' => $card->id,
            'titulo_projeto' => 'Projeto IA Aplicada',
            'conteudo_gerado' => 'Conteúdo técnico da minuta...',
        ]);

        EditalFavorito::create([
            'user_id' => $admin->id,
            'edital_id' => $edital->id,
            'startup_id' => $startup->id,
        ]);

        AlertaMatch::create([
            'user_id' => $admin->id,
            'startup_id' => $startup->id,
            'edital_id' => $edital->id,
            'score_match' => 92,
            'titulo' => 'Alto Match FINEP',
            'mensagem' => 'Compatibilidade de 92%.',
        ]);

        AlertaPrazo::create([
            'user_id' => $admin->id,
            'startup_id' => $startup->id,
            'edital_id' => $edital->id,
            'kanban_card_id' => $card->id,
            'data_limite' => now()->addDays(3),
            'dias_restantes' => 3,
            'nivel_urgencia' => 'critico',
            'titulo' => 'Prazo Crítico FINEP',
            'mensagem' => 'Faltam 3 dias.',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenAdmin)
            ->deleteJson('/api/conta/cancelar', [
                'password' => 'SenhaSegura@2026',
                'ciente_lgpd' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Sua conta, assinatura e todos os dados vinculados foram permanentemente excluídos conforme a LGPD.',
            ]);

        // Verifica exclusão física em cascata no banco
        $this->assertDatabaseMissing('agencias', ['id' => $agencia->id]);
        $this->assertDatabaseMissing('users', ['id' => $admin->id]);
        $this->assertDatabaseMissing('users', ['id' => $membroEquipe->id]);
        $this->assertDatabaseMissing('startups', ['id' => $startup->id]);
        $this->assertDatabaseMissing('kanban_boards', ['id' => $board->id]);
        $this->assertDatabaseMissing('kanban_columns', ['id' => $column->id]);
        $this->assertDatabaseMissing('kanban_cards', ['id' => $card->id]);
        $this->assertDatabaseMissing('minutas', ['id' => $minuta->id]);
        $this->assertDatabaseMissing('edital_favoritos', ['user_id' => $admin->id]);
        $this->assertDatabaseMissing('alerta_matches', ['user_id' => $admin->id]);
        $this->assertDatabaseMissing('alerta_prazos', ['user_id' => $admin->id]);

        // Verifica revogação total dos tokens Sanctum
        $this->assertEquals(0, PersonalAccessToken::whereIn('tokenable_id', [$admin->id, $membroEquipe->id])->count());
    }

    /**
     * EDTAN-158: Falha (422) ao tentar cancelar com senha incorreta ou sem marcar ciente_lgpd,
     * garantindo que os dados permanecem intactos no banco.
     */
    public function test_cancelamento_falha_com_422_se_senha_incorreta_ou_sem_ciente_lgpd(): void
    {
        $agencia = Agencia::create([
            'nome' => 'Consultoria Intacta',
            'plano_id' => $this->plano->id,
            'status_pagamento' => 'ativo',
            'assinatura_expira_em' => now()->addDays(30),
        ]);

        $admin = User::create([
            'name' => 'Admin Intacto',
            'email' => 'admin@intacta.com.br',
            'password' => Hash::make('SenhaCorreta#2026'),
            'agencia_id' => $agencia->id,
            'perfil_acesso' => 'consultor_admin',
        ]);

        $startup = Startup::create([
            'agencia_id' => $agencia->id,
            'cnpj' => '98.765.432/0001-10',
            'razao_social' => 'STARTUP PERMANECE LTDA',
        ]);

        // 1. Tentativa com senha incorreta
        $resSenhaErrada = $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/conta/cancelar', [
                'password' => 'SenhaErrada#000',
                'ciente_lgpd' => true,
            ]);

        $resSenhaErrada->assertStatus(422)
            ->assertJson([
                'message' => 'A senha informada está incorreta.',
            ]);

        // 2. Tentativa sem marcar ciente_lgpd
        $resSemLgpd = $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/conta/cancelar', [
                'password' => 'SenhaCorreta#2026',
                'ciente_lgpd' => false,
            ]);

        $resSemLgpd->assertStatus(422);

        // Dados devem permanecer intactos
        $this->assertDatabaseHas('agencias', ['id' => $agencia->id]);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertDatabaseHas('startups', ['id' => $startup->id]);
    }

    /**
     * EDTAN-158: Após o cancelamento bem-sucedido, o mesmo e-mail consegue se registrar novamente
     * em /api/auth/verificar-email e /api/auth/registrar-com-plano sem erro de e-mail duplicado.
     */
    public function test_email_pode_se_registrar_novamente_do_zero_apos_cancelamento_de_conta(): void
    {
        $agencia = Agencia::create([
            'nome' => 'Consultoria Ciclo Antigo',
            'plano_id' => $this->plano->id,
            'status_pagamento' => 'ativo',
            'assinatura_expira_em' => now()->addDays(30),
        ]);

        $admin = User::create([
            'name' => 'Mariana Retorno',
            'email' => 'mariana@retorno.com.br',
            'password' => Hash::make('SenhaInicial@123'),
            'agencia_id' => $agencia->id,
            'perfil_acesso' => 'consultor_admin',
        ]);

        // Cancela a conta
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/conta/cancelar', [
                'password' => 'SenhaInicial@123',
                'ciente_lgpd' => true,
            ])
            ->assertStatus(200);

        // 1. Verifica disponibilidade do mesmo e-mail em /api/auth/verificar-email
        $resVerificar = $this->postJson('/api/auth/verificar-email', [
            'email' => 'mariana@retorno.com.br',
        ]);

        $resVerificar->assertStatus(200)
            ->assertJson([
                'disponivel' => true,
            ]);

        // 2. Registra novamente em /api/auth/registrar-com-plano
        $this->mock(AbacatePayService::class, function ($mock) {
            $mock->shouldReceive('gerarCobranca')->once()->andReturn([
                'id_transacao' => 'bill_retorno_123',
                'url_checkout' => 'https://abacatepay.com/pay/bill_retorno_123',
            ]);
        });

        $resNovoRegistro = $this->postJson('/api/auth/registrar-com-plano', [
            'name' => 'Mariana Nova Consultoria',
            'email' => 'mariana@retorno.com.br',
            'password' => 'NovaSenhaForte@2026',
            'plano_id' => $this->plano->id,
            'nome_agencia' => 'Nova Consultoria Mariana',
        ]);

        $resNovoRegistro->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'mariana@retorno.com.br']);
        $this->assertDatabaseHas('agencias', ['nome' => 'Nova Consultoria Mariana']);
    }

    /**
     * EDTAN-158: Bloqueio (403) caso um consultor_padrao tente cancelar a conta.
     */
    public function test_consultor_padrao_recebe_403_ao_tentar_cancelar_conta(): void
    {
        $agencia = Agencia::create([
            'nome' => 'Consultoria Protegida RBAC',
            'plano_id' => $this->plano->id,
            'status_pagamento' => 'ativo',
            'assinatura_expira_em' => now()->addDays(30),
        ]);

        $consultorPadrao = User::create([
            'name' => 'Lucas Consultor Padrão',
            'email' => 'lucas@protegida.com.br',
            'password' => Hash::make('SenhaPadrao@123'),
            'agencia_id' => $agencia->id,
            'perfil_acesso' => 'consultor_padrao',
        ]);

        $response = $this->actingAs($consultorPadrao, 'sanctum')
            ->deleteJson('/api/conta/cancelar', [
                'password' => 'SenhaPadrao@123',
                'ciente_lgpd' => true,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Apenas o administrador da consultoria pode encerrar a conta.',
            ]);

        $this->assertDatabaseHas('agencias', ['id' => $agencia->id]);
        $this->assertDatabaseHas('users', ['id' => $consultorPadrao->id]);
    }

    /**
     * EDTAN-158: Funcionamento do isPagamentoAtivo() com assinatura_expira_em (ciclo de 30 dias)
     * e atualização via WebhookController.
     */
    public function test_ciclo_assinatura_30_dias_is_pagamento_ativo_e_webhook(): void
    {
        // 1. Agência ativa com assinatura_expira_em no futuro -> true
        $agenciaAtiva = Agencia::create([
            'nome' => 'Agência Ciclo Futuro',
            'plano_id' => $this->plano->id,
            'status_pagamento' => 'ativo',
            'assinatura_expira_em' => now()->addDays(30),
        ]);
        $this->assertTrue($agenciaAtiva->isPagamentoAtivo());

        // 2. Agência ativa com assinatura_expira_em null (legado) -> true
        $agenciaSemExpiracao = Agencia::create([
            'nome' => 'Agência Sem Expiração',
            'plano_id' => $this->plano->id,
            'status_pagamento' => 'ativo',
            'assinatura_expira_em' => null,
        ]);
        $this->assertTrue($agenciaSemExpiracao->isPagamentoAtivo());

        // 3. Agência com status_pagamento = ativo mas assinatura_expira_em vencida -> false
        $agenciaExpirada = Agencia::create([
            'nome' => 'Agência Ciclo Expirado',
            'plano_id' => $this->plano->id,
            'status_pagamento' => 'ativo',
            'assinatura_expira_em' => now()->subDay(),
        ]);
        $this->assertFalse($agenciaExpirada->isPagamentoAtivo());

        // 4. Webhook AbacatePay confirmando pagamento preenche assinatura_expira_em = now()->addDays(30)
        $agenciaPendente = Agencia::create([
            'nome' => 'Agência Aguardando PIX',
            'plano_id' => $this->plano->id,
            'status_pagamento' => 'pendente',
            'assinatura_expira_em' => null,
        ]);

        $secret = env('ABACATEPAY_WEBHOOK_SECRET');
        $urlWebhook = '/api/webhook/abacatepay' . ($secret ? '?webhookSecret=' . urlencode($secret) : '');

        $resWebhook = $this->postJson($urlWebhook, [
            'event' => 'billing.paid',
            'data' => [
                'id' => 'bill_pix_30d',
                'status' => 'PAID',
                'externalId' => 'agencia_' . $agenciaPendente->id . '_abc',
            ],
        ]);

        $resWebhook->assertStatus(200);

        $agenciaPendente->refresh();
        $this->assertEquals('ativo', $agenciaPendente->status_pagamento);
        $this->assertNotNull($agenciaPendente->assinatura_expira_em);
        $this->assertTrue($agenciaPendente->assinatura_expira_em->isFuture());
        $this->assertTrue($agenciaPendente->isPagamentoAtivo());
    }
}
