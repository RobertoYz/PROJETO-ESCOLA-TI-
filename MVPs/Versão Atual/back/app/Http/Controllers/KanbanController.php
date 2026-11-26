<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use App\Services\KanbanService;
use Exception;

class KanbanController extends Controller
{
    public const MAX_ANEXO_KB = 10240; // 10 MB por arquivo
    public const MAX_ANEXO_BYTES = 10 * 1024 * 1024;
    public const MAX_ANEXOS_CARD_BYTES = 25 * 1024 * 1024; // 25 MB acumulados por card

    protected KanbanService $kanbanService;

    public function __construct(KanbanService $kanbanService)
    {
        $this->kanbanService = $kanbanService;
    }

    protected function autorizarEscritaKanban(Request $request): ?JsonResponse
    {
        $usuario = $request->user('sanctum') ?? $request->user();
        if ($usuario && !$usuario->temPermissao(\App\Enums\Permissao::KANBAN_GERENCIAR)) {
            return response()->json([
                'status' => 'erro',
                'message' => 'Você não possui permissão para criar, editar ou excluir cards no Pipeline Kanban.',
            ], 403);
        }

        $agenciaId = $usuario?->agencia_id;
        $quadros = \App\Models\KanbanBoard::where('agencia_id', $agenciaId)->select('id');
        $quadroId = $request->integer('board_id') ?: null;
        if ($request->route('id')) {
            $card = \App\Models\KanbanCard::whereIn('board_id', $quadros)->findOrFail($request->route('id'));
            $quadroId = $card->board_id;
        }
        if ($quadroId) {
            abort_unless((clone $quadros)->whereKey($quadroId)->exists(), 404);
        }
        $colunaId = $request->integer('target_column_id') ?: $request->integer('column_id');
        if ($colunaId) {
            $coluna = \App\Models\KanbanColumn::whereIn('board_id', $quadros)->findOrFail($colunaId);
            abort_if($quadroId && $coluna->board_id != $quadroId, 422);
            $request->merge(['board_id' => $coluna->board_id]);
        }

        return null;
    }

