<?php

namespace Tests\Feature;

use App\Mail\CodigoVerificacaoCadastroMail;
use App\Models\EmailVerificacaoCadastro;
use App\Models\Plano;
use App\Models\User;
use App\Services\AbacatePayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class VerificacaoEmailCadastroTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'ana@consultoria.com.br';
    private const ENVIO = '/api/auth/enviar-codigo-verificacao';
    private const CONFIRMACAO = '/api/auth/verificar-codigo-email';
    private const REGISTRO = '/api/auth/registrar-com-plano';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->freezeSecond();
    }

    private function enviar(): string
    {
        $this->postJson(self::ENVIO, ['email' => ' ANA@consultoria.com.br ', 'nome' => 'Ana'])
            ->assertOk()->assertExactJson(['expira_em_segundos' => 900, 'reenvio_em_segundos' => 60]);

        return Mail::sent(CodigoVerificacaoCadastroMail::class)->last()->codigo;
    }

    private function verificar(string $codigo)
    {
        return $this->postJson(self::CONFIRMACAO, ['email' => self::EMAIL, 'codigo' => $codigo]);
    }

    private function cadastro(): array
    {
        $plano = Plano::create(['nome' => 'Individual', 'preco' => 99, 'limite_startups' => 2, 'limite_membros_equipe' => 1]);

        return ['name' => 'Ana', 'email' => self::EMAIL, 'nome_agencia' => 'Consultoria',
            'password' => 'senha-forte', 'plano_id' => $plano->id, 'forma_pagamento' => 'PIX'];
    }

    public function test_envia_codigo_ao_dominio_corporativo_e_persiste_apenas_o_hash(): void
    {
        $codigo = $this->enviar();
        Mail::assertSent(CodigoVerificacaoCadastroMail::class, fn ($email) => $email->hasTo(self::EMAIL));
        $registro = EmailVerificacaoCadastro::sole();
        $this->assertSame(self::EMAIL, $registro->email);
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $codigo);
        $this->assertTrue(Hash::check($codigo, $registro->codigo_hash));
        $this->assertNotContains($codigo, $registro->getAttributes());
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('agencias', 0);
        Mail::sent(CodigoVerificacaoCadastroMail::class)->sole()->assertSeeInHtml($codigo);
    }

    public function test_email_ja_cadastrado_nao_recebe_codigo(): void
    {
        User::factory()->create(['email' => self::EMAIL]);
        $this->postJson(self::ENVIO, ['email' => self::EMAIL])->assertUnprocessable()->assertJsonPath('codigo_erro', 'EMAIL_JA_CADASTRADO');
        Mail::assertNothingSent();
        $this->assertDatabaseCount('email_verificacoes_cadastro', 0);
    }

    public function test_reenvio_respeita_cooldown_e_substitui_codigo_e_token(): void
    {
        $antigo = $this->enviar();
        $this->travel(59)->seconds();
        $this->postJson(self::ENVIO, ['email' => self::EMAIL])->assertStatus(429)->assertJsonPath('segundos_restantes', 1);
        Mail::assertSentCount(1);
        $this->travel(1)->second();
        $novo = $this->enviar();
        $this->assertNotSame($antigo, $novo);
        $this->verificar($antigo)->assertUnprocessable();
        $this->verificar($novo)->assertOk();
        $this->travel(60)->seconds();
        $this->enviar();
        $this->assertNull(EmailVerificacaoCadastro::sole()->verification_token_hash);
    }

    public function test_cinco_erros_esgotam_as_tentativas(): void
    {
        $codigo = $this->enviar();
        $incorreto = $codigo === '000000' ? '000001' : '000000';
        for ($restantes = 4; $restantes >= 0; $restantes--) {
            $this->verificar($incorreto)->assertUnprocessable()->assertJsonPath('tentativas_restantes', $restantes);
        }
        $this->verificar($codigo)->assertStatus(429)->assertJsonPath('codigo_erro', 'LIMITE_TENTATIVAS_EXCEDIDO');
        $this->assertNull(EmailVerificacaoCadastro::sole()->codigo_hash);
    }

    public function test_codigo_expira_apos_quinze_minutos(): void
    {
        $codigo = $this->enviar();
        $this->travel(15)->minutes();
        $this->verificar($codigo)->assertUnprocessable()->assertJsonPath('codigo_erro', 'CODIGO_EXPIRADO');
    }

    public function test_confirmacao_substitui_codigo_por_token_de_uma_hora(): void
    {
        $codigo = $this->enviar();
        $token = $this->verificar($codigo)->assertOk()->assertJsonPath('expira_em_segundos', 3600)->json('verification_token');
        $registro = EmailVerificacaoCadastro::sole();
        $this->assertSame(hash('sha256', $token), $registro->verification_token_hash);
        $this->assertTrue($registro->token_expira_em->eq(now()->addHour()));
        $this->verificar($codigo)->assertUnprocessable()->assertJsonPath('codigo_erro', 'CODIGO_INVALIDO');
    }

    public function test_rejeita_token_ausente_incorreto_alheio_ou_expirado(): void
    {
        $dados = $this->cadastro();
        $token = $this->confirmarEmailCadastro(self::EMAIL);
        foreach ([[], ['verification_token' => 'incorreto'], ['verification_token' => $token, 'email' => 'outra@empresa.com']] as $alteracoes) {
            $this->postJson(self::REGISTRO, $alteracoes + $dados)->assertUnprocessable()->assertJsonPath('codigo_erro', 'VERIFICACAO_EMAIL_INVALIDA');
        }
        $this->postJson('/api/auth/registro', $dados)->assertUnprocessable()->assertJsonPath('codigo_erro', 'VERIFICACAO_EMAIL_INVALIDA');
        $this->travel(60)->minutes();
        $this->postJson(self::REGISTRO, $dados + ['verification_token' => $token])
            ->assertUnprocessable()->assertJsonPath('codigo_erro', 'VERIFICACAO_EMAIL_INVALIDA');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('agencias', 0);
    }

    public function test_cobranca_e_consumo_sao_atomicos_e_o_token_so_funciona_uma_vez(): void
    {
        $dados = $this->cadastro() + ['verification_token' => $this->confirmarEmailCadastro(self::EMAIL)];
        $gateway = $this->mock(AbacatePayService::class);
        $gateway->shouldReceive('gerarCobranca')->once()->andThrow(new \RuntimeException('Gateway indisponível'));
        $gateway->shouldReceive('gerarCobranca')->once()->andReturn(['url_checkout' => 'https://abacatepay.com/pay/teste', 'id_transacao' => 'teste']);
        $this->postJson(self::REGISTRO, $dados)->assertStatus(500);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('agencias', 0);
        $resposta = $this->postJson(self::REGISTRO, $dados)->assertCreated();
        $this->assertNotEmpty($resposta->json('token_pendencia'));
        $this->assertNotNull(User::sole()->email_verified_at);
        $registro = EmailVerificacaoCadastro::sole();
        $this->assertNotNull($registro->consumido_em);
        $this->assertNull($registro->verification_token_hash);
        $this->postJson(self::REGISTRO, $dados)->assertUnprocessable()->assertJsonPath('codigo_erro', 'VERIFICACAO_EMAIL_INVALIDA');
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_limpeza_preserva_verificacoes_ativas_e_recentes(): void
    {
        $this->enviar();
        foreach (['codigo_expira_em', 'token_expira_em', 'consumido_em'] as $campo) {
            EmailVerificacaoCadastro::create(['email' => "$campo@empresa.com", $campo => now()->subHours(25)]);
        }
        EmailVerificacaoCadastro::create(['email' => 'recente@empresa.com', 'token_expira_em' => now()->subDay()]);
        $this->artisan('auth:limpar-verificacoes-email')->assertSuccessful();
        $this->assertDatabaseCount('email_verificacoes_cadastro', 2);
        $this->assertDatabaseHas('email_verificacoes_cadastro', ['email' => self::EMAIL]);
        $this->assertDatabaseHas('email_verificacoes_cadastro', ['email' => 'recente@empresa.com']);
    }
}
