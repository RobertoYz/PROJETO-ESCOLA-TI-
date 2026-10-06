<?php

namespace App\Services;

use App\Models\KanbanBoard;
use App\Models\KanbanColumn;
use App\Models\KanbanCard;
use App\Models\Edital;
use Illuminate\Support\Facades\DB;

class KanbanService
{
    protected CardMovementService $movementService;

    public function __construct(CardMovementService $movementService)
    {
        $this->movementService = $movementService;
    }

    /**
     * Retorna o quadro padrão da agência com suas colunas e cartões (com dados do edital).
     * Se o quadro ou as colunas não existirem, cria a estrutura inicial padrão.
     */
    public function getOrCreateBoard(?int $agenciaId = null): KanbanBoard
    {
        $query = KanbanBoard::query();
        if ($agenciaId) {
            $query->where('agencia_id', $agenciaId);
        }

        $board = $query->with(['columns.cards.edital'])->first();

        if (!$board) {
            $board = DB::transaction(function () use ($agenciaId) {
                $newBoard = KanbanBoard::create([
                    'agencia_id' => $agenciaId,
                    'title' => 'Pipeline de Editais'
                ]);

                $colunasPadrao = [
                    ['title' => 'Backlog', 'slug' => 'backlog', 'position' => 10000.0],
                    ['title' => 'A fazer', 'slug' => 'todo', 'position' => 20000.0],
                    ['title' => 'Em elaboração', 'slug' => 'doing', 'position' => 30000.0],
                    ['title' => 'Bloqueado', 'slug' => 'blocked', 'position' => 40000.0],
                    ['title' => 'Pronto para Submissão', 'slug' => 'review', 'position' => 50000.0],
                    ['title' => 'Submetido', 'slug' => 'done', 'position' => 60000.0],
                ];

                foreach ($colunasPadrao as $col) {
                    $newBoard->columns()->create($col);
                }

                return $newBoard;
            });

            $board->load(['columns.cards.edital']);
        }

        return $board;
    }

    /**
     * Cria um novo cartão no quadro (opcionalmente vinculado a um edital existente).
     */
    public function createCard(array $dados): KanbanCard
    {
        $boardId = $dados['board_id'] ?? null;
        if (!$boardId) {
            $board = $this->getOrCreateBoard($dados['agencia_id'] ?? null);
            $boardId = $board->id;
        }

        // Se a coluna não for informada, coloca na primeira coluna (ex: Backlog)
        $columnId = $dados['column_id'] ?? null;
        if (!$columnId) {
            $firstCol = KanbanColumn::where('board_id', $boardId)->orderBy('position')->first();
            $columnId = $firstCol ? $firstCol->id : null;
        }

        $editalId = $dados['edital_id'] ?? null;
        $title = $dados['title'] ?? null;
        $description = $dados['description'] ?? null;
        $metadata = $dados['metadata'] ?? [];

        // Se tem edital vinculado, pré-preenche informações se estiverem vazias
        if ($editalId) {
            $edital = Edital::find($editalId);
            if ($edital) {
                if (!$title) {
                    $title = $edital->title;
                }
                if (!$description) {
                    $description = $edital->objetivo;
                }
                $metadata = array_merge([
                    'fonte' => $edital->fonte,
                    'max_budget' => $edital->max_budget,
                    'deadline' => $edital->deadline,
                    'ai_match' => $edital->ai_match,
                    'publico' => $edital->publico,
                ], $metadata);
            }
        }

        // Determina posição inicial (último da coluna + 65536.0)
        $lastCard = KanbanCard::where('column_id', $columnId)->orderByDesc('position')->first();
        $position = $lastCard ? $lastCard->position + 65536.0 : 65536.0;

        return KanbanCard::create([
            'board_id' => $boardId,
            'column_id' => $columnId,
            'edital_id' => $editalId,
            'title' => $title ?: 'Novo Card',
            'description' => $description,
            'startup_name' => $dados['startup_name'] ?? 'Startup X',
            'position' => $position,
            'metadata' => $metadata
        ]);
    }

    /**
     * Move o cartão para outra coluna / reordena usando Fractional Indexing.
     */
    public function moveCard(int $cardId, int $targetColumnId, ?float $prevPosition = null, ?float $nextPosition = null): KanbanCard
    {
        $card = KanbanCard::findOrFail($cardId);
        return $this->movementService->move($card, $targetColumnId, $prevPosition, $nextPosition);
    }

    /**
     * Remove um cartão.
     */
    public function deleteCard(int $cardId): bool
    {
        $card = KanbanCard::findOrFail($cardId);
        return $card->delete();
    }
}
