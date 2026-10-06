<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPagamentoAtivo
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->agencia) {
            if ($user->agencia->status_pagamento === 'ativo') {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'Acesso bloqueado. O pagamento da sua assinatura está pendente ou inativo.',
            'codigo_erro' => 'PAGAMENTO_PENDENTE'
        ], 403);
    }
}
