<?php

namespace Tests\Feature;

use App\Adapters\AbacatePayAdapter;
use App\Models\Agencia;
use App\Models\Plano;
use App\Models\User;
use Database\Seeders\PlanoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class TrocaPlanoTest extends TestCase
{
    use RefreshDatabase;

    private Agencia $agencia;
    private User $titular;
    private const TROCAR = '/api/assinatura/trocar-plano';
    private const SIMULAR = '/api/assinatura/simular-troca?plano_id=';
    private const CANCELAR = '/api/assinatura/downgrade-agendado';

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeSecond();
        $this->seed(PlanoSeeder::class);
        $this->agencia = Agencia::create([
            'nome' => 'Consultoria de teste',
            'plano_id' => 2,
            'status_pagamento' => 'ativo',
            'assinatura_expira_em' => now()->addDays(15),
        ]);
        $this->titular = $this->membro();
        $this->agencia->update(['owner_id' => $this->titular->id]);
        $this->actingAs($this->titular, 'sanctum');
        config(['services.abacatepay.api_key' => 'teste', 'services.abacatepay.webhook_secret' => 'teste']);
        Http::preventStrayRequests();
        Http::fakeSequence()
            ->push(['data' => ['id' => 'primeiro', 'url' => 'https://abacatepay.com/primeiro']])
            ->push(['data' => ['id' => 'segundo', 'url' => 'https://abacatepay.com/segundo']]);
    }

    private function membro(string $status = 'ativo', string $perfil = 'consultor_admin'): User
    {
        return User::factory()->create(['agencia_id' => $this->agencia->id, 'status' => $status, 'perfil_acesso' => $perfil]);
    }

    private function pagar(string $id): void
    {
        $this->postJson('/api/webhook/abacatepay?webhookSecret=teste', [
            'event' => 'checkout.completed',
            'data' => ['status' => 'PAID', 'id' => $id, 'externalId' => 'agencia_'.$this->agencia->id.'_teste_troca'],
        ])->assertOk();
    }

    public function test_somente_titular_pode_operar_a_troca(): void
    {
        foreach (['consultor_admin', 'consultor_padrao'] as $perfil) {
            $this->actingAs($this->membro('ativo', $perfil), 'sanctum');
            $this->getJson(self::SIMULAR.'3')->assertForbidden();
            $this->postJson(self::TROCAR, ['plano_id' => 3])->assertForbidden();
            $this->deleteJson(self::CANCELAR)->assertForbidden();
        }
    }

    public function test_mesmo_plano_e_rejeitado(): void
    {
        $this->getJson(self::SIMULAR.'2')->assertUnprocessable();
        $this->postJson(self::TROCAR, ['plano_id' => 2])->assertUnprocessable();
    }

    public function test_simula_valor_e_dias_de_credito(): void
    {
        $this->getJson(self::SIMULAR.'3')->assertOk()->assertJson([
            'tipo' => 'upgrade', 'dias_restantes' => 15, 'credito_estimado' => 49.95,
            'dias_extras' => 5, 'novo_ciclo_dias' => 35, 'valor_a_pagar' => 299.90, 'permitido' => true,
        ]);
        Http::assertNothingSent();
    }

    public function test_pix_e_cartao_substituem_pendencia_e_apenas_checkout_vigente_ativa_uma_vez(): void
    {
        $this->agencia->update(['plano_agendado_id' => 1, 'alerta_falha_pagamento_enviado_em' => now()]);
        foreach (['PIX' => 'primeiro', 'CARD' => 'segundo'] as $forma => $id) {
            $this->postJson(self::TROCAR, ['plano_id' => 3, 'forma_pagamento' => $forma])
                ->assertOk()->assertJsonPath('id_transacao', $id)->assertJsonPath('url_checkout', 'https://abacatepay.com/'.$id);
            Http::assertSent(fn (Request $requisicao) => $requisicao['items'][0]['id'] === 'prod_yjeeY2u5sXwGaZJZGGpEgW1g'
                && $requisicao['methods'] === [$forma] && str_ends_with($requisicao['externalId'], '_troca'));
            $this->assertDatabaseHas('agencias', [
                'id' => $this->agencia->id, 'plano_id' => 2, 'status_pagamento' => 'ativo',
                'upgrade_transacao_id' => $id, 'upgrade_forma_pagamento' => $forma, 'forma_pagamento' => 'PIX',
            ]);
        }
        $this->getJson('/api/assinatura/atual')->assertJsonPath('upgrade_pendente.id', 3);
        $this->pagar('primeiro');
        $this->assertEquals(2, $this->agencia->refresh()->plano_id);
        $this->pagar('segundo');
        $this->pagar('segundo');
        $this->assertDatabaseHas('agencias', [
            'id' => $this->agencia->id, 'plano_id' => 3, 'status_pagamento' => 'ativo', 'forma_pagamento' => 'CARD',
            'assinatura_expira_em' => now()->addDays(35), 'plano_agendado_id' => null,
            'plano_upgrade_pendente_id' => null, 'upgrade_transacao_id' => null, 'upgrade_forma_pagamento' => null,
            'upgrade_dias_extras' => 0, 'url_checkout' => null, 'pagamento_expira_em' => null,
            'alerta_falha_pagamento_enviado_em' => null,
        ]);
    }

    public function test_detalha_excesso_de_startups_e_membros(): void
    {
        foreach ([1, 2, 3] as $numero) {
            $this->agencia->startups()->create(['cnpj' => str_pad($numero, 14, '0'), 'razao_social' => 'Startup '.$numero]);
        }
        $this->membro('pendente');
        $this->membro();
        $this->membro('inativo');
        $impedimentos = ['startups' => ['uso' => 3, 'limite' => 2, 'excedente' => 1], 'membros' => ['uso' => 3, 'limite' => 1, 'excedente' => 2]];
        $this->getJson(self::SIMULAR.'1')->assertOk()->assertJson(['permitido' => false, 'impedimentos' => $impedimentos]);
        $this->postJson(self::TROCAR, ['plano_id' => 1])->assertUnprocessable()
            ->assertJson(['codigo_erro' => 'DOWNGRADE_EXCEDE_COTA_PLANO', 'impedimentos' => $impedimentos]);
        Http::assertNothingSent();
    }

    public function test_agenda_e_cancela_downgrade_descartando_upgrade_abandonado(): void
    {
        $this->postJson(self::TROCAR, ['plano_id' => 3])->assertOk();
        $this->postJson(self::TROCAR, ['plano_id' => 1])->assertOk()->assertJsonPath('plano_agendado.id', 1);
        $this->getJson('/api/assinatura/atual')->assertJsonPath('plano_agendado.id', 1);
        $this->pagar('primeiro');
        $this->assertDatabaseHas('agencias', ['plano_id' => 2, 'plano_agendado_id' => 1, 'upgrade_transacao_id' => null, 'url_checkout' => null]);
        $this->deleteJson(self::CANCELAR)->assertOk();
        $this->assertNull($this->agencia->refresh()->plano_agendado_id);
    }

    public function test_comando_revalida_cotas_no_vencimento(): void
    {
        $this->postJson(self::TROCAR, ['plano_id' => 1])->assertOk();
        $this->artisan('assinaturas:processar-downgrades')->assertSuccessful();
        $this->assertEquals(1, $this->agencia->refresh()->plano_agendado_id);
        $consultor = $this->membro();
        $this->travel(16)->days();
        Log::spy();
        $this->artisan('assinaturas:processar-downgrades')->assertSuccessful();
        Log::shouldHaveReceived('warning')->once();
        $this->assertNull($this->agencia->refresh()->plano_agendado_id);
        $this->assertEquals(2, $this->agencia->plano_id);
        $this->assertEquals('ativo', $consultor->fresh()->status);
        $consultor->update(['status' => 'inativo']);
        $this->agencia->update(['plano_agendado_id' => 1]);
        $this->artisan('assinaturas:processar-downgrades')->assertSuccessful();
        $this->assertDatabaseHas('agencias', ['plano_id' => 1, 'plano_agendado_id' => null]);
    }

    public function test_renovacao_vencida_cobra_plano_agendado(): void
    {
        $this->agencia->update(['plano_agendado_id' => 1, 'assinatura_expira_em' => now()->subDay()]);
        $this->postJson('/api/assinatura/renovar')->assertOk();
        Http::assertSent(fn (Request $requisicao) => $requisicao['items'][0]['id'] === 'prod_NKNk5t4saLSRLGEEZ21qL0sG');
        $this->assertDatabaseHas('agencias', ['plano_id' => 1, 'plano_agendado_id' => null]);
    }

    public function test_troca_imediata_respeita_cota_e_herda_forma_sem_bonus(): void
    {
        $this->membro();
        foreach (['ativo', 'suspenso', 'cancelado', 'pendente'] as $status) {
            $this->agencia->update(['status_pagamento' => $status, 'assinatura_expira_em' => now()->subDay(), 'forma_pagamento' => 'CARD']);
            $this->getJson(self::SIMULAR.'3')->assertOk()->assertJson(['tipo' => 'troca_imediata', 'dias_extras' => 0]);
            $this->postJson(self::TROCAR, ['plano_id' => 1])->assertUnprocessable()->assertJsonPath('codigo_erro', 'DOWNGRADE_EXCEDE_COTA_PLANO');
        }
        $this->postJson(self::TROCAR, ['plano_id' => 3])->assertOk();
        $this->assertDatabaseHas('agencias', ['plano_id' => 2, 'upgrade_forma_pagamento' => 'CARD', 'status_pagamento' => 'pendente']);
        $this->pagar('primeiro');
        $this->assertDatabaseHas('agencias', ['plano_id' => 3, 'assinatura_expira_em' => now()->addDays(30)]);
    }

    public function test_downgrade_simulado_antes_do_vencimento_exige_nova_confirmacao_apos_vencer(): void
    {
        $this->getJson(self::SIMULAR.'1')->assertJsonPath('tipo', 'downgrade');
        $this->travel(16)->days();
        $this->postJson(self::TROCAR, ['plano_id' => 1, 'tipo_simulado' => 'downgrade'])->assertConflict();
        Http::assertNothingSent();
    }

    public function test_webhook_sem_segredo_configurado_recusa_confirmacao(): void
    {
        config(['services.abacatepay.webhook_secret' => null]);
        $this->postJson('/api/webhook/abacatepay', [])->assertUnauthorized();
    }

    public function test_produto_ausente_interrompe_cobranca(): void
    {
        $this->expectException(\DomainException::class);
        (new AbacatePayAdapter())->paraPayloadCriacao($this->agencia, $this->titular, new Plano(), 'https://example.org');
    }
}
