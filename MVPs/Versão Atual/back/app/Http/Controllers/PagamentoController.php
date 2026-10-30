<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AbacatePayService;

class PagamentoController extends Controller
{
    public function recuperarLinkPagamento(Request $requisicao, AbacatePayService $servicoAbacatePay)
    {
        $usuario = $requisicao->user();
        $agencia = $usuario ? $usuario->agencia : null;

        if (!$agencia) {
            return response()->json(['erro' => 'Agência não encontrada para este usuário.'], 404);
        }

        if ($agencia->pagamento_ativo || $agencia->status_pagamento === 'ativo') {
            return response()->json(['erro' => 'Sua assinatura já está ativa.'], 400);
        }

        $plano = $agencia->plano;

        $cobranca = $servicoAbacatePay->gerarCobranca($agencia, $usuario, $plano);
        $urlCheckout = is_array($cobranca) ? $cobranca['url'] : $cobranca;
        $idCobranca = is_array($cobranca) ? ($cobranca['id'] ?? null) : null;

        $agencia->checkout_url = $urlCheckout;
        $agencia->url_checkout = $urlCheckout;
        if ($idCobranca) {
            $agencia->billing_id = $idCobranca;
            $agencia->id_cobranca = $idCobranca;
            $agencia->id_plataforma_pagamento = $idCobranca;
        }
        $agencia->save();

        return response()->json([
            'checkout_url' => $urlCheckout,
            'url_checkout' => $urlCheckout,
        ], 200);
    }
}
