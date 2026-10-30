<?php

namespace App\Http\Middleware;

use App\Models\Startup;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarStartupAtiva
{
    public function handle(Request $request, Closure $next): Response
    {
        $startupId = $request->header('X-Startup-Id');

        if ($startupId && $request->user()) {
            $startup = Startup::where('id', $startupId)
                ->where('agencia_id', $request->user()->agencia_id)
                ->first();

            if (!$startup) {
                return response()->json([
                    'mensagem' => 'A startup selecionada não pertence à sua consultoria ou é inválida.'
                ], 403);
            }

            $request->attributes->set('startup_ativa', $startup);
        }

        return $next($request);
    }
}
