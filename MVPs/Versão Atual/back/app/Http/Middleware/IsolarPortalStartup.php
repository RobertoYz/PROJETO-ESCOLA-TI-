<?php

namespace App\Http\Middleware;

use App\Models\StartupPortalAcesso;
use Closure;
use Illuminate\Http\Request;

class IsolarPortalStartup
{
    public function handle(Request $request, Closure $next)
    {
        $perfil = $request->user('sanctum');
        $portal = $request->is('api/portal/*');

        if ($perfil instanceof StartupPortalAcesso) {
            if ($request->routeIs('auth.logout')) {
                return $next($request);
            }
            abort_unless($portal && $perfil->tokenCan('portal-startup'), 403);
            if (!$perfil->disponivel()) {
                return response()->json(['codigo_erro' => 'PORTAL_DESATIVADO', 'message' => 'Acesso ao portal desativado.'], 403);
            }
        } elseif ($perfil && in_array('portal-startup', $perfil->currentAccessToken()?->abilities ?? [], true)) {
            abort(403);
        } elseif ($portal && $perfil) {
            abort(403);
        }

        return $next($request);
    }
}
