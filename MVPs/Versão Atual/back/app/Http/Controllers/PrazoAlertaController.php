<?php

namespace App\Http\Controllers;

use App\Models\Startup;
use App\Services\PrazoAlertaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrazoAlertaController extends Controller
{
    protected PrazoAlertaService $prazoService;

    public function __construct(PrazoAlertaService $prazoService)
    {
        $this->prazoService = $prazoService;
    }

    /**
     * Retorna os alertas de prazos críticos para a startup ativa ou visão geral.
     */
    public function alertas(Request $request): JsonResponse
    {
        $startupNome = null;
        $startupId = $request->header('X-Startup-Id') ?? $request->query('startup_id');

        if ($startupId) {
            $startup = Startup::find($startupId);
            if ($startup) {
                $startupNome = $startup->nome_fantasia ?: $startup->razao_social;
            }
        }

        $dados = $this->prazoService->obterAlertas($startupNome);

        return response()->json([
            'status' => 'success',
            'startup_filtrada' => $startupNome,
            'resumo' => $dados['resumo'],
            'alertas' => $dados['itens']
        ]);
    }

    /**
     * Retorna apenas os contadores para alimentar badges ou sinos no frontend.
     */
    public function resumo(Request $request): JsonResponse
    {
        $startupNome = null;
        $startupId = $request->header('X-Startup-Id') ?? $request->query('startup_id');

        if ($startupId) {
            $startup = Startup::find($startupId);
            if ($startup) {
                $startupNome = $startup->nome_fantasia ?: $startup->razao_social;
            }
        }

        $dados = $this->prazoService->obterAlertas($startupNome);

        return response()->json([
            'status' => 'success',
            'resumo' => $dados['resumo']
        ]);
    }
}

