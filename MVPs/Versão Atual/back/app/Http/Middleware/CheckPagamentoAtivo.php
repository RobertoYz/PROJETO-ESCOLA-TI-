<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPagamentoAtivo
{
    public function handle(Request $requisicao, Closure $proximo)
    {
        $usuario = $requisicao->user();

        // Administrador Global tem acesso irrestrito e não possui validação de pagamento
        if ($usuario && $usuario->perfil_acesso === 'admin_global') {
            return $proximo($requisicao);
        }

        if ($usuario && $usuario->agencia) {
            if ($usuario->agencia->isSuspensaAdministrativamente()) {
                return response()->json([
                    'message' => 'O acesso desta consultoria foi suspenso pela administração global da plataforma. Entre em contato com o suporte.',
                    'codigo_erro' => 'CONSULTORIA_SUSPENSA',
                    'codigo' => 'CONSULTORIA_SUSPENSA',
                ], 403);
            }

            if ($usuario->agencia->isPagamentoAtivo()) {
                return $proximo($requisicao);
            }
        }

        return response()->json([
            'message' => 'Acesso bloqueado. O pagamento da sua assinatura está pendente ou inativo.',
            'codigo_erro' => 'PAGAMENTO_PENDENTE',
            'codigo' => 'PAGAMENTO_PENDENTE',
        ], 403);
    }
}
