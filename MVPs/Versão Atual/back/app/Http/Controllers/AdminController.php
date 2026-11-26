<?php

namespace App\Http\Controllers;

use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard(AdminService $service): JsonResponse
    {
        return response()->json([
            'status' => 'sucesso',
            'dados' => $service->obterMetricasDashboard(),
        ]);
    }

    public function listarAgencias(Request $request, AdminService $service): JsonResponse
    {
        $filtros = $request->only(['busca', 'status', 'plano_id', 'por_pagina']);
        $paginador = $service->listarAgencias($filtros);

        return response()->json([
            'status' => 'sucesso',
            'dados' => $paginador->items(),
            'paginacao' => [
                'total' => $paginador->total(),
                'por_pagina' => $paginador->perPage(),
                'pagina_atual' => $paginador->currentPage(),
                'ultima_pagina' => $paginador->lastPage(),
            ],
        ]);
    }

    public function obterAgencia(int $id, AdminService $service): JsonResponse
    {
        return response()->json([
            'status' => 'sucesso',
            'dados' => $service->obterDetalhesAgencia($id),
        ]);
    }

    public function alterarStatusAgencia(Request $request, int $id, AdminService $service): JsonResponse
    {
        $dados = $request->validate([
            'status' => 'required|string|in:ativo,suspenso,cancelado,pendente',
        ], [
            'status.required' => 'O novo status é obrigatório.',
            'status.in' => 'O status informado deve ser: ativo, suspenso, cancelado ou pendente.',
        ]);

        $agencia = $service->alterarStatusAgencia($id, $dados['status']);

        return response()->json([
            'status' => 'sucesso',
            'message' => "Status da agência '{$agencia->nome}' alterado para '{$agencia->status_pagamento}' com sucesso.",
            'dados' => $agencia,
        ]);
    }

    public function ajustarPlano(Request $request, int $id, AdminService $service): JsonResponse
    {
        $dados = $request->validate([
            'plano_id' => 'required|integer|exists:planos,id',
            'dias_extras' => 'nullable|integer|min:1|max:365',
        ], [
            'plano_id.required' => 'Selecione o plano desejado.',
            'plano_id.exists' => 'O plano selecionado não existe.',
        ]);

        $agencia = $service->ajustarPlanoAgencia($id, (int) $dados['plano_id'], isset($dados['dias_extras']) ? (int) $dados['dias_extras'] : null);

        return response()->json([
            'status' => 'sucesso',
            'message' => "Plano da agência '{$agencia->nome}' atualizado para '{$agencia->plano->nome}' com sucesso.",
            'dados' => $agencia,
        ]);
    }

    public function excluirAgencia(int $id, AdminService $service): JsonResponse
    {
        $service->excluirAgencia($id);

        return response()->json([
            'status' => 'sucesso',
            'message' => 'Agência e todos os seus recursos vinculados foram excluídos com sucesso.',
        ]);
    }

    public function statusRadares(AdminService $service): JsonResponse
    {
        return response()->json([
            'status' => 'sucesso',
            'dados' => $service->obterStatusRadares(),
        ]);
    }

    public function sincronizarRadar(string $fonte, AdminService $service): JsonResponse
    {
        try {
            $resultado = $service->sincronizarRadar($fonte);

            return response()->json([
                'status' => 'sucesso',
                'message' => "Sincronização do radar '{$fonte}' executada com sucesso.",
                'dados' => $resultado,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'erro',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'erro',
                'message' => 'Erro ao sincronizar radar: ' . $e->getMessage(),
            ], 500);
        }
    }
}
