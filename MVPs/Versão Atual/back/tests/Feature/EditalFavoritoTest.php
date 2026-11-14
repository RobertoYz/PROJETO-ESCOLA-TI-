<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Agencia;
use App\Models\Plano;
use App\Models\Startup;
use App\Models\Edital;
use App\Models\EditalFavorito;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EditalFavoritoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private Edital $editalA;
    private Edital $editalB;
    private Startup $startup;

    protected function setUp(): void
    {
        parent::setUp();

        $plano = Plano::create([
            'nome' => 'Plano Inovação',
            'preco' => 197.00
        ]);

        $agencia = Agencia::create([
            'nome' => 'Agência Piloto',
            'plano_id' => $plano->id,
            'status_pagamento' => 'ativo'
        ]);

        $this->usuario = User::create([
            'name' => 'Consultor Teste',
            'email' => 'consultor.teste@anjo.com.br',
            'password' => bcrypt('Senha@12345'),
            'agencia_id' => $agencia->id
        ]);

        $this->startup = Startup::create([
            'agencia_id' => $agencia->id,
            'cnpj' => '11.222.333/0001-44',
            'razao_social' => 'Startup Alfa Tecnologia Ltda',
            'nome_fantasia' => 'Alfa Tech'
        ]);

        $this->editalA = Edital::create([
            'title' => 'Edital FINEP Subvenção 2026',
            'fonte' => 'FINEP',
            'status' => 'active'
        ]);

        $this->editalB = Edital::create([
            'title' => 'Programa FAPESC Inovação SC',
            'fonte' => 'FAPESC',
            'status' => 'active'
        ]);
    }

    /**
     * RF-11: Rota de favoritar exige autenticação Sanctum.
     */
    public function test_favoritar_sem_autenticacao_retorna_401(): void
    {
        $response = $this->postJson("/api/editais/{$this->editalA->id}/favoritar");
        $response->assertStatus(401);
    }

    /**
     * RF-11: Alternância (toggle) de favorito adiciona e remove do banco de dados.
     */
    public function test_toggle_favorito_adiciona_e_remove_do_banco(): void
    {
        // 1. Favoritar pela primeira vez -> adiciona
        $respAdiciona = $this->actingAs($this->usuario, 'sanctum')
            ->postJson("/api/editais/{$this->editalA->id}/favoritar", [
                'startup_id' => $this->startup->id
            ]);

        $respAdiciona->assertStatus(200)
            ->assertJson([
                'sucesso' => true,
                'favorito' => true,
                'message' => 'Edital salvo nos favoritos com sucesso.'
            ]);

        $this->assertDatabaseHas('edital_favoritos', [
            'user_id' => $this->usuario->id,
            'edital_id' => $this->editalA->id,
            'startup_id' => $this->startup->id
        ]);

        // 2. Chamar novamente -> remove (toggle)
        $respRemove = $this->actingAs($this->usuario, 'sanctum')
            ->postJson("/api/editais/{$this->editalA->id}/favoritar", [
                'startup_id' => $this->startup->id
            ]);

        $respRemove->assertStatus(200)
            ->assertJson([
                'sucesso' => true,
                'favorito' => false,
                'message' => 'Edital removido dos favoritos com sucesso.'
            ]);

        $this->assertDatabaseMissing('edital_favoritos', [
            'user_id' => $this->usuario->id,
            'edital_id' => $this->editalA->id
        ]);
    }

    /**
     * RF-11: Listagem /api/editais identifica edital favoritado pelo usuário.
     */
    public function test_listar_editais_identifica_status_de_favorito_para_usuario(): void
    {
        EditalFavorito::create([
            'user_id' => $this->usuario->id,
            'edital_id' => $this->editalA->id,
            'startup_id' => $this->startup->id
        ]);

        $response = $this->actingAs($this->usuario, 'sanctum')
            ->getJson("/api/editais?startup_id={$this->startup->id}");

        $response->assertStatus(200);

        $editais = collect($response->json());
        $favA = $editais->firstWhere('id', $this->editalA->id);
        $favB = $editais->firstWhere('id', $this->editalB->id);

        $this->assertNotNull($favA);
        $this->assertTrue($favA['favorite']);

        $this->assertNotNull($favB);
        $this->assertFalse($favB['favorite']);
    }

    /**
     * RF-11: Filtro ?favoritos=1 retorna exclusivamente os editais favoritados.
     */
    public function test_listar_editais_com_filtro_favoritos_retorna_apenas_favoritados(): void
    {
        EditalFavorito::create([
            'user_id' => $this->usuario->id,
            'edital_id' => $this->editalA->id,
            'startup_id' => $this->startup->id
        ]);

        $response = $this->actingAs($this->usuario, 'sanctum')
            ->getJson("/api/editais?favoritos=1");

        $response->assertStatus(200);

        $editais = $response->json();
        $this->assertCount(1, $editais);
        $this->assertEquals($this->editalA->id, $editais[0]['id']);
        $this->assertTrue($editais[0]['favorite']);
    }

    /**
     * RF-11: Isolamento de dados: favoritos de um usuário não aparecem para outro usuário.
     */
    public function test_favoritos_sao_isolados_entre_usuarios(): void
    {
        EditalFavorito::create([
            'user_id' => $this->usuario->id,
            'edital_id' => $this->editalA->id
        ]);

        $outroUsuario = User::create([
            'name' => 'Outro Consultor',
            'email' => 'outro@anjo.com.br',
            'password' => bcrypt('Senha@12345'),
            'agencia_id' => $this->usuario->agencia_id
        ]);

        $response = $this->actingAs($outroUsuario, 'sanctum')
            ->getJson("/api/editais");

        $response->assertStatus(200);

        $editais = collect($response->json());
        $favA = $editais->firstWhere('id', $this->editalA->id);

        // Para o outro usuário, o edital A NÃO deve estar favoritado
        $this->assertFalse($favA['favorite']);
    }
}
