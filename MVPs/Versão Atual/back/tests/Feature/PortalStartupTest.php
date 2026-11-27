<?php

namespace Tests\Feature;

use App\Models\Agencia;
use App\Models\KanbanBoard;
use App\Models\Plano;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PortalStartupTest extends TestCase
{
    use RefreshDatabase;

    private User $titular;
    private Startup $startup;
    private string $tokenAgencia;
    private string $rota;
    private array $credenciais = ['login' => 'cliente', 'password' => 'senha-forte-123'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->titular = $this->agencia('primeira');
        $this->startup = $this->startup($this->titular);
        $this->tokenAgencia = $this->titular->createToken('teste')->plainTextToken;
        $this->rota = "/api/startups/{$this->startup->id}/portal-acesso";
        $this->usar($this->tokenAgencia);
    }

    private function agencia(string $nome): User
    {
        $plano = Plano::firstOrCreate(['nome' => 'Pro'], ['preco' => 99, 'limite_startups' => 10,
            'limite_membros_equipe' => 5, 'limite_minutas_ia' => 5]);
        $agencia = Agencia::create(['nome' => $nome, 'status_pagamento' => 'ativo', 'plano_id' => $plano->id]);
        $user = User::create(['name' => $nome, 'email' => "$nome@agencia.com", 'password' => Hash::make('senha-agencia'),
            'perfil_acesso' => 'consultor_admin', 'agencia_id' => $agencia->id]);
        $agencia->update(['owner_id' => $user->id]);
        return $user;
    }

    private function startup(User $user): Startup
    {
        return Startup::create(['agencia_id' => $user->agencia_id, 'razao_social' => 'Mesmo nome',
            'cnpj' => fake()->unique()->numerify('##############')]);
    }

    private function usar(string $token): void
    {
        Auth::forgetGuards();
        $this->withToken($token);
    }

    private function entrar(): string
    {
        $this->usar('');
        return $this->postJson('/api/auth/login', $this->credenciais)->assertOk()
            ->assertJsonPath('tipo_acesso', 'portal_startup')->json('token');
    }

    public function test_criacao_hash_normalizacao_e_listagem_sem_n_mais_um(): void
    {
        $this->postJson($this->rota, ['login' => ' CLIENTE '] + $this->credenciais)
            ->assertCreated()->assertJsonPath('ativo', true)->assertJsonPath('login', 'cliente')->assertJsonMissingPath('password');
        $this->assertTrue(Hash::check($this->credenciais['password'], $this->startup->portalAcesso->password));
        $this->startup($this->titular);
        DB::enableQueryLog();
        $this->getJson('/api/startups')->assertOk()->assertJsonFragment(['existe' => false])
            ->assertJsonFragment(['existe' => true])->assertJsonMissingPath('0.portal_acesso.password');
        $consultas = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'startup_portal_acessos'));
        $this->assertCount(1, $consultas);
        $this->getJson("/api/startups/{$this->startup->id}")->assertJsonPath('portal_acesso.login', 'cliente');
    }

    public function test_login_aceita_maiusculas_na_criacao_edicao_e_autenticacao(): void
    {
        $dados = ['login' => 'Nestle.BR'] + $this->credenciais;
        $this->postJson($this->rota, $dados)->assertCreated()->assertJsonPath('login', 'nestle.br');
        $this->assertDatabaseHas('startup_portal_acessos', ['startup_id' => $this->startup->id, 'login' => 'nestle.br']);
        $outra = $this->startup($this->titular);
        $this->postJson("/api/startups/{$outra->id}/portal-acesso", ['login' => 'NESTLE.BR'] + $dados)
            ->assertUnprocessable()->assertJsonValidationErrors('login');

        $this->usar('');
        foreach (['Nestle.BR', 'nestle.br'] as $login) {
            $this->postJson('/api/auth/login', ['login' => $login] + $dados)->assertOk()->assertJsonPath('login', 'nestle.br');
        }
        $this->usar($this->tokenAgencia);
        $this->patchJson($this->rota, ['login' => ' NOVO.Br '])->assertOk()->assertJsonPath('login', 'novo.br');
    }

    public function test_exclusao_logica_e_fisica_revogam_tokens_e_liberam_login(): void
    {
        foreach ([false, true] as $fisica) {
            $startup = $this->startup($this->titular);
            $this->usar($this->tokenAgencia);
            $this->postJson("/api/startups/{$startup->id}/portal-acesso", $this->credenciais)->assertCreated();
            $perfil = $startup->portalAcesso;
            $tokens = [$this->entrar(), $this->entrar()];

            if ($fisica) {
                $startup->forceDelete();
                $this->assertDatabaseMissing('startups', ['id' => $startup->id]);
            } else {
                $this->usar($this->tokenAgencia);
                $this->deleteJson("/api/startups/{$startup->id}")->assertOk();
                $this->assertSoftDeleted($startup);
            }
            $this->assertDatabaseMissing('startup_portal_acessos', ['id' => $perfil->id]);
            $this->assertDatabaseMissing('personal_access_tokens', [
                'tokenable_type' => $perfil->getMorphClass(), 'tokenable_id' => $perfil->id,
            ]);
            foreach ($tokens as $token) {
                $this->usar($token);
                $this->getJson('/api/portal/acompanhamento')->assertUnauthorized();
            }
        }
        $this->usar($this->tokenAgencia);
        $this->postJson($this->rota, $this->credenciais)->assertCreated();
    }

    public function test_validacao_unicidade_e_perfil_unico(): void
    {
        foreach (['ab', 'x@y', 'cli ente', 'cli/ente'] as $login) {
            $this->postJson($this->rota, ['login' => $login] + $this->credenciais)->assertUnprocessable();
        }
        $this->postJson($this->rota, ['password' => 'curta'] + $this->credenciais)->assertUnprocessable();
        $this->postJson($this->rota, $this->credenciais)->assertCreated();
        $this->postJson($this->rota, ['login' => 'outro'] + $this->credenciais)->assertUnprocessable();
        $outra = $this->startup($this->titular);
        $this->postJson("/api/startups/{$outra->id}/portal-acesso", $this->credenciais)->assertUnprocessable();
        $this->putJson($this->rota, ['login' => 'cliente'])->assertOk();
    }

    public function test_politica_de_gestao_isola_agencias_e_permissao(): void
    {
        $externo = $this->agencia('segunda');
        $this->usar($externo->createToken('teste')->plainTextToken);
        $this->postJson($this->rota, $this->credenciais)->assertForbidden();
        $this->patchJson($this->rota, ['ativo' => false])->assertForbidden();
        $externo->update(['agencia_id' => $this->titular->agencia_id, 'perfil_acesso' => 'consultor_padrao', 'permissoes' => []]);
        $this->usar($externo->createToken('teste')->plainTextToken);
        $this->postJson($this->rota, $this->credenciais)->assertForbidden();
    }

    public function test_login_unificado_bloqueios_e_ultimo_acesso(): void
    {
        $this->postJson($this->rota, $this->credenciais)->assertCreated();
        $this->postJson('/api/auth/login', ['email' => $this->titular->email, 'password' => 'senha-agencia'])
            ->assertOk()->assertJsonPath('tipo_acesso', 'agencia');
        $this->postJson('/api/auth/login', ['identificador' => 'CLIENTE', 'password' => 'errada'])->assertUnauthorized();
        $token = $this->entrar();
        $this->assertNotNull($this->startup->portalAcesso->ultimo_acesso_em);
        $this->assertSame(['portal-startup'], $this->startup->portalAcesso->tokens()->first()->abilities);
        $this->titular->agencia->update(['status_pagamento' => 'suspenso']);
        $this->usar($token);
        $this->getJson('/api/portal/acompanhamento')->assertForbidden()->assertJsonPath('codigo_erro', 'PORTAL_DESATIVADO');
        $this->usar('');
        $this->postJson('/api/auth/login', $this->credenciais)->assertForbidden()->assertJsonPath('codigo_erro', 'PORTAL_DESATIVADO');
    }

    public function test_pausa_e_troca_de_senha_revogam_sessoes(): void
    {
        $this->postJson($this->rota, $this->credenciais)->assertCreated();
        foreach ([['ativo' => false], ['password' => 'nova-senha-segura']] as $mudanca) {
            $anterior = $this->entrar();
            $this->usar($this->tokenAgencia);
            $this->patchJson($this->rota, $mudanca)->assertOk();
            $this->usar($anterior);
            $this->getJson('/api/portal/acompanhamento')->assertUnauthorized();
            if (isset($mudanca['ativo'])) {
                $this->postJson('/api/auth/login', $this->credenciais)->assertForbidden()->assertJsonPath('codigo_erro', 'PORTAL_DESATIVADO');
                $this->usar($this->tokenAgencia);
                $this->patchJson($this->rota, ['ativo' => true])->assertOk();
            }
        }
        $this->postJson('/api/auth/login', $this->credenciais)->assertUnauthorized();
    }

    public function test_isolamento_das_rotas_e_logout(): void
    {
        $this->getJson('/api/portal/acompanhamento')->assertForbidden();
        $this->postJson($this->rota, $this->credenciais)->assertCreated();
        $token = $this->entrar();
        $this->usar($token);
        foreach (['startups', 'kanban', 'assinatura/atual', 'termos/meu-aceite', 'auth/perfil', 'equipe', 'editais'] as $rota) {
            $this->getJson("/api/$rota")->assertForbidden();
        }
        $this->postJson('/api/auth/logout')->assertOk();
        $this->usar($token);
        $this->getJson('/api/portal/acompanhamento')->assertUnauthorized();
    }

    public function test_card_criado_pelo_kanban_alimenta_portal_e_protege_quadro_da_agencia(): void
    {
        $quadro = $this->getJson('/api/kanban')->assertOk()->json('data');
        $this->assertEquals($this->titular->agencia_id, $quadro['agencia_id']);
        $id = $this->postJson('/api/kanban/cards', ['title' => 'Edital em preparação',
            'column_id' => $quadro['columns'][0]['id'], 'startup_id' => $this->startup->id])
            ->assertCreated()->assertJsonPath('data.startup_id', $this->startup->id)->json('data.id');
        $this->postJson($this->rota, $this->credenciais)->assertCreated();
        $this->usar($this->entrar());
        $this->getJson('/api/portal/acompanhamento')->assertOk()->assertJsonPath('processos.0.id', $id);
        $this->usar('');
        $this->getJson('/api/kanban?agencia_id='.$this->titular->agencia_id)
            ->assertUnauthorized()->assertDontSee('Edital em preparação');
        $this->deleteJson("/api/kanban/cards/$id")->assertUnauthorized();
    }

    public function test_apenas_cards_da_startup_e_quadro_da_mesma_agencia(): void
    {
        $outra = $this->startup($this->titular);
        $externa = $this->startup($this->agencia('externa'));
        foreach ([$this->startup, $outra, $externa] as $startup) {
            $quadro = KanbanBoard::create(['agencia_id' => $startup->agencia_id]);
            $coluna = $quadro->columns()->create(['title' => 'Em análise']);
            $quadro->cards()->create(['column_id' => $coluna->id, 'startup_id' => $startup->id,
                'title' => "Projeto {$startup->id}", 'description' => 'Nota privada', 'metadata' => ['valor' => 5000]]);
        }
        $quadro->cards()->create(['column_id' => $coluna->id, 'startup_id' => $this->startup->id, 'title' => 'Vínculo inconsistente']);
        $this->postJson($this->rota, $this->credenciais)->assertCreated();
        $this->usar($this->entrar());
        $this->getJson("/api/portal/acompanhamento?startup_id={$outra->id}&agencia_id={$externa->agencia_id}")
            ->assertOk()->assertJsonCount(1, 'processos')->assertJsonPath('processos.0.titulo', "Projeto {$this->startup->id}")
            ->assertJsonPath('resumo.total_processos', 1)->assertJsonPath('resumo.por_etapa.Em análise', 1)
            ->assertJsonMissingPath('processos.0.metadata')->assertDontSee('Nota privada');
    }
}
