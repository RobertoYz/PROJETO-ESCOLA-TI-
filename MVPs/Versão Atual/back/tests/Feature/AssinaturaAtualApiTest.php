<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Agencia;
use App\Models\Plano;
use App\Models\Startup;
use App\Services\AbacatePayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

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
     * EDTAN-160 (1): Consulta de GET /api/assinatura/atual retornando plano, forma 'PIX',
     * uso atual, data de vencimento e flags de renovação.
     */
    public function test_consulta_assinatura_atual_retorna_plano_pix_vencimento_e_flags_renovacao(): void
    {
        $vencimento = now()->addDays(15);

        $agencia = Agencia::create([
            'nome' => 'Consultoria Anjo Nexus',
            'plano_id' => $this->plano->id,
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
                'status_pagamento' => 'ativo',
                'assinatura_expira_em_formatada' => $vencimento->format('d/m/Y'),
                'dias_restantes' => 15,
                'pode_renovar' => false,
                'data_liberacao_renovacao' => $vencimento->copy()->subDays(5)->format('d/m/Y'),
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
     * EDTAN-160 (4): Idempotência: chamar POST /api/assinatura/renovar duas vezes seguidas
     * dentro de 60 minutos reaproveita a mesma url_checkout sem chamar o AbacatePay novamente.
     */
    public function test_idempotencia_reaproveita_cobranca_aberta_sem_duplicar_no_abacatepay(): void
    {
        $agencia = Agencia::create([
            'nome' => 'Consultoria Idempotente',
            'plano_id' => $this->plano->id,
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

        // O mock garante que gerarCobranca seja chamado EXATAMENTE 1 vez mesmo em 2 requisições
        $this->mock(AbacatePayService::class, function ($mock) {
            $mock->shouldReceive('gerarCobranca')->once()->andReturn([
                'id_transacao' => 'bill_unica_idempotente',
                'url_checkout' => 'https://abacatepay.com/pay/bill_unica_idempotente',
            ]);
        });

        // 1ª chamada: cria a cobrança PIX
        $primeiraResposta = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/assinatura/renovar');

        $primeiraResposta->assertStatus(200)
            ->assertJson([
                'id_transacao' => 'bill_unica_idempotente',
                'url_checkout' => 'https://abacatepay.com/pay/bill_unica_idempotente',
            ]);

        // 2ª chamada consecutiva (dentro de 60 minutos): reaproveita a mesma cobrança
        $segundaResposta = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/assinatura/renovar');

        $segundaResposta->assertStatus(200)
            ->assertJson([
                'id_transacao' => 'bill_unica_idempotente',
                'url_checkout' => 'https://abacatepay.com/pay/bill_unica_idempotente',
            ]);
    }

    /**
     * EDTAN-160 (5): Renovação acumulativa no Webhook (+30 dias somados sobre a data futura existente,
     * preservando os dias restantes e limpando url_checkout e pagamento_expira_em).
     */
    public function test_webhook_soma_30_dias_acumulativos_sobre_vencimento_futuro_e_limpa_checkout(): void
    {
        $vencimentoAtual = now()->addDays(4)->startOfSecond();
        $vencimentoEsperado = $vencimentoAtual->copy()->addDays(30);

        $agencia = Agencia::create([
            'nome' => 'Consultoria Renovação Acumulativa',
            'plano_id' => $this->plano->id,
            'status_pagamento' => 'ativo',
            'assinatura_expira_em' => $vencimentoAtual,
            'id_transacao' => 'bill_renovacao_acumulativa',
            'url_checkout' => 'https://abacatepay.com/pay/bill_renovacao_acumulativa',
            'pagamento_expira_em' => now()->addMinutes(45),
        ]);

        $secret = env('ABACATEPAY_WEBHOOK_SECRET');
        $urlWebhook = '/api/webhook/abacatepay' . ($secret ? '?webhookSecret=' . urlencode($secret) : '');

        $response = $this->postJson($urlWebhook, [
            'event' => 'billing.paid',
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
        $this->assertEquals(
            $vencimentoEsperado->format('Y-m-d H:i:s'),
            $agencia->assinatura_expira_em->format('Y-m-d H:i:s')
        );
    }
}
