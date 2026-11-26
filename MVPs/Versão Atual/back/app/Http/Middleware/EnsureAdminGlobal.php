<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminGlobal
{
    /**
     * Garante que apenas usuários com perfil 'admin_global' acessem a rota.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->perfil_acesso !== 'admin_global') {
            return response()->json([
                'status' => 'erro',
                'codigo_erro' => 'ACESSO_NEGADO_ADMIN_GLOBAL',
                'message' => 'Acesso restrito ao Administrador Global da plataforma.'
            ], 403);
        }

        return $next($request);
    }
}
