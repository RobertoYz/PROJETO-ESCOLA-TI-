<?php

namespace App\Http\Controllers;

use App\Adapters\AbacatePayAdapter;
use App\Models\Plano;
use App\Services\AbacatePayService;
use Illuminate\Http\Request;
use Carbon\Carbon;
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
            'limite_membros_equipe as limite_usuarios',
            'limite_minutas_ia'
        )->get();
        return response()->json($planos, 200);
    }

    public function selecionarPlano(Request $requisicao, AbacatePayService $servicoAbacatePay, AbacatePayAdapter $adapter)
    {
        $requisicao->validate([
            'plano_id' => 'required|exists:planos,id',
        ]);

        $usuario = $requisicao->user();
        $agencia = $usuario ? $usuario->agencia : null;

        if (!$agencia) {
            return response()->json(['message' => 'Agência não encontrada para este usuário.'], 404);
        }

        $plano = Plano::findOrFail($requisicao->plano_id);

        $agencia->plano_id = $plano->id;
        $agencia->save();

        try {
            $cobranca = $servicoAbacatePay->gerarCobranca($agencia, $usuario, $plano);
            $dadosCobranca = $adapter->normalizarRetornoCobranca($cobranca);

            $agencia->url_checkout = $dadosCobranca['url_checkout'];
            $agencia->id_transacao = $dadosCobranca['id_transacao'];
            $agencia->pagamento_expira_em = Carbon::now()->addMinutes(60);
            $agencia->save();

            return response()->json([
                'message' => 'Plano selecionado com sucesso.',
                'plano' => $plano->nome,
                'url_checkout' => $dadosCobranca['url_checkout'],
            ], 200);
        } catch (Exception $excecao) {
            return response()->json([
                'message' => 'Erro ao gerar link de pagamento para o plano selecionado.',
                'detalhe' => $excecao->getMessage(),
            ], 500);
        }
    }
}
