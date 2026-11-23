<?php

namespace Tests\Feature;

use App\Models\Agencia;
use App\Models\Plano;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EquipeQuotaLimitTest extends TestCase
{
    use RefreshDatabase;

    private Plano $planoStandard;
    private Plano $planoDuplo;
    private Plano $planoPro;
    private Plano $planoIlimitado;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->planoStandard = Plano::create([
            'nome' => 'Standard',
            'preco' => 49.90,
            'limite_startups' => 2,
            'limite_membros_equipe' => 1,
            'limite_minutas_ia' => 3,
        ]);

        $this->planoDuplo = Plano::create([
            'nome' => 'Duplo',
            'preco' => 69.90,
            'limite_startups' => 4,
            'limite_membros_equipe' => 2,
            'limite_minutas_ia' => 6,
        ]);

        $this->planoPro = Plano::create([
            'nome' => 'Pro',
            'preco' => 99.90,
            'limite_startups' => 10,
            'limite_membros_equipe' => 5,
            'limite_minutas_ia' => 15,
        ]);

        $this->planoIlimitado = Plano::create([
            'nome' => 'Custom Ilimitado',
            'preco' => 599.90,
            'limite_startups' => 100,
            'limite_membros_equipe' => null,
            'limite_minutas_ia' => 100,
        ]);
    }

    private function criarAgenciaETitular(Plano $plano): array
    {
        $agencia = Agencia::create([
            'nome' => 'Consultoria Teste Cotas ' . uniqid(),
            'plano_id' => $plano->id,
            'status_pagamento' => 'ativo',
            'assinatura_expira_em' => now()->addDays(30),
        ]);

        $titular = User::create([
            'name' => 'Titular Responsável',
            'email' => 'titular_' . uniqid() . '@consultoria.com.br',
            'password' => Hash::make('Senha@12345'),
            'agencia_id' => $agencia->id,
            'perfil_acesso' => 'consultor_admin',
            'status' => 'ativo',
        ]);

        $agencia->owner_id = $titular->id;
        $agencia->save();

        return [$agencia, $titular];
    }

    public function test_1_titular_contabilizado_na_cota(): void
    {
        [$agencia, $titular] = $this->criarAgenciaETitular($this->planoStandard);

        $resposta = $this->actingAs($titular, 'sanctum')->getJson('/api/equipe');

        $resposta->assertStatus(200)
            ->assertJsonPath('cota_equipe.usados', 1)
            ->assertJsonPath('cota_equipe.ativos', 1)
            ->assertJsonPath('cota_equipe.pendentes', 0)
            ->assertJsonPath('cota_equipe.inativos', 0)
            ->assertJsonPath('cota_equipe.limite', 1)
            ->assertJsonPath('cota_equipe.restantes', 0)
            ->assertJsonPath('cota_equipe.ilimitado', false)
            ->assertJsonPath('cota_equipe.atingiu_limite', true)
            ->assertJsonPath('cota_equipe.inclui_titular', true);
    }

    public function test_2_convite_pendente_consome_vaga(): void
    {
        [$agencia, $titular] = $this->criarAgenciaETitular($this->planoDuplo);

        $primeiroConvite = $this->actingAs($titular, 'sanctum')->postJson('/api/equipe', [
            'name' => 'Consultor Um',
            'email' => 'consultor1@consultoria.com.br',
            'permissoes' => ['startups.criar_editar'],
        ]);

        $primeiroConvite->assertStatus(201)
            ->assertJsonPath('cota_equipe.usados', 2)
            ->assertJsonPath('cota_equipe.ativos', 1)
            ->assertJsonPath('cota_equipe.pendentes', 1)
            ->assertJsonPath('cota_equipe.atingiu_limite', true);

        $segundoConvite = $this->actingAs($titular, 'sanctum')->postJson('/api/equipe', [
            'name' => 'Consultor Dois',
            'email' => 'consultor2@consultoria.com.br',
            'permissoes' => ['startups.criar_editar'],
        ]);

        $segundoConvite->assertStatus(403)
            ->assertJsonPath('code', 'LIMITE_CONSULTORES_ATINGIDO')
            ->assertJsonPath('codigo_erro', 'LIMITE_CONSULTORES_ATINGIDO')
            ->assertJsonPath('usados', 2)
            ->assertJsonPath('limite', 2);

        $this->assertDatabaseMissing('users', [
            'email' => 'consultor2@consultoria.com.br',
        ]);
    }

    public function test_3_inativacao_libera_vaga_imediatamente(): void
    {
        [$agencia, $titular] = $this->criarAgenciaETitular($this->planoDuplo);

        $consultorAtivo = User::create([
            'name' => 'Consultor Ativo',
            'email' => 'ativo@consultoria.com.br',
            'password' => Hash::make('Senha@12345'),
            'agencia_id' => $agencia->id,
            'perfil_acesso' => 'consultor_padrao',
            'status' => 'ativo',
            'convidado_por_id' => $titular->id,
        ]);

        $bloqueio = $this->actingAs($titular, 'sanctum')->postJson('/api/equipe', [
            'name' => 'Consultor Extra',
            'email' => 'extra@consultoria.com.br',
        ]);
        $bloqueio->assertStatus(403);

        $inativar = $this->actingAs($titular, 'sanctum')->patchJson("/api/equipe/{$consultorAtivo->id}/status", [
            'status' => 'inativo',
        ]);

        $inativar->assertStatus(200)
            ->assertJsonPath('cota_equipe.usados', 1)
            ->assertJsonPath('cota_equipe.ativos', 1)
            ->assertJsonPath('cota_equipe.inativos', 1)
            ->assertJsonPath('cota_equipe.atingiu_limite', false);

        $novoConvite = $this->actingAs($titular, 'sanctum')->postJson('/api/equipe', [
            'name' => 'Consultor Substituto',
            'email' => 'substituto@consultoria.com.br',
        ]);

        $novoConvite->assertStatus(201)
            ->assertJsonPath('cota_equipe.usados', 2)
            ->assertJsonPath('cota_equipe.atingiu_limite', true);
    }

    public function test_4_bloqueio_de_reativacao_com_limite_cheio(): void
    {
        [$agencia, $titular] = $this->criarAgenciaETitular($this->planoDuplo);

        $consultorInativo = User::create([
            'name' => 'Consultor Inativo',
            'email' => 'inativo@consultoria.com.br',
            'password' => Hash::make('Senha@12345'),
            'agencia_id' => $agencia->id,
            'perfil_acesso' => 'consultor_padrao',
            'status' => 'inativo',
            'convidado_por_id' => $titular->id,
        ]);

        $consultorAtivo = User::create([
            'name' => 'Consultor Ativo 2',
            'email' => 'ativo2@consultoria.com.br',
            'password' => Hash::make('Senha@12345'),
            'agencia_id' => $agencia->id,
            'perfil_acesso' => 'consultor_padrao',
            'status' => 'ativo',
            'convidado_por_id' => $titular->id,
        ]);

        $reativacao = $this->actingAs($titular, 'sanctum')->patchJson("/api/equipe/{$consultorInativo->id}/status", [
            'status' => 'ativo',
        ]);

        $reativacao->assertStatus(403)
            ->assertJsonPath('code', 'LIMITE_CONSULTORES_ATINGIDO')
            ->assertJsonPath('codigo_erro', 'LIMITE_CONSULTORES_ATINGIDO');

        $this->assertEquals('inativo', $consultorInativo->fresh()->status);
    }

    public function test_5_reativacao_permitida_apos_liberar_vaga(): void
    {
        [$agencia, $titular] = $this->criarAgenciaETitular($this->planoDuplo);

        $consultorInativo = User::create([
            'name' => 'Consultor Para Reativar',
            'email' => 'reativar@consultoria.com.br',
            'password' => Hash::make('Senha@12345'),
            'agencia_id' => $agencia->id,
            'perfil_acesso' => 'consultor_padrao',
            'status' => 'inativo',
            'convidado_por_id' => $titular->id,
        ]);

        $consultorAtivo = User::create([
            'name' => 'Consultor Temporario',
            'email' => 'temporario@consultoria.com.br',
            'password' => Hash::make('Senha@12345'),
            'agencia_id' => $agencia->id,
            'perfil_acesso' => 'consultor_padrao',
            'status' => 'ativo',
            'convidado_por_id' => $titular->id,
        ]);

        $this->actingAs($titular, 'sanctum')->deleteJson("/api/equipe/{$consultorAtivo->id}")
            ->assertStatus(200)
            ->assertJsonPath('cota_equipe.usados', 1);

        $reativacao = $this->actingAs($titular, 'sanctum')->patchJson("/api/equipe/{$consultorInativo->id}/status", [
            'status' => 'ativo',
        ]);

        $reativacao->assertStatus(200)
            ->assertJsonPath('membro.status', 'ativo')
            ->assertJsonPath('cota_equipe.usados', 2)
            ->assertJsonPath('cota_equipe.atingiu_limite', true);

        $this->assertEquals('ativo', $consultorInativo->fresh()->status);
    }

    public function test_6_remocao_cancelamento_de_convite_pendente_libera_vaga(): void
    {
        [$agencia, $titular] = $this->criarAgenciaETitular($this->planoDuplo);

        $convitePendente = User::create([
            'name' => 'Consultor Pendente',
            'email' => 'pendente@consultoria.com.br',
            'password' => Hash::make('Senha@12345'),
            'agencia_id' => $agencia->id,
            'perfil_acesso' => 'consultor_padrao',
            'status' => 'pendente',
            'convite_token' => 'token_de_teste_123',
            'convite_codigo_criptografado' => '123456',
            'convite_expira_em' => now()->addHours(48),
            'convidado_por_id' => $titular->id,
        ]);

        $bloqueio = $this->actingAs($titular, 'sanctum')->postJson('/api/equipe', [
            'name' => 'Tentativa Bloqueada',
            'email' => 'bloqueado@consultoria.com.br',
        ]);
        $bloqueio->assertStatus(403);

        $remocao = $this->actingAs($titular, 'sanctum')->deleteJson("/api/equipe/{$convitePendente->id}");
        $remocao->assertStatus(200)
            ->assertJsonPath('cota_equipe.usados', 1)
            ->assertJsonPath('cota_equipe.pendentes', 0);

        $novo = $this->actingAs($titular, 'sanctum')->postJson('/api/equipe', [
            'name' => 'Novo Apos Cancelamento',
            'email' => 'novoapos@consultoria.com.br',
        ]);
        $novo->assertStatus(201);
    }

    public function test_7_reenvio_de_convite_nao_e_bloqueado_pelo_teto(): void
    {
        [$agencia, $titular] = $this->criarAgenciaETitular($this->planoDuplo);

        $convitePendente = User::create([
            'name' => 'Consultor Pendente Cheio',
            'email' => 'pendente_cheio@consultoria.com.br',
            'password' => Hash::make('Senha@12345'),
            'agencia_id' => $agencia->id,
            'perfil_acesso' => 'consultor_padrao',
            'status' => 'pendente',
            'convite_token' => 'token_pendente_cheio',
            'convite_codigo_criptografado' => '654321',
            'convite_expira_em' => now()->addHours(48),
            'convidado_por_id' => $titular->id,
        ]);

        $reenvio = $this->actingAs($titular, 'sanctum')->postJson("/api/equipe/{$convitePendente->id}/reenviar-convite");

        $reenvio->assertStatus(200)
            ->assertJsonPath('membro.status', 'pendente');

        $this->assertNotNull($reenvio->json('codigo_ativacao'));
        $this->assertNotNull($reenvio->json('link_convite'));
    }

    public function test_8_upgrade_de_plano_plano_ilimitado(): void
    {
        [$agencia, $titular] = $this->criarAgenciaETitular($this->planoStandard);

        $bloqueio = $this->actingAs($titular, 'sanctum')->postJson('/api/equipe', [
            'name' => 'Bloqueado Standard',
            'email' => 'bloqueado_standard@consultoria.com.br',
        ]);
        $bloqueio->assertStatus(403);

        $agencia->plano_id = $this->planoPro->id;
        $agencia->save();
        $titular->refresh();

        $permitidoPro = $this->actingAs($titular, 'sanctum')->postJson('/api/equipe', [
            'name' => 'Aceito Pro',
            'email' => 'aceito_pro@consultoria.com.br',
        ]);
        $permitidoPro->assertStatus(201)
            ->assertJsonPath('cota_equipe.usados', 2)
            ->assertJsonPath('cota_equipe.limite', 5)
            ->assertJsonPath('cota_equipe.restantes', 3)
            ->assertJsonPath('cota_equipe.atingiu_limite', false);

        $agencia->plano_id = $this->planoIlimitado->id;
        $agencia->save();
        $titular->refresh();

        $consultaIlimitado = $this->actingAs($titular, 'sanctum')->getJson('/api/equipe');
        $consultaIlimitado->assertStatus(200)
            ->assertJsonPath('cota_equipe.ilimitado', true)
            ->assertJsonPath('cota_equipe.limite', null)
            ->assertJsonPath('cota_equipe.restantes', null)
            ->assertJsonPath('cota_equipe.atingiu_limite', false);
    }
}
