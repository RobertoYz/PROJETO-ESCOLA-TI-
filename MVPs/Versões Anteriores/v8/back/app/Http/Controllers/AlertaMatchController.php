<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Startup;
use App\Services\AlertaMatchService;
use Exception;

class AlertaMatchController extends Controller
{
    protected AlertaMatchService $alertaService;

    public function __construct(AlertaMatchService $alertaService)
    {
        $this->alertaService = $alertaService;
    }

    /**
     * RF-26 (EDTAN-172): Lista os alertas de match compatíveis do usuário
     * GET /api/alertas/match
     */
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $startupId = $request->input('startup_id') ? (int) $request->input('startup_id') : null;
        $apenasNaoLidos = $request->boolean('apenas_nao_lidos');

        $alertas = $this->alertaService->listarAlertas($usuario, $startupId, $apenasNaoLidos);
        $totalNaoLidos = $this->alertaService->contarNaoLidos($usuario, $startupId);

        return response()->json([
            'sucesso' => true,
            'total_nao_lidos' => $totalNaoLidos,
            'dados' => $alertas
        ]);
    }

    /**
     * RF-26 (EDTAN-172): Dispara a verificação inteligente de editais compatíveis e gera alertas
     * POST /api/alertas/verificar
     */
    public function verificar(Request $request): JsonResponse
    {
        $request->validate([
            'startup_id' => 'required|exists:startups,id'
        ]);

        try {
            $usuario = $request->user();
            $startup = Startup::findOrFail($request->input('startup_id'));

            $alertas = $this->alertaService->verificarEGerarAlertas($startup, $usuario, 70);
            $totalNaoLidos = $this->alertaService->contarNaoLidos($usuario, $startup->id);

            return response()->json([
                'sucesso' => true,
                'message' => 'Verificação de match concluída com sucesso.',
                'total_gerados' => count($alertas),
                'total_nao_lidos' => $totalNaoLidos,
                'dados' => $alertas
            ]);
        } catch (Exception $e) {
            return response()->json([
                'sucesso' => false,
                'message' => 'Erro ao processar alerta de match: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retorna a quantidade de alertas não lidos para exibição no ícone/sino
     * GET /api/alertas/contador
     */
    public function contador(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $startupId = $request->input('startup_id') ? (int) $request->input('startup_id') : null;
        $totalNaoLidosMatch = $this->alertaService->contarNaoLidos($usuario, $startupId);
        
        $prazosQuery = \App\Models\AlertaPrazo::where('user_id', $usuario->id)->where('lido', false);
        if ($startupId) {
            $prazosQuery->where(function ($q) use ($startupId) {
                $q->where('startup_id', $startupId)->orWhereNull('startup_id');
            });
        }
        $totalNaoLidosPrazos = $prazosQuery->count();
        $totalGeral = $totalNaoLidosMatch + $totalNaoLidosPrazos;

        return response()->json([
            'sucesso' => true,
            'total_nao_lidos' => $totalGeral,
            'detalhes' => [
                'match' => $totalNaoLidosMatch,
                'prazos' => $totalNaoLidosPrazos
            ]
        ]);
    }

    /**
     * Marca um alerta individual como lido
     * PATCH /api/alertas/{id}/lido
     */
    public function marcarLido(Request $request, int $id): JsonResponse
    {
        $usuario = $request->user();
        $atualizado = $this->alertaService->marcarComoLido($id, $usuario);

        if (!$atualizado) {
            return response()->json([
                'sucesso' => false,
                'message' => 'Alerta não encontrado ou não pertence ao usuário.'
            ], 404);
        }

        return response()->json([
            'sucesso' => true,
            'message' => 'Alerta marcado como lido.'
        ]);
    }

    /**
     * Marca todos os alertas como lidos
     * POST /api/alertas/marcar-todos-lidos
     */
    public function marcarTodosLidos(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $startupId = $request->input('startup_id') ? (int) $request->input('startup_id') : null;
        $afetados = $this->alertaService->marcarTodosComoLidos($usuario, $startupId);

        return response()->json([
            'sucesso' => true,
            'message' => 'Todos os alertas foram marcados como lidos.',
            'total_afetados' => $afetados
        ]);
    }
}
