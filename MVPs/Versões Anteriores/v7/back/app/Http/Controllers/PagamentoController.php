<?php

namespace App\Http\Controllers;

use App\Adapters\AbacatePayAdapter;
use Illuminate\Http\Request;
use App\Services\AbacatePayService;

class PagamentoController extends Controller
{
    public function recuperarLinkPagamento(Request $requisicao, AbacatePayService $servicoAbacatePay, AbacatePayAdapter $adapter)
    {
        $usuario = $requisicao->user();
        $agencia = $usuario ? $usuario->agencia : null;

        if (!$agencia) {
            return response()->json(['message' => 'Agência não encontrada para este usuário.', 'erro' => 'Agência não encontrada para este usuário.'], 404);
        }

        if ($agencia->isPagamentoAtivo()) {
            return response()->json(['message' => 'Sua assinatura já está ativa.', 'erro' => 'Sua assinatura já está ativa.'], 400);
        }

        $plano = $agencia->plano;

        $cobranca = $servicoAbacatePay->gerarCobranca($agencia, $usuario, $plano);
        $dadosCobranca = $adapter->normalizarRetornoCobranca($cobranca);

        $agencia->url_checkout = $dadosCobranca['url_checkout'];
        if ($dadosCobranca['id_transacao']) {
            $agencia->id_transacao = $dadosCobranca['id_transacao'];
        }
        $agencia->save();

        return response()->json([
            'url_checkout' => $dadosCobranca['url_checkout'],
        ], 200);
    }
}
