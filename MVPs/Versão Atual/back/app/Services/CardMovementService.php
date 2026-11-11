<?php

namespace App\Services;

use App\Models\KanbanCard;
use App\Models\KanbanColumn;
use App\Events\CardMovedToProposal;

class CardMovementService
{
    /**
     * Espaçamento numérico padrão para inserção em Fractional Indexing.
     * Permite múltiplas inserções intermediárias sem colisões.
     */
    private const INTERVALO_POSICIONAMENTO_PADRAO = 65536.0;

    /**
     * Move o cartão e recalcula sua posição no quadro via Fractional Indexing.
     */
    public function move(
        KanbanCard $cartaoAlvo, 
        int $colunaDestinoId, 
        ?float $posicaoCartaoAnterior = null, 
        ?float $posicaoCartaoPosterior = null
    ): KanbanCard {
        $colunaOrigemId = $cartaoAlvo->column_id;

        // Calcula a nova posição baseada nos vizinhos
        $novaPosicaoCalculada = $this->calculateNewPosition($posicaoCartaoAnterior, $posicaoCartaoPosterior);

        $cartaoAlvo->column_id = $colunaDestinoId;
        $cartaoAlvo->position = $novaPosicaoCalculada;
        $cartaoAlvo->save();

        // Verifica se mudou para a coluna de geração de proposta / elaboração
        $colunaDestino = KanbanColumn::find($colunaDestinoId);
        
        if ($colunaDestino && $colunaOrigemId !== $colunaDestinoId) {
            $tituloColunaMinusculo = mb_strtolower($colunaDestino->title);
            if (str_contains($tituloColunaMinusculo, 'proposta') || str_contains($tituloColunaMinusculo, 'elaboração')) {
                // Dispara o evento de proposta caso haja listeners registrados
                event(new CardMovedToProposal($cartaoAlvo));
            }
        }

        return $cartaoAlvo;
    }

    /**
     * Lógica de Fractional Indexing para reordenação sem reindexar a tabela inteira.
     */
    private function calculateNewPosition(?float $posicaoAnterior, ?float $posicaoPosterior): float
    {
        // Caso 1: Coluna vazia, assume o espaçamento padrão
        if ($posicaoAnterior === null && $posicaoPosterior === null) {
            return self::INTERVALO_POSICIONAMENTO_PADRAO;
        }

        // Caso 2: Inserido no topo absoluto da coluna (antes do primeiro card)
        if ($posicaoAnterior === null) {
            return $posicaoPosterior / 2.0;
        }

        // Caso 3: Inserido no final absoluto da coluna (após o último card)
        if ($posicaoPosterior === null) {
            return $posicaoAnterior + self::INTERVALO_POSICIONAMENTO_PADRAO;
        }

        // Caso 4: Inserido exatamente entre dois cards existentes
        return ($posicaoAnterior + $posicaoPosterior) / 2.0;
    }
}
