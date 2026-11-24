<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Agencia;
use App\Models\Plano;
use App\Services\AbacatePayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    private Plano $plano;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plano = Plano::create([
            'nome' => 'Plano Individual',
            'preco' => 197.00,
            'limite_startups' => 10,
            'limite_membros_equipe' => 1
        ]);
    }

    /**
     * RF-01 / RF-02: Teste de verificação de e-mail disponível e duplicado.
     */
    public function test_verificar_email_identifica_disponibilidade_corretamente(): void
    {
        // 1. E-mail disponível
        $responseDisponivel = $this->postJson('/api/auth/verificar-email', [
            'email' => 'novo.consultor@anjo.com.br'
        ]);

        $responseDisponivel->assertStatus(200)
            ->assertJson([
                'disponivel' => true,
                'message' => 'E-mail disponível para cadastro.'
            ]);

        // 2. Cria usuário
        User::create([
            'name' => 'Consultor Cadastrado',
            'email' => 'novo.consultor@anjo.com.br',
            'password' => Hash::make('Senha@123456')
        ]);

        // 3. E-mail agora deve estar indisponível (422)
        $responseIndisponivel = $this->postJson('/api/auth/verificar-email', [
            'email' => 'novo.consultor@anjo.com.br'
        ]);

        $responseIndisponivel->assertStatus(422)
            ->assertJson([
                'disponivel' => false
            ]);
    }

    /**
     * RF-02 / RF-03: Teste de registro de conta com plano e geração de cobrança.
     */
    public function test_registrar_com_plano_cria_usuario_agencia_e_retorna_checkout(): void
    {
        $this->mock(AbacatePayService::class, function ($mock) {
            $mock->shouldReceive('gerarCobranca')->once()->andReturn([
                'id_transacao' => 'bill_teste_789',
                'url_checkout' => 'https://abacatepay.com/pay/bill_teste_789'
            ]);
        });

        $payload = [
            'name' => 'Roberto Empreendedor',
            'email' => 'roberto@startupnexus.com.br',
            'password' => 'SegredoForte#2026',
            'plano_id' => $this->plano->id,
            'nome_agencia' => 'Nexus Consultoria de Inovação'
        ];

        $payload['verification_token'] = $this->confirmarEmailCadastro($payload['email']);
        $response = $this->postJson('/api/auth/registrar-com-plano', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'url_checkout',
                'token_pendencia',
                'usuario' => ['id', 'name', 'email'],
                'agencia' => ['id', 'nome', 'status_pagamento']
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'roberto@startupnexus.com.br',
            'name' => 'Roberto Empreendedor'
        ]);

        $this->assertDatabaseHas('agencias', [
            'nome' => 'Nexus Consultoria de Inovação',
            'status_pagamento' => 'pendente'
        ]);
    }

    /**
     * RF-04: Teste de login com credenciais válidas e pagamento ativo.
     */
    public function test_login_sucesso_com_pagamento_ativo_retorna_token(): void
    {
        $agencia = Agencia::create([
            'nome' => 'Nexus Tech Hub',
            'plano_id' => $this->plano->id,
            'status_pagamento' => 'ativo'
        ]);

        $usuario = User::create([
            'name' => 'Ana Diretora',
            'email' => 'ana@nexustech.com.br',
            'password' => Hash::make('MinhaSenha@2026'),
            'agencia_id' => $agencia->id,
            'perfil_acesso' => 'consultor_admin'
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'ana@nexustech.com.br',
            'password' => 'MinhaSenha@2026'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'sucesso',
                'message' => 'Login realizado com sucesso!'
            ])
            ->assertJsonStructure([
                'token',
                'dados' => ['usuario', 'agencia']
            ]);
    }

    /**
     * RF-04: Teste de login com senha incorreta retorna 401.
     */
    public function test_login_com_senha_incorreta_retorna_401(): void
    {
        User::create([
            'name' => 'Carlos Consultor',
            'email' => 'carlos@nexus.com.br',
            'password' => Hash::make('Correta#123')
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'carlos@nexus.com.br',
            'password' => 'Errada#999'
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'erro',
                'message' => 'Credenciais incorretas. Verifique seu e-mail e senha.'
            ]);
    }

    /**
     * RF-04: Teste de login com pagamento pendente retorna 403 e código PAGAMENTO_PENDENTE.
     */
    public function test_login_com_pagamento_pendente_retorna_403_e_codigo_especifico(): void
    {
        $agencia = Agencia::create([
            'nome' => 'Startup Em Espera',
            'plano_id' => $this->plano->id,
            'status_pagamento' => 'pendente'
        ]);

        User::create([
            'name' => 'Daniel Pendente',
            'email' => 'daniel@espera.com.br',
            'password' => Hash::make('Pendente#123'),
            'agencia_id' => $agencia->id
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'daniel@espera.com.br',
            'password' => 'Pendente#123'
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'codigo' => 'PAGAMENTO_PENDENTE'
            ]);
    }

    /**
     * RF-05: Teste de solicitação de recuperação de senha.
     */
    public function test_solicitar_recuperacao_senha_gera_token_para_email_existente(): void
    {
        Mail::fake();

        User::create([
            'name' => 'Eduardo Recuperar',
            'email' => 'eduardo@nexus.com.br',
            'password' => Hash::make('SenhaVelha#123')
        ]);

        $response = $this->postJson('/api/auth/esqueci-senha', [
            'email' => 'eduardo@nexus.com.br'
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'eduardo@nexus.com.br'
        ]);
    }

    /**
     * RF-05: Teste de solicitação de recuperação de senha para e-mail não existente retorna 404.
     */
    public function test_solicitar_recuperacao_senha_retorna_404_para_email_inexistente(): void
    {
        $response = $this->postJson('/api/auth/esqueci-senha', [
            'email' => 'naoexiste@nexus.com.br'
        ]);

        $response->assertStatus(404);
    }

    /**
     * RF-06: Teste de redefinição de senha com token válido atualiza a senha e invalida o token.
     */
    public function test_redefinir_senha_com_token_valido_atualiza_senha_e_consome_token(): void
    {
        $usuario = User::create([
            'name' => 'Eduardo Redefinir',
            'email' => 'redefinir@nexus.com.br',
            'password' => Hash::make('SenhaAntiga#123')
        ]);

        $tokenPuro = 'token-seguro-de-teste-com-tamanho-suficiente-64-caracteres-ok';
        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->insert([
            'email' => $usuario->email,
            'token' => Hash::make($tokenPuro),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/redefinir-senha', [
            'email' => $usuario->email,
            'token' => $tokenPuro,
            'password' => 'NovaSenhaForte#2026',
            'password_confirmation' => 'NovaSenhaForte#2026',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message']);

        $usuario->refresh();
        $this->assertTrue(Hash::check('NovaSenhaForte#2026', $usuario->password));
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $usuario->email,
        ]);
    }

    /**
     * RF-06: Teste de redefinição de senha com token inválido retorna 400.
     */
    public function test_redefinir_senha_com_token_invalido_retorna_400(): void
    {
        $usuario = User::create([
            'name' => 'Eduardo Token Invalido',
            'email' => 'tokeninvalido@nexus.com.br',
            'password' => Hash::make('SenhaAntiga#123')
        ]);

        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->insert([
            'email' => $usuario->email,
            'token' => Hash::make('token-verdadeiro'),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/redefinir-senha', [
            'email' => $usuario->email,
            'token' => 'token-errado',
            'password' => 'NovaSenhaForte#2026',
            'password_confirmation' => 'NovaSenhaForte#2026',
        ]);

        $response->assertStatus(400);
    }

    /**
     * RF-06: Teste de redefinição de senha com token expirado (> 60 min) retorna 400 e remove o token.
     */
    public function test_redefinir_senha_com_token_expirado_retorna_400(): void
    {
        $usuario = User::create([
            'name' => 'Eduardo Expirado',
            'email' => 'expirado@nexus.com.br',
            'password' => Hash::make('SenhaAntiga#123')
        ]);

        $tokenPuro = 'token-expirado-teste';
        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->insert([
            'email' => $usuario->email,
            'token' => Hash::make($tokenPuro),
            'created_at' => now()->subMinutes(61),
        ]);

        $response = $this->postJson('/api/auth/redefinir-senha', [
            'email' => $usuario->email,
            'token' => $tokenPuro,
            'password' => 'NovaSenhaForte#2026',
            'password_confirmation' => 'NovaSenhaForte#2026',
        ]);

        $response->assertStatus(400);
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $usuario->email,
        ]);
    }
}

