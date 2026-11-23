<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\KanbanBoard;
use App\Models\KanbanCard;
use App\Models\KanbanColumn;
use App\Services\CardMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CardMovementServiceTest extends TestCase
{
    use RefreshDatabase;

    private CardMovementService $service;
    private KanbanBoard $board;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CardMovementService();
        $this->board = KanbanBoard::create(['title' => 'Pipeline de Editais']);
    }

    /**
     * RF-28: Testa movimentação e cálculo de posição inicial quando não há vizinhos.
     */
    public function test_move_sem_vizinhos_atribui_posicao_padrao(): void
    {
        $colunaOrigem = KanbanColumn::create(['board_id' => $this->board->id, 'title' => 'Triagem', 'slug' => 'backlog', 'position' => 1000]);
        $colunaDestino = KanbanColumn::create(['board_id' => $this->board->id, 'title' => 'Em Análise', 'slug' => 'analise', 'position' => 2000]);

        $card = KanbanCard::create([
            'board_id' => $this->board->id,
            'column_id' => $colunaOrigem->id,
            'title' => 'Edital FINEP Inovação',
            'position' => 1000.0
        ]);

        $cardMovido = $this->service->move($card, $colunaDestino->id, null, null);

        $this->assertEquals($colunaDestino->id, $cardMovido->column_id);
        $this->assertEquals(65536.0, $cardMovido->position);
    }

    /**
     * RF-28: Testa posicionamento entre dois cards vizinhos usando ponto médio exato (Fractional Indexing).
     */
    public function test_move_entre_dois_cards_calcula_ponto_medio_fracionario(): void
    {
        $coluna = KanbanColumn::create(['board_id' => $this->board->id, 'title' => 'Minuta', 'slug' => 'minuta', 'position' => 3000]);

        $card = KanbanCard::create([
            'board_id' => $this->board->id,
            'column_id' => $coluna->id,
            'title' => 'Edital FAPESC',
            'position' => 10000.0
        ]);

        $prevPos = 20000.0;
        $nextPos = 30000.0;

        $cardMovido = $this->service->move($card, $coluna->id, $prevPos, $nextPos);

        // Ponto médio entre 20000 e 30000 deve ser 25000.0
        $this->assertEquals(25000.0, $cardMovido->position);
    }

    /**
     * RF-28: Testa posicionamento no topo (antes do primeiro card).
     */
    public function test_move_para_o_topo_calcula_metade_da_primeira_posicao(): void
    {
        $coluna = KanbanColumn::create(['board_id' => $this->board->id, 'title' => 'Submetido', 'slug' => 'submetido', 'position' => 4000]);

        $card = KanbanCard::create([
            'board_id' => $this->board->id,
            'column_id' => $coluna->id,
            'title' => 'Edital Finep Submetido',
            'position' => 50000.0
        ]);

        $nextPos = 20000.0; // Primeiro card tem posição 20000.0
        $cardMovido = $this->service->move($card, $coluna->id, null, $nextPos);

        // Deve ser a metade da primeira posição: 10000.0
        $this->assertEquals(10000.0, $cardMovido->position);
    }
}
