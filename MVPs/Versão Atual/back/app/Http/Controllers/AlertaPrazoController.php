<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Startup;
use App\Models\AlertaPrazo;
use App\Services\AlertaPrazoService;
use Exception;

class AlertaPrazoController extends Controller
{
    protected AlertaPrazoService $prazoService;

    public function __construct(AlertaPrazoService $prazoService)
    {
        $this->prazoService = $prazoService;
    }

    /**
     * RF-47 / EDTAN-171: Lista os alertas de prazos críticos de editais
     * GET /api/alertas/prazos-criticos
     */
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $startupId = $request->input('startup_id') ? (int) $request->input('startup_id') : null;
        $apenasNaoLidos = $request->boolean('apenas_nao_lidos');
        $nivelUrgencia = $request->input('nivel_urgencia');

        $alertas = $this->prazoService->listarAlertas($usuario, $startupId, $apenasNaoLidos, $nivelUrgencia);
        $totalNaoLidos = $this->prazoService->contarNaoLidos($usuario, $startupId);

        return response()->json([
            'sucesso' => true,
            'total_nao_lidos' => $totalNaoLidos,
            'dados' => $alertas
        ]);
    }

    /**
     * RF-47 / EDTAN-171: Dispara varredura de editais para identificação de prazos críticos
     * POST /api/alertas/prazos-criticos/verificar
     */
    public function verificar(Request $request): JsonResponse
    {
        $request->validate([
            'startup_id' => 'nullable|exists:startups,id',
            'dias_maximos' => 'nullable|integer|min:1|max:90'
        ]);

        try {
            $usuario = $request->user();
            $startup = null;
            if ($request->filled('startup_id')) {
                $startup = Startup::find($request->input('startup_id'));
            }

            $diasMaximos = (int) $request->input('dias_maximos', 15);
            $enviarEmail = $request->boolean('enviar_email', false);
            $alertas = $this->prazoService->verificarEGerarAlertasPrazos($usuario, $startup, $diasMaximos, $enviarEmail);
            $totalNaoLidos = $this->prazoService->contarNaoLidos($usuario, $startup?->id);

            return response()->json([
                'sucesso' => true,
                'mensagem' => 'Varredura de prazos críticos concluída com sucesso.',
                'total_gerados' => count($alertas),
                'total_nao_lidos' => $totalNaoLidos,
                'dados' => $alertas
            ]);
        } catch (Exception $e) {
            return response()->json([
                'sucesso' => false,
                'mensagem' => 'Falha ao verificar prazos críticos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * RF-47 / EDTAN-171: Retorna contador de alertas não lidos de prazos
     * GET /api/alertas/prazos-criticos/contador
     */
    public function contador(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $startupId = $request->input('startup_id') ? (int) $request->input('startup_id') : null;
        $totalNaoLidos = $this->prazoService->contarNaoLidos($usuario, $startupId);

        return response()->json([
            'sucesso' => true,
            'total_nao_lidos' => $totalNaoLidos
        ]);
    }

    /**
     * RF-47 / EDTAN-171: Marca um alerta específico de prazo como lido
     * PATCH /api/alertas/prazos-criticos/{id}/lido
     */
    public function marcarLido(Request $request, int $id): JsonResponse
    {
        try {
            $alerta = $this->prazoService->marcarComoLido($id, $request->user());

            return response()->json([
                'sucesso' => true,
                'mensagem' => 'Alerta de prazo marcado como lido.',
                'alerta' => $alerta
            ]);
        } catch (Exception $e) {
            return response()->json([
                'sucesso' => false,
                'mensagem' => 'Alerta não encontrado ou não autorizado.'
            ], 404);
        }
    }

    /**
     * RF-47 / EDTAN-171: Dispara e-mail de alerta de prazo crítico manualmente
     * POST /api/alertas/prazos-criticos/{id}/enviar-email
     */
    public function enviarEmail(Request $request, int $id): JsonResponse
    {
        try {
            $alerta = AlertaPrazo::where('id', $id)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            $enviado = $this->prazoService->enviarEmailAlertaPrazo($request->user(), $alerta);

            return response()->json([
                'sucesso' => $enviado,
                'mensagem' => $enviado ? 'E-mail de alerta de prazo enviado com sucesso.' : 'Falha ao despachar e-mail.'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'sucesso' => false,
                'mensagem' => 'Alerta não encontrado ou não autorizado.'
            ], 404);
        }
    }

    /**
     * RF-47 / EDTAN-171: Marca todos os alertas de prazos do usuário como lidos
     * POST /api/alertas/prazos-criticos/marcar-todos-lidos
     */
    public function marcarTodosLidos(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $startupId = $request->input('startup_id') ? (int) $request->input('startup_id') : null;

        $totalAtualizados = $this->prazoService->marcarTodosComoLidos($usuario, $startupId);

        return response()->json([
            'sucesso' => true,
            'mensagem' => "{$totalAtualizados} alertas de prazos marcados como lidos.",
            'total_atualizados' => $totalAtualizados
        ]);
    }
}

