<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Agencia;
use App\Models\Plano;
use App\Models\Startup;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SecurityMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private Plano $plano;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plano = Plano::create([
            'nome' => 'Plano Segurança',
            'preco' => 199.00
        ]);
    }

    /**
     * Segurança: Rotas protegidas por auth:sanctum devem rejeitar requisições sem token (401).
     */
    public function test_rotas_protegidas_sem_token_retornam_401(): void
    {
        // 1. Perfil
        $respPerfil = $this->getJson('/api/auth/perfil');
        $respPerfil->assertStatus(401);

        // 2. Startups
        $respStartups = $this->getJson('/api/startups');
        $respStartups->assertStatus(401);
    }

    /**
     * Segurança: Token inválido ou revogado deve ser sumariamente rejeitado (401).
     */
    public function test_token_invalido_rejeitado_com_401(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer token_invalido_123456789')
            ->getJson('/api/auth/perfil');

        $response->assertStatus(401);
    }

    /**
     * Segurança: Middleware check.pagamento bloqueia agência com pagamento pendente (403).
     */
    public function test_middleware_pagamento_bloqueia_usuario_com_assinatura_pendente(): void
    {
        $agenciaPendente = Agencia::create([
            'nome' => 'Agência Inadimplente',
            'plano_id' => $this->plano->id,
            'status_pagamento' => 'pendente'
        ]);

        $usuario = User::create([
            'name' => 'Consultor Pendente',
            'email' => 'pendente@agencia.com',
            'password' => bcrypt('Senha@12345'),
            'agencia_id' => $agenciaPendente->id
        ]);

        $response = $this->actingAs($usuario, 'sanctum')
            ->getJson('/api/startups');

        $response->assertStatus(403)
            ->assertJson([
                'codigo_erro' => 'PAGAMENTO_PENDENTE'
            ]);
    }

    /**
     * Segurança: Middleware check.pagamento permite acesso irrestrito para agência com status ativo (200).
     */
    public function test_middleware_pagamento_libera_usuario_com_assinatura_ativa(): void
    {
        $agenciaAtiva = Agencia::create([
            'nome' => 'Agência Regularizada',
            'plano_id' => $this->plano->id,
            'status_pagamento' => 'ativo'
        ]);

        $usuario = User::create([
            'name' => 'Consultor Regular',
            'email' => 'regular@agencia.com',
            'password' => bcrypt('Senha@12345'),
            'agencia_id' => $agenciaAtiva->id
        ]);

        Startup::create([
            'agencia_id' => $agenciaAtiva->id,
            'cnpj' => '44.555.666/0001-77',
            'razao_social' => 'Startup Liberada Ltda'
        ]);

        $response = $this->actingAs($usuario, 'sanctum')
            ->getJson('/api/startups');

        $response->assertStatus(200);
    }

    /**
     * Segurança: Cancelamento de cadastro pendente é bloqueado se a conta já estiver com pagamento ativo.
     */
    public function test_impedir_cancelamento_de_conta_com_pagamento_ativo(): void
    {
        $agenciaAtiva = Agencia::create([
            'nome' => 'Agência Blindada',
            'plano_id' => $this->plano->id,
            'status_pagamento' => 'ativo'
        ]);

        $usuario = User::create([
            'name' => 'Admin Blindado',
            'email' => 'admin@blindada.com',
            'password' => bcrypt('Senha@12345'),
            'agencia_id' => $agenciaAtiva->id
        ]);

        $response = $this->actingAs($usuario, 'sanctum')
            ->deleteJson('/api/auth/cancelar-cadastro-pendente', [
                'email' => $usuario->email
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Não é permitido cancelar uma conta com assinatura ativa.'
            ]);
    }
}

