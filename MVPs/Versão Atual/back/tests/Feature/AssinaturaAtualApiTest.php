<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Adapters\AbacatePayAdapter;
use App\Models\User;
use App\Models\Agencia;
use App\Models\Plano;
use App\Models\Startup;
use App\Services\AbacatePayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AssinaturaAtualApiTest extends TestCase
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
     * EDTAN-160 / EDTAN-311 (1): Consulta de GET /api/assinatura/atual retornando plano, forma de pagamento,
     * forma_pagamento_label, em_carencia, datas em d/m/Y e flags de renovação.
     */
    public function test_consulta_assinatura_atual_retorna_plano_pix_vencimento_e_flags_renovacao(): void
    {
        $vencimento = now()->addDays(15);

        $agencia = Agencia::create([
            'nome' => 'Consultoria Anjo Nexus',
            'plano_id' => $this->plano->id,
            'forma_pagamento' => 'PIX',
            'status_pagamento' => 'ativo',
            'assinatura_expira_em' => $vencimento,
        ]);

        $admin = User::create([
            'name' => 'Roberto Consultor Admin',
            'email' => 'roberto@anjonexus.com.br',
            'password' => Hash::make('SenhaSegura@2026'),
            'agencia_id' => $agencia->id,
            'perfil_acesso' => 'consultor_admin',
        ]);

        Startup::create([
            'agencia_id' => $agencia->id,
            'cnpj' => '11.222.333/0001-81',
            'razao_social' => 'STARTUP NEXUS ONE LTDA',
            'nome_fantasia' => 'Nexus One',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/assinatura/atual');

        $response->assertStatus(200)
            ->assertJson([
                'plano' => [
                    'id' => $this->plano->id,
                    'nome' => 'Plano Corporativo Mensal',
                    'preco' => 297.00,
                    'limite_startups' => 25,
                    'limite_membros_equipe' => 5,
                    'limite_minutas_ia' => 50,
                ],
                'uso_atual' => [
                    'startups_cadastradas' => 1,
                    'membros_equipe' => 1,
                    'minutas_ia' => 0,
                ],
                'forma_pagamento' => 'PIX',
                'forma_pagamento_label' => 'PIX',
                'em_carencia' => false,
                'status_pagamento' => 'ativo',
                'assinatura_expira_em_formatada' => $vencimento->format('d/m/Y'),
                'data_vencimento_formatada' => $vencimento->format('d/m/Y'),
                'data_repeticao_formatada' => $vencimento->format('d/m/Y'),
                'dias_restantes' => 15,
                'pode_renovar' => false,
                'data_liberacao_renovacao' => $vencimento->copy()->subDays(5)->format('d/m/Y'),
                'data_liberacao_renovacao_formatada' => $vencimento->copy()->subDays(5)->format('d/m/Y'),
                'cobranca_aberta' => false,
                'url_checkout' => null,
            ]);
    }

    /**
     * EDTAN-160 (2): Bloqueio (422) ao tentar renovar faltando mais de 5 dias (ex: 15 dias restantes).
     */
    public function test_bloqueia_renovacao_com_422_quando_faltam_mais_de_5_dias(): void
    {
        $agencia = Agencia::create([
            'nome' => 'Consultoria Em Dia',
            'plano_id' => $this->plano->id,
            'status_pagamento' => 'ativo',
            'assinatura_expira_em' => now()->addDays(15),
        ]);

        $admin = User::create([
            'name' => 'Admin Em Dia',
            'email' => 'admin@emdia.com.br',
            'password' => Hash::make('SenhaSegura@2026'),
            'agencia_id' => $agencia->id,
            'perfil_acesso' => 'consultor_admin',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/assinatura/renovar');

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'A renovação antecipada fica disponível faltando 5 dias para o vencimento da assinatura.',
            ]);
    }

    /**
     * EDTAN-160 (2b): Bloqueio (403) caso um consultor_padrao tente gerar cobrança de renovação.
     */
    public function test_consultor_padrao_recebe_403_ao_tentar_renovar_assinatura(): void
    {
        $agencia = Agencia::create([
            'nome' => 'Consultoria RBAC Renovação',
            'plano_id' => $this->plano->id,
            'status_pagamento' => 'ativo',
            'assinatura_expira_em' => now()->addDays(3),
        ]);

        $consultorPadrao = User::create([
            'name' => 'Consultor Padrão',
            'email' => 'padrao@rbac.com.br',
            'password' => Hash::make('SenhaPadrao@2026'),
            'agencia_id' => $agencia->id,
            'perfil_acesso' => 'consultor_padrao',
        ]);

        $response = $this->actingAs($consultorPadrao, 'sanctum')
            ->postJson('/api/assinatura/renovar');

        $response->assertStatus(403)
            ->assertJsonStructure(['message']);
    }

    /**
     * EDTAN-160 (3): Sucesso (200) ao renovar faltando 5 dias ou menos,
     * mantendo status_pagamento = 'ativo' e acesso liberado nos dias restantes.
     */
    public function test_permite_renovar_com_200_faltando_5_dias_ou_menos_mantendo_acesso_ativo(): void
    {
        $agencia = Agencia::create([
            'nome' => 'Consultoria Janela Renovação',
            'plano_id' => $this->plano->id,
            'status_pagamento' => 'ativo',
            'assinatura_expira_em' => now()->addDays(4),
        ]);

        $admin = User::create([
            'name' => 'Admin Renovação',
            'email' => 'admin@renovacao.com.br',
            'password' => Hash::make('SenhaSegura@2026'),
            'agencia_id' => $agencia->id,
            'perfil_acesso' => 'consultor_admin',
        ]);

        $this->mock(AbacatePayService::class, function ($mock) {
            $mock->shouldReceive('gerarCobranca')->once()->andReturn([
                'id_transacao' => 'bill_renovacao_4dias',
                'url_checkout' => 'https://abacatepay.com/pay/bill_renovacao_4dias',
            ]);
        });

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/assinatura/renovar');

        $response->assertStatus(200)
            ->assertJson([
                'id_transacao' => 'bill_renovacao_4dias',
                'url_checkout' => 'https://abacatepay.com/pay/bill_renovacao_4dias',
            ])
            ->assertJsonStructure([
                'message',
                'id_transacao',
                'url_checkout',
                'pagamento_expira_em',
            ]);

        $agencia->refresh();
        $this->assertEquals('ativo', $agencia->status_pagamento);
        $this->assertTrue($agencia->isPagamentoAtivo());
        $this->assertEquals('https://abacatepay.com/pay/bill_renovacao_4dias', $agencia->url_checkout);
        $this->assertNotNull($agencia->pagamento_expira_em);
        $this->assertTrue($agencia->pagamento_expira_em->isFuture());
    }

    /**
     * EDTAN-160 / EDTAN-311 (4): Idempotência: chamar POST /api/assinatura/renovar duas vezes seguidas
     * com a mesma forma_pagamento reaproveita a url_checkout, mas se trocar de PIX para CARD gera novo checkout.
     */
    public function test_idempotencia_reaproveita_cobranca_aberta_e_gera_nova_se_trocar_forma_pagamento(): void
    {
        $agencia = Agencia::create([
            'nome' => 'Consultoria Idempotente',
            'plano_id' => $this->plano->id,
            'forma_pagamento' => 'PIX',
            'status_pagamento' => 'ativo',
            'assinatura_expira_em' => now()->addDays(3),
        ]);

        $admin = User::create([
            'name' => 'Admin Idempotente',
            'email' => 'admin@idempotente.com.br',
            'password' => Hash::make('SenhaSegura@2026'),
            'agencia_id' => $agencia->id,
            'perfil_acesso' => 'consultor_admin',
        ]);

        $this->mock(AbacatePayService::class, function ($mock) {
            $mock->shouldReceive('gerarCobranca')->twice()->andReturn(
                [
                    'id_transacao' => 'bill_unica_idempotente_pix',
                    'url_checkout' => 'https://abacatepay.com/pay/bill_unica_idempotente_pix',
                ],
                [
                    'id_transacao' => 'bill_nova_troca_card',
                    'url_checkout' => 'https://abacatepay.com/pay/bill_nova_troca_card',
                ]
            );
        });

        // 1ª chamada: cria a cobrança PIX
        $primeiraResposta = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/assinatura/renovar', ['forma_pagamento' => 'PIX']);

        $primeiraResposta->assertStatus(200)
            ->assertJson([
                'reaproveitada' => false,
                'forma_pagamento' => 'PIX',
                'id_transacao' => 'bill_unica_idempotente_pix',
                'url_checkout' => 'https://abacatepay.com/pay/bill_unica_idempotente_pix',
            ]);

        // 2ª chamada consecutiva com mesma forma (PIX): reaproveita
        $segundaResposta = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/assinatura/renovar', ['forma_pagamento' => 'PIX']);

        $segundaResposta->assertStatus(200)
            ->assertJson([
                'reaproveitada' => true,
                'forma_pagamento' => 'PIX',
                'id_transacao' => 'bill_unica_idempotente_pix',
                'url_checkout' => 'https://abacatepay.com/pay/bill_unica_idempotente_pix',
            ]);

        // 3ª chamada trocando para CARD: gera novo checkout e atualiza forma_pagamento
        $terceiraResposta = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/assinatura/renovar', ['forma_pagamento' => 'CARD']);

        $terceiraResposta->assertStatus(200)
            ->assertJson([
                'reaproveitada' => false,
                'forma_pagamento' => 'CARD',
                'id_transacao' => 'bill_nova_troca_card',
                'url_checkout' => 'https://abacatepay.com/pay/bill_nova_troca_card',
            ]);

        $agencia->refresh();
        $this->assertEquals('CARD', $agencia->forma_pagamento);
    }

    /**
     * EDTAN-160 / EDTAN-311 (5): Renovação acumulativa no Webhook (+30 dias somados sobre a data futura existente,
     * preservando os dias restantes e limpando url_checkout, pagamento_expira_em e alerta_falha_pagamento_enviado_em),
     * além de bloqueio imediato em checkout.refunded e checkout.lost.
     */
    public function test_webhook_soma_30_dias_acumulativos_limpa_alerta_e_corta_acesso_em_refunded_ou_lost(): void
    {
        $vencimentoAtual = now()->addDays(4)->startOfSecond();
        $vencimentoEsperado = $vencimentoAtual->copy()->addDays(30);

        $agencia = Agencia::create([
            'nome' => 'Consultoria Renovação Acumulativa',
            'plano_id' => $this->plano->id,
            'forma_pagamento' => 'CARD',
            'status_pagamento' => 'ativo',
            'assinatura_expira_em' => $vencimentoAtual,
            'alerta_falha_pagamento_enviado_em' => now()->subDay(),
            'id_transacao' => 'bill_renovacao_acumulativa',
            'url_checkout' => 'https://abacatepay.com/pay/bill_renovacao_acumulativa',
            'pagamento_expira_em' => now()->addMinutes(45),
        ]);

        $secret = env('ABACATEPAY_WEBHOOK_SECRET');
        $urlWebhook = '/api/webhook/abacatepay' . ($secret ? '?webhookSecret=' . urlencode($secret) : '');

        $response = $this->postJson($urlWebhook, [
            'event' => 'checkout.completed',
            'data' => [
                'id' => 'bill_renovacao_acumulativa',
                'status' => 'PAID',
                'externalId' => 'agencia_' . $agencia->id . '_renovacao',
            ],
        ]);

        $response->assertStatus(200);

        $agencia->refresh();
        $this->assertEquals('ativo', $agencia->status_pagamento);
        $this->assertNull($agencia->url_checkout);
        $this->assertNull($agencia->pagamento_expira_em);
        $this->assertNull($agencia->alerta_falha_pagamento_enviado_em);
        $this->assertEquals(
            $vencimentoEsperado->format('Y-m-d H:i:s'),
            $agencia->assinatura_expira_em->format('Y-m-d H:i:s')
        );
        $this->assertTrue($agencia->isPagamentoAtivo());

        // Recebimento de checkout.refunded corta acesso imediatamente
        $this->postJson($urlWebhook, [
            'event' => 'checkout.refunded',
            'data' => [
                'id' => 'bill_renovacao_acumulativa',
                'status' => 'REFUNDED',
                'externalId' => 'agencia_' . $agencia->id . '_renovacao',
            ],
        ])->assertStatus(200);

        $agencia->refresh();
        $this->assertEquals('cancelado', $agencia->status_pagamento);
        $this->assertFalse($agencia->isPagamentoAtivo());

        // Recebimento de checkout.lost (chargeback/disputa perdida) define suspenso e bloqueia acesso
        $this->postJson($urlWebhook, [
            'event' => 'checkout.lost',
            'data' => [
                'id' => 'bill_renovacao_acumulativa',
                'status' => 'EXPIRED',
                'externalId' => 'agencia_' . $agencia->id . '_renovacao',
            ],
        ])->assertStatus(200);

        $agencia->refresh();
        $this->assertEquals('suspenso', $agencia->status_pagamento);
        $this->assertFalse($agencia->isPagamentoAtivo());
    }

    /**
     * EDTAN-311 (6): Registro com plano e geração de nova cobrança com CARD e PIX,
     * além do AbacatePayAdapter enviando methods: ['CARD'] ou ['PIX'].
     */
    public function test_registrar_com_plano_e_gerar_nova_cobranca_com_card_e_pix(): void
    {
        $this->mock(AbacatePayService::class, function ($mock) {
            $mock->shouldReceive('gerarCobranca')->twice()->andReturn(
                [
                    'id_transacao' => 'chk_card_001',
                    'url_checkout' => 'https://abacatepay.com/pay/chk_card_001',
                ],
                [
                    'id_transacao' => 'chk_pix_002',
                    'url_checkout' => 'https://abacatepay.com/pay/chk_pix_002',
                ]
            );
        });

        $resRegistro = $this->postJson('/api/auth/registrar-com-plano', [
            'name' => 'Consultor Cartão',
            'email' => 'cartao@consultoria.com.br',
            'password' => 'SenhaForte@2026',
            'plano_id' => $this->plano->id,
            'nome_agencia' => 'Consultoria Cartão B2B',
            'forma_pagamento' => 'CARD',
        ]);

        $resRegistro->assertStatus(201)
            ->assertJsonPath('agencia.forma_pagamento', 'CARD')
            ->assertJsonPath('url_checkout', 'https://abacatepay.com/pay/chk_card_001');

        $this->assertDatabaseHas('agencias', [
            'nome' => 'Consultoria Cartão B2B',
            'forma_pagamento' => 'CARD',
        ]);

        // Geração de nova cobrança alterando para PIX
        $resNovaCobranca = $this->postJson('/api/planos/gerar-nova-cobranca', [
            'plano_id' => $this->plano->id,
            'email' => 'cartao@consultoria.com.br',
            'forma_pagamento' => 'PIX',
        ]);

        $resNovaCobranca->assertStatus(200)
            ->assertJsonPath('forma_pagamento', 'PIX')
            ->assertJsonPath('url_checkout', 'https://abacatepay.com/pay/chk_pix_002');

        $this->assertDatabaseHas('agencias', [
            'nome' => 'Consultoria Cartão B2B',
            'forma_pagamento' => 'PIX',
        ]);

        // Verifica o Adapter diretamente para methods: ['CARD'] e ['PIX']
        $agencia = Agencia::where('nome', 'Consultoria Cartão B2B')->firstOrFail();
        $usuario = User::where('email', 'cartao@consultoria.com.br')->firstOrFail();
        $adapter = new AbacatePayAdapter();

        $payloadCard = $adapter->paraPayloadCriacao($agencia, $usuario, $this->plano, 'https://anjonexus.com/login', 'CARD');
        $this->assertEquals(['CARD'], $payloadCard['methods']);

        $payloadPix = $adapter->paraPayloadCriacao($agencia, $usuario, $this->plano, 'https://anjonexus.com/login', 'PIX');
        $this->assertEquals(['PIX'], $payloadPix['methods']);
    }

    /**
     * EDTAN-311 (7): POST /api/assinatura/atualizar-forma-pagamento:
     * - Restrito a consultor_admin (403 para outros);
     * - Quando > 5 dias vigentes: apenas salva a nova forma_pagamento para o próximo ciclo (sem checkout);
     * - Quando em carência (<= 2 dias após vencimento), vencida, suspensa ou <= 5 dias: gera novo checkout imediatamente.
     */
    public function test_atualizar_forma_pagamento_para_proximo_ciclo_e_geracao_imediata_em_carencia(): void
    {
        $agencia = Agencia::create([
            'nome' => 'Consultoria Troca Pagamento',
            'plano_id' => $this->plano->id,
            'forma_pagamento' => 'PIX',
            'status_pagamento' => 'ativo',
            'assinatura_expira_em' => now()->addDays(20),
        ]);

        $admin = User::create([
            'name' => 'Admin Troca',
            'email' => 'admin@troca.com.br',
            'password' => Hash::make('SenhaSegura@2026'),
            'agencia_id' => $agencia->id,
            'perfil_acesso' => 'consultor_admin',
        ]);

        $padrao = User::create([
            'name' => 'Membro Padrão',
            'email' => 'padrao@troca.com.br',
            'password' => Hash::make('SenhaSegura@2026'),
            'agencia_id' => $agencia->id,
            'perfil_acesso' => 'consultor_padrao',
        ]);

        // 1. Bloqueio 403 para consultor_padrao
        $this->actingAs($padrao, 'sanctum')
            ->postJson('/api/assinatura/atualizar-forma-pagamento', ['forma_pagamento' => 'CARD'])
            ->assertStatus(403);

        // 2. Assinatura ativa com > 5 dias restantes: atualiza no banco sem gerar cobrança antecipada
        $resProximoCiclo = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/assinatura/atualizar-forma-pagamento', ['forma_pagamento' => 'CARD']);

        $resProximoCiclo->assertStatus(200)
            ->assertJson([
                'forma_pagamento' => 'CARD',
                'forma_pagamento_label' => 'Cartão de Crédito',
                'requer_checkout' => false,
                'url_checkout' => null,
            ]);

        $agencia->refresh();
        $this->assertEquals('CARD', $agencia->forma_pagamento);

        // 3. Assinatura em período de carência (vencida há 1 dia): gera novo checkout imediatamente
        $agencia->assinatura_expira_em = now()->subDay();
        $agencia->save();
        $this->assertTrue($agencia->emPeriodoCarencia());

        $this->mock(AbacatePayService::class, function ($mock) {
            $mock->shouldReceive('gerarCobranca')->once()->andReturn([
                'id_transacao' => 'chk_carencia_pix',
                'url_checkout' => 'https://abacatepay.com/pay/chk_carencia_pix',
            ]);
        });

        $resCarencia = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/assinatura/atualizar-forma-pagamento', ['forma_pagamento' => 'PIX']);

        $resCarencia->assertStatus(200)
            ->assertJson([
                'forma_pagamento' => 'PIX',
                'forma_pagamento_label' => 'PIX',
                'requer_checkout' => true,
                'url_checkout' => 'https://abacatepay.com/pay/chk_carencia_pix',
            ]);
    }

    /**
     * EDTAN-311 (8): Comando assinaturas:processar-ciclo:
     * - Fase 1 (Carência <= 2 dias): envia e-mail via Mail::html e preenche alerta_falha_pagamento_enviado_em;
     * - Fase 2 (Suspensão > 2 dias): atualiza status_pagamento = 'suspenso' mantendo os dados;
     * - Expurgo Nunca Pagas (> 3 dias): Hard Delete de contas pendentes com assinatura_expira_em IS NULL;
     * - Fase 3 Expurgo Suspensas (> 30 dias): Hard Delete de contas inadimplentes há mais de 30 dias.
     */
    public function test_comando_processar_ciclo_assinaturas_carencia_email_suspensao_e_hard_delete_3_e_30_dias(): void
    {
        Mail::fake();

        // 1. Conta que nunca pagou criada há 4 dias (> 3 dias) -> Deve sofrer Hard Delete
        $agenciaNuncaPaga = Agencia::create([
            'nome' => 'Agência Abandonada 4 Dias',
            'plano_id' => $this->plano->id,
            'status_pagamento' => 'pendente',
            'assinatura_expira_em' => null,
        ]);
        $agenciaNuncaPaga->timestamps = false;
        $agenciaNuncaPaga->created_at = now()->subDays(4);
        $agenciaNuncaPaga->save();

        $userNuncaPago = User::create([
            'name' => 'Lead Abandonado',
            'email' => 'abandonado@lead.com.br',
            'password' => Hash::make('SenhaSegura@2026'),
            'agencia_id' => $agenciaNuncaPaga->id,
            'perfil_acesso' => 'consultor_admin',
        ]);

        // 2. Conta em período de carência (venceu há 1 dia) -> Deve receber e-mail e continuar ativa
        $agenciaCarencia = Agencia::create([
            'nome' => 'Agência Em Carência',
            'plano_id' => $this->plano->id,
            'forma_pagamento' => 'CARD',
            'status_pagamento' => 'ativo',
            'assinatura_expira_em' => now()->subDay(),
            'alerta_falha_pagamento_enviado_em' => null,
        ]);

        $adminCarencia = User::create([
            'name' => 'Admin Carência',
            'email' => 'carencia@consultoria.com.br',
            'password' => Hash::make('SenhaSegura@2026'),
            'agencia_id' => $agenciaCarencia->id,
            'perfil_acesso' => 'consultor_admin',
        ]);

        // 3. Conta que passou dos 2 dias de carência (venceu há 5 dias) -> Deve ser suspensa mantendo dados
        $agenciaParaSuspender = Agencia::create([
            'nome' => 'Agência Para Suspender',
            'plano_id' => $this->plano->id,
            'status_pagamento' => 'ativo',
            'assinatura_expira_em' => now()->subDays(5),
        ]);

        $startupPreservada = Startup::create([
            'agencia_id' => $agenciaParaSuspender->id,
            'cnpj' => '22.333.444/0001-55',
            'razao_social' => 'STARTUP PRESERVADA NA SUSPENSAO LTDA',
        ]);

        // 4. Conta inadimplente há mais de 30 dias (venceu há 31 dias) -> Deve sofrer Hard Delete
        $agenciaSuspensa31Dias = Agencia::create([
            'nome' => 'Agência Suspensa 31 Dias',
            'plano_id' => $this->plano->id,
            'status_pagamento' => 'suspenso',
            'assinatura_expira_em' => now()->subDays(31),
        ]);

        $userSuspenso31Dias = User::create([
            'name' => 'Admin Expurgo 30d',
            'email' => 'expurgo30d@consultoria.com.br',
            'password' => Hash::make('SenhaSegura@2026'),
            'agencia_id' => $agenciaSuspensa31Dias->id,
            'perfil_acesso' => 'consultor_admin',
        ]);

        $this->artisan('assinaturas:processar-ciclo')
            ->assertExitCode(0);

        // A) Conta nunca paga (> 3 dias) foi removida fisicamente
        $this->assertDatabaseMissing('agencias', ['id' => $agenciaNuncaPaga->id]);
        $this->assertDatabaseMissing('users', ['id' => $userNuncaPago->id]);

        // B1) Conta em carência permaneceu ativa e teve alerta_falha_pagamento_enviado_em preenchido
        $agenciaCarencia->refresh();
        $this->assertEquals('ativo', $agenciaCarencia->status_pagamento);
        $this->assertNotNull($agenciaCarencia->alerta_falha_pagamento_enviado_em);
        $this->assertTrue($agenciaCarencia->isPagamentoAtivo());

        // B2) Conta vencida há 5 dias (> 2 dias de carência) foi suspensa mas seus dados permanecem intactos
        $agenciaParaSuspender->refresh();
        $this->assertEquals('suspenso', $agenciaParaSuspender->status_pagamento);
        $this->assertFalse($agenciaParaSuspender->isPagamentoAtivo());
        $this->assertDatabaseHas('startups', ['id' => $startupPreservada->id]);

        // B3) Conta vencida há 31 dias (> 30 dias) sofreu Hard Delete em cascata
        $this->assertDatabaseMissing('agencias', ['id' => $agenciaSuspensa31Dias->id]);
        $this->assertDatabaseMissing('users', ['id' => $userSuspenso31Dias->id]);
    }
}
