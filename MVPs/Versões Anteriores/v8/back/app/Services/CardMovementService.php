<?php

namespace App\Services;

use App\Models\KanbanCard;
use App\Models\KanbanColumn;
use App\Events\CardMovedToProposal;

class CardMovementService
{
    /**
     * Move o card e recalcula sua posição no quadro via Fractional Indexing.
     */
    public function move(KanbanCard $card, int $targetColumnId, ?float $prevPosition = null, ?float $nextPosition = null): KanbanCard
    {
        $originalColumnId = $card->column_id;

        // Calcula a nova posição baseada nos vizinhos
        $newPosition = $this->calculateNewPosition($prevPosition, $nextPosition);

        $card->column_id = $targetColumnId;
        $card->position = $newPosition;
        $card->save();

        // Verifica se mudou para a coluna de geração de proposta / elaboração
        $targetColumn = KanbanColumn::find($targetColumnId);
        
        if ($targetColumn && $originalColumnId !== $targetColumnId) {
            $colTitle = mb_strtolower($targetColumn->title);
            if (str_contains($colTitle, 'proposta') || str_contains($colTitle, 'elaboração')) {
                // Dispara o evento de proposta caso haja listeners registrados
                event(new CardMovedToProposal($card));
            }
        }

        return $card;
    }

    /**
     * Lógica de Fractional Indexing
     */
    private function calculateNewPosition(?float $prevPosition, ?float $nextPosition): float
    {
        $step = 65536.0; // Gap inicial padrão

        if ($prevPosition === null && $nextPosition === null) {
            return $step;
        }

        if ($prevPosition === null) {
            return $nextPosition / 2.0;
        }

        if ($nextPosition === null) {
            return $prevPosition + $step;
        }

        return ($prevPosition + $nextPosition) / 2.0;
    }
}
