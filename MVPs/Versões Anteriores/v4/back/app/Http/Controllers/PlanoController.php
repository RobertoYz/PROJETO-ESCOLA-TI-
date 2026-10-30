<?php

namespace App\Http\Controllers;

use App\Models\Plano;
use App\Services\AbacatePayService;
use Illuminate\Http\Request;
use Exception;

class PlanoController extends Controller
{
    public function listarPlanos()
    {
        $planos = Plano::select(
            'id',
            'nome',
            'preco',
            'preco as preco_mensal',
            'limite_startups',
            'limite_membros_equipe',
            'limite_membros_equipe as limite_usuarios'
        )->get();
        return response()->json($planos, 200);
    }

    public function selecionarPlano(Request $request, AbacatePayService $abacatePayService)
    {
        $request->validate([
            'plano_id' => 'required|exists:planos,id',
        ]);

        $usuario = $request->user();
        $agencia = $usuario->agencia;

        if (!$agencia) {
            return response()->json(['mensagem' => 'Agência não encontrada para este usuário.'], 404);
        }

        $plano = Plano::findOrFail($request->plano_id);

        $agencia->plano_id = $plano->id;
        $agencia->save();

        try {
            $checkoutUrl = $abacatePayService->gerarCobranca($agencia, $usuario, $plano);

            return response()->json([
                'mensagem' => 'Plano selecionado com sucesso.',
                'plano' => $plano->nome,
                'checkout_url' => $checkoutUrl,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'mensagem' => 'Erro ao gerar link de pagamento para o plano selecionado.',
                'detalhe' => $e->getMessage()
            ], 500);
        }
    }
}
