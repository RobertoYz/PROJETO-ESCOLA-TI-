<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPagamentoAtivo
{
    public function handle(Request $requisicao, Closure $proximo)
    {
        $usuario = $requisicao->user();

        if ($usuario && $usuario->agencia) {
            if ($usuario->agencia->pagamento_ativo || $usuario->agencia->status_pagamento === 'ativo') {
                return $proximo($requisicao);
            }
        }

        return response()->json([
            'mensagem' => 'Acesso bloqueado. O pagamento da sua assinatura está pendente ou inativo.',
            'message' => 'Acesso bloqueado. O pagamento da sua assinatura está pendente ou inativo.',
            'codigo_erro' => 'PAGAMENTO_PENDENTE',
            'codigo' => 'PAGAMENTO_PENDENTE'
        ], 403);
    }
}
