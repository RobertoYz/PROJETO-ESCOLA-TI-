<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\KanbanBoard;
use App\Models\KanbanColumn;
use App\Models\KanbanCard;
use App\Models\Edital;
use Illuminate\Foundation\Testing\RefreshDatabase;

class KanbanApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * RF-26: Teste de carregamento e inicialização automática do quadro Kanban com colunas padrão.
     */
    public function test_index_inicializa_quadro_e_colunas_padrao(): void
    {
        $response = $this->getJson('/api/kanban');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'sucesso'
            ])
            ->assertJsonStructure([
                'status',
                'data' => [
                    'id',
                    'title',
                    'columns'
                ]
            ]);

        $this->assertDatabaseHas('kanban_boards', [
            'title' => 'Pipeline de Editais'
        ]);

        // Verifica existência das colunas padrão do pipeline
        $this->assertDatabaseHas('kanban_columns', ['slug' => 'backlog']);
        $this->assertDatabaseHas('kanban_columns', ['slug' => 'doing']);
        $this->assertDatabaseHas('kanban_columns', ['slug' => 'done']);
    }

    /**
     * RF-27: Teste de criação de card avulso e vinculado a edital.
     */
    public function test_store_card_cria_card_com_sucesso(): void
    {
        $board = KanbanBoard::create(['title' => 'Pipeline de Editais']);
        $column = KanbanColumn::create([
            'board_id' => $board->id,
            'title' => 'Backlog',
            'slug' => 'backlog',
            'position' => 1000.0
        ]);

        $edital = Edital::create([
            'title' => 'Edital FINEP Mulheres Inovadoras',
            'fonte' => 'FINEP',
            'objetivo' => 'Apoio à liderança feminina em startups.',
            'status' => 'active'
        ]);

        $payload = [
            'board_id' => $board->id,
            'column_id' => $column->id,
            'edital_id' => $edital->id,
            'title' => 'Submissão Mulheres Inovadoras 2026',
            'startup_name' => 'BioTech Brasil'
        ];

        $response = $this->postJson('/api/kanban/cards', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'sucesso',
                'mensagem' => 'Card criado com sucesso!'
            ]);

        $this->assertDatabaseHas('kanban_cards', [
            'board_id' => $board->id,
            'column_id' => $column->id,
            'edital_id' => $edital->id,
            'title' => 'Submissão Mulheres Inovadoras 2026',
            'startup_name' => 'BioTech Brasil'
        ]);
    }

    /**
     * RF-27: Criação de card sem título e sem edital deve retornar 422.
     */
    public function test_store_card_sem_titulo_e_sem_edital_falha(): void
    {
        $response = $this->postJson('/api/kanban/cards', [
            'title' => '',
            'edital_id' => null
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'erro'
            ]);
    }

    /**
     * RF-28: Teste de movimentação de card entre colunas.
     */
    public function test_mover_card_atualiza_coluna_e_posicao(): void
    {
        $board = KanbanBoard::create(['title' => 'Pipeline Teste']);
        $colunaA = KanbanColumn::create(['board_id' => $board->id, 'title' => 'Coluna A', 'slug' => 'col-a', 'position' => 1000.0]);
        $colunaB = KanbanColumn::create(['board_id' => $board->id, 'title' => 'Coluna B', 'slug' => 'col-b', 'position' => 2000.0]);

        $card = KanbanCard::create([
            'board_id' => $board->id,
            'column_id' => $colunaA->id,
            'title' => 'Card Teste Movimento',
            'position' => 5000.0
        ]);

        $response = $this->patchJson("/api/kanban/cards/{$card->id}/mover", [
            'target_column_id' => $colunaB->id,
            'prev_position' => 10000.0,
            'next_position' => 20000.0
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'sucesso',
                'mensagem' => 'Card movimentado com sucesso!'
            ]);

        $this->assertDatabaseHas('kanban_cards', [
            'id' => $card->id,
            'column_id' => $colunaB->id,
            'position' => 15000.0
        ]);
    }

    /**
     * RF-27: Teste de atualização de anotações e metadados do card.
     */
    public function test_atualizar_dados_anexos_do_card(): void
    {
        $board = KanbanBoard::create(['title' => 'Pipeline Teste']);
        $coluna = KanbanColumn::create(['board_id' => $board->id, 'title' => 'Triagem', 'slug' => 'triagem', 'position' => 1000.0]);

        $card = KanbanCard::create([
            'board_id' => $board->id,
            'column_id' => $coluna->id,
            'title' => 'Card Inicial',
            'position' => 1000.0
        ]);

        $response = $this->postJson("/api/kanban/cards/{$card->id}/dados-anexos", [
            'title' => 'Card Título Atualizado',
            'description' => 'Anotações da reunião com os fundadores.',
            'metadata' => [
                'orcamento_solicitado' => '500.000,00',
                'prazo_interno' => '2026-12-15'
            ]
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'sucesso'
            ]);

        $cardAtualizado = $card->fresh();
        $this->assertEquals('Card Título Atualizado', $cardAtualizado->title);
        $this->assertEquals('Anotações da reunião com os fundadores.', $cardAtualizado->description);
        $this->assertEquals('500.000,00', $cardAtualizado->metadata['orcamento_solicitado']);
    }

    /**
     * RF-27: Teste de exclusão de card.
     */
    public function test_excluir_card_remove_do_banco(): void
    {
        $board = KanbanBoard::create(['title' => 'Pipeline Teste']);
        $coluna = KanbanColumn::create(['board_id' => $board->id, 'title' => 'Triagem', 'slug' => 'triagem', 'position' => 1000.0]);

        $card = KanbanCard::create([
            'board_id' => $board->id,
            'column_id' => $coluna->id,
            'title' => 'Card Para Excluir',
            'position' => 1000.0
        ]);

        $response = $this->deleteJson("/api/kanban/cards/{$card->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'sucesso',
                'mensagem' => 'Card removido com sucesso!'
            ]);

        $this->assertDatabaseMissing('kanban_cards', [
            'id' => $card->id
        ]);
    }
}
