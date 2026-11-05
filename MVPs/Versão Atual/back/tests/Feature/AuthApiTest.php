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
                'mensagem' => 'E-mail disponível para cadastro.'
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
                'id' => 'bill_teste_789',
                'url' => 'https://abacatepay.com/pay/bill_teste_789'
            ]);
        });

        $payload = [
            'name' => 'Roberto Empreendedor',
            'email' => 'roberto@startupnexus.com.br',
            'password' => 'SegredoForte#2026',
            'plano_id' => $this->plano->id,
            'nome_agencia' => 'Nexus Consultoria de Inovação'
        ];

        $response = $this->postJson('/api/auth/registrar-com-plano', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'mensagem',
                'checkout_url',
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
            'status_pagamento' => 'ativo',
            'pagamento_ativo' => true
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
                'mensagem' => 'Login realizado com sucesso!'
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
                'mensagem' => 'Credenciais incorretas. Verifique seu e-mail e senha.'
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
            'status_pagamento' => 'pendente',
            'pagamento_ativo' => false
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
}
