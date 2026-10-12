<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Agencia;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function abacatePayWebhook(Request $request)
    {
        try {
            $secretRecebido = $request->query('webhookSecret');
            $secretEsperado = env('ABACATEPAY_WEBHOOK_SECRET');

            if ($secretEsperado && $secretRecebido !== $secretEsperado) {
                Log::warning('Webhook AbacatePay recusado: webhookSecret inválido ou ausente.', ['recebido' => $secretRecebido]);
                return response()->json(['error' => 'Não autorizado'], 401);
            }

            Log::info('Webhook AbacatePay recebido:', $request->all());

            $evento = $request->input('event');
            $data = $request->input('data', $request->all());
            
            $externalId = $data['externalId'] ?? null;
            $statusPagamento = $data['status'] ?? null;
            $transacaoId = $data['id'] ?? $data['transaction_id'] ?? null;

            if (!$externalId) {
                Log::warning('Webhook AbacatePay sem externalId', $request->all());
                return response()->json(['error' => 'ID externo não encontrado no payload'], 400);
            }

            $agenciaId = str_replace('agencia_', '', $externalId);
            $agencia = Agencia::find($agenciaId);

            if (!$agencia) {
                Log::error("Agência não encontrada para o externalId: {$externalId}");
                return response()->json(['error' => 'Agência não encontrada'], 404);
            }

            $statusRecebido = strtoupper($statusPagamento ?? '');
            
            if (in_array($evento, ['checkout.completed', 'subscription.completed']) || $statusRecebido === 'PAID') {
                $agencia->status_pagamento = 'ativo';
            } elseif (in_array($evento, ['checkout.refunded', 'subscription.cancelled']) || in_array($statusRecebido, ['REFUNDED', 'CANCELLED'])) {
                $agencia->status_pagamento = 'cancelado';
            } elseif (in_array($evento, ['checkout.lost', 'subscription.expired']) || $statusRecebido === 'EXPIRED') {
                $agencia->status_pagamento = 'suspenso';
            } elseif ($statusRecebido === 'PENDING') {
                $agencia->status_pagamento = 'pendente';
            }

            if ($transacaoId && $agencia->id_plataforma_pagamento !== $transacaoId) {
                $agencia->id_plataforma_pagamento = $transacaoId;
            }

            $agencia->save();

            Log::info("Webhook processado com sucesso. Agência {$agencia->id} - Novo Status: {$agencia->status_pagamento}");
            return response()->json(['message' => 'Webhook processado com sucesso'], 200);

        } catch (\Exception $e) {
            Log::error('Erro ao processar Webhook AbacatePay: ' . $e->getMessage());
            return response()->json(['error' => 'Erro interno ao processar webhook'], 500);
        }
    }
}