    /**
     * Retorna o quadro Kanban completo com colunas e cards ordenados.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $agenciaId = $request->user('sanctum')?->agencia_id;
            $startupName = $request->query('startup_name');
            $board = $this->kanbanService->getOrCreateBoard(
                $agenciaId ? (int)$agenciaId : null,
                $startupName ? trim($startupName) : null,
                $request->integer('startup_id') ?: null
            );

            return response()->json([
                'status' => 'sucesso',
                'data' => $board
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'erro',
                'message' => 'Erro ao carregar o quadro Kanban.',
                'detalhe' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria um novo card no quadro (avulso ou vinculado a um edital).
     */
    public function storeCard(Request $request): JsonResponse
    {
        if ($bloqueio = $this->autorizarEscritaKanban($request)) {
            return $bloqueio;
        }

        $dados = $request->validate([
            'board_id' => 'nullable|integer|exists:kanban_boards,id',
            'column_id' => 'nullable|integer|exists:kanban_columns,id',
            'edital_id' => 'nullable|integer|exists:editals,id',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'startup_id' => 'nullable|integer',
            'startup_name' => 'nullable|string|max:255',
            'metadata' => 'nullable|array'
        ]);

        $dados['agencia_id'] = $request->user('sanctum')?->agencia_id;
        if (isset($dados['startup_id'])) {
            $startup = \App\Models\Startup::where('agencia_id', $dados['agencia_id'])->findOrFail($dados['startup_id']);
            $dados['startup_name'] = $startup->nome_fantasia ?: $startup->razao_social;
        }

        if (empty($dados['title']) && empty($dados['edital_id'])) {
            return response()->json([
                'status' => 'erro',
                'message' => 'O título do card ou o edital_id deve ser informado.'
            ], 422);
        }

        try {
            $card = $this->kanbanService->createCard($dados);
            $card->load('edital');

            return response()->json([
                'status' => 'sucesso',
                'message' => 'Card criado com sucesso!',
                'data' => $card
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'erro',
                'message' => 'Falha ao criar card no Kanban.',
                'detalhe' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Movimenta um card para uma nova coluna ou reordena na mesma coluna.
     */
    public function moveCard(Request $request, int $id): JsonResponse
    {
        if ($bloqueio = $this->autorizarEscritaKanban($request)) {
            return $bloqueio;
        }

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
                'message' => 'Card movimentado com sucesso!',
                'data' => $card
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'erro',
                'message' => 'Falha ao movimentar card.',
                'detalhe' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove um card do Kanban.
     */
    public function destroyCard(Request $request, int $id): JsonResponse
    {
        if ($bloqueio = $this->autorizarEscritaKanban($request)) {
            return $bloqueio;
        }

        try {
            $this->kanbanService->deleteCard($id);

            return response()->json([
                'status' => 'sucesso',
                'message' => 'Card removido com sucesso!'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'erro',
                'message' => 'Falha ao remover card.',
                'detalhe' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza dados de texto, anotações e anexos do Card.
     */
    public function updateCard(Request $request, int $id): JsonResponse
    {
        if ($bloqueio = $this->autorizarEscritaKanban($request)) {
            return $bloqueio;
        }

        try {
            $card = \App\Models\KanbanCard::findOrFail($id);

            $dados = $request->validate([
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'metadata' => 'nullable|array',
                'arquivo' => 'nullable|file|max:' . self::MAX_ANEXO_KB . '|mimes:pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg'
            ], [
                'arquivo.file' => 'O anexo enviado não é um arquivo válido.',
                'arquivo.max' => 'O arquivo excede o limite máximo permitido de 10 MB por anexo.',
                'arquivo.mimes' => 'Formato de arquivo não permitido. Envie PDF, DOC, DOCX, XLS, XLSX, CSV, TXT, PNG ou JPG.'
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
                $anexosAtuais = $metaAtual['anexos'] ?? [];
                $tamanhoAtualBytes = array_reduce($anexosAtuais, function ($carry, $item) {
                    return $carry + (int) ($item['tamanho'] ?? 0);
                }, 0);

                $tamanhoNovo = (int) $file->getSize();
                if (($tamanhoAtualBytes + $tamanhoNovo) > self::MAX_ANEXOS_CARD_BYTES) {
                    $disponivelMb = max(0, (self::MAX_ANEXOS_CARD_BYTES - $tamanhoAtualBytes) / (1024 * 1024));
                    return response()->json([
                        'status' => 'erro',
                        'message' => sprintf(
                            'Limite total de documentação do card (25 MB) excedido. Espaço disponível: %.2f MB.',
                            $disponivelMb
                        )
                    ], 422);
                }

                $nomeOriginal = $file->getClientOriginalName();
                $caminho = $file->store('kanban_anexos', 'public');

                $anexosAtuais[] = [
                    'id' => uniqid(),
                    'nome' => $nomeOriginal,
                    'caminho' => '/storage/' . $caminho,
                    'tamanho' => $tamanhoNovo,
                    'enviado_em' => now()->toIso8601String()
                ];
                $metaAtual['anexos'] = $anexosAtuais;
            }

            $card->metadata = $metaAtual;
            $card->save();

            $cardAtualizado = $card->load('edital');

            return response()->json([
                'status' => 'sucesso',
                'message' => 'Card atualizado com sucesso!',
                'data' => $cardAtualizado,
                'card' => $cardAtualizado
            ]);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $firstMessage = collect($errors)->flatten()->first() ?? 'Dados inválidos para atualização do card.';

            return response()->json([
                'status' => 'erro',
                'message' => $firstMessage,
                'errors' => $errors
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'erro',
                'message' => 'Falha ao atualizar card.',
                'detalhe' => $e->getMessage()
            ], 500);
        }
    }
}
