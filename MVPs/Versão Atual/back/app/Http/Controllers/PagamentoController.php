<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AbacatePayService;

class PagamentoController extends Controller
{
    public function recuperarLinkPagamento(Request $request, AbacatePayService $abacatePayService)
    {
        $user = $request->user();
        $agencia = $user->agencia;

        if ($agencia->status_pagamento === 'ativo') {
            return response()->json(['error' => 'Sua assinatura já está ativa'], 400);
        }

        $plano = $agencia->plano;

        $linkCheckout = $abacatePayService->gerarCobranca($agencia, $user, $plano);

        return response()->json([
            'checkout_url' => $linkCheckout
        ], 200);
    }
}
