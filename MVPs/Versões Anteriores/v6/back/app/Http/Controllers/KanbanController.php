<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\KanbanService;
use Exception;

class KanbanController extends Controller
{
    protected KanbanService $kanbanService;

    public function __construct(KanbanService $kanbanService)
    {
        $this->kanbanService = $kanbanService;
    }

    /**
     * Retorna o quadro Kanban completo com colunas e cards ordenados.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $agenciaId = $request->query('agencia_id');
            $startupName = $request->query('startup_name');
            $board = $this->kanbanService->getOrCreateBoard(
                $agenciaId ? (int)$agenciaId : null,
                $startupName ? trim($startupName) : null
            );

            return response()->json([
                'status' => 'sucesso',
                'data' => $board
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'erro',
                'mensagem' => 'Erro ao carregar o quadro Kanban.',
                'detalhe' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria um novo card no quadro (avulso ou vinculado a um edital).
     */
    public function storeCard(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'board_id' => 'nullable|integer|exists:kanban_boards,id',
            'column_id' => 'nullable|integer|exists:kanban_columns,id',
            'edital_id' => 'nullable|integer|exists:editals,id',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'startup_name' => 'nullable|string|max:255',
            'metadata' => 'nullable|array'
        ]);

        if (empty($dados['title']) && empty($dados['edital_id'])) {
            return response()->json([
                'status' => 'erro',
                'mensagem' => 'O título do card ou o edital_id deve ser informado.'
            ], 422);
        }

        try {
            $card = $this->kanbanService->createCard($dados);
            $card->load('edital');

            return response()->json([
                'status' => 'sucesso',
                'mensagem' => 'Card criado com sucesso!',
                'data' => $card
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'erro',
                'mensagem' => 'Falha ao criar card no Kanban.',
                'detalhe' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Movimenta um card para uma nova coluna ou reordena na mesma coluna.
     */
    public function moveCard(Request $request, int $id): JsonResponse
    {
        $dados = $request->validate([
            'target_column_id' => 'required|integer|exists:kanban_columns,id',
            'prev_position' => 'nullable|numeric',
            'next_position' => 'nullable|numeric'
        ]);

        try {
            $card = $this->kanbanService->moveCard(
                $id,
                (int)$dados['target_column_id'],
                isset($dados['prev_position']) ? (float)$dados['prev_position'] : null,
                isset($dados['next_position']) ? (float)$dados['next_position'] : null
            );

            return response()->json([
                'status' => 'sucesso',
                'mensagem' => 'Card movimentado com sucesso!',
                'data' => $card
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'erro',
                'mensagem' => 'Falha ao movimentar card.',
                'detalhe' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove um card do Kanban.
     */
    public function destroyCard(int $id): JsonResponse
    {
        try {
            $this->kanbanService->deleteCard($id);

            return response()->json([
                'status' => 'sucesso',
                'mensagem' => 'Card removido com sucesso!'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'erro',
                'mensagem' => 'Falha ao remover card.',
                'detalhe' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza dados de texto, anotações e anexos do Card.
     */
    public function updateCard(Request $request, int $id): JsonResponse
    {
        try {
            $card = \App\Models\KanbanCard::findOrFail($id);

            $dados = $request->validate([
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'metadata' => 'nullable|array',
                'arquivo' => 'nullable|file|max:10240' // até 10MB
            ]);

            if (isset($dados['title'])) $card->title = $dados['title'];
            if (isset($dados['description'])) $card->description = $dados['description'];

            $metaAtual = $card->metadata ?? [];

            // Se enviou metadados de texto (anotações, valores, etc)
            if (isset($dados['metadata'])) {
                $metaAtual = array_merge($metaAtual, $dados['metadata']);
            }

            // Se fez upload de arquivo
            if ($request->hasFile('arquivo')) {
                $file = $request->file('arquivo');
                $nomeOriginal = $file->getClientOriginalName();
                $caminho = $file->store('kanban_anexos', 'public');

                $anexosAtuais = $metaAtual['anexos'] ?? [];
                $anexosAtuais[] = [
                    'id' => uniqid(),
                    'nome' => $nomeOriginal,
                    'caminho' => '/storage/' . $caminho,
                    'tamanho' => $file->getSize(),
                    'enviado_em' => now()->toIso8601String()
                ];
                $metaAtual['anexos'] = $anexosAtuais;
            }

            $card->metadata = $metaAtual;
            $card->save();

            $cardAtualizado = $card->load('edital');

            return response()->json([
                'status' => 'sucesso',
                'mensagem' => 'Card atualizado com sucesso!',
                'data' => $cardAtualizado,
                'card' => $cardAtualizado
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'erro',
                'mensagem' => 'Falha ao atualizar card.',
                'detalhe' => $e->getMessage()
            ], 500);
        }
    }
}
