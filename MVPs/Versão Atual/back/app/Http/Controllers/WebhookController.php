<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Agencia;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function abacatePayWebhook(Request $requisicao)
    {
        try {
            $chaveSecretaRecebida = $requisicao->query('webhookSecret');
            $chaveSecretaEsperada = env('ABACATEPAY_WEBHOOK_SECRET');

            if ($chaveSecretaEsperada && $chaveSecretaRecebida !== $chaveSecretaEsperada) {
                Log::warning('Webhook AbacatePay recusado: webhookSecret inválido ou ausente.', ['recebido' => $chaveSecretaRecebida]);
                return response()->json(['erro' => 'Não autorizado'], 401);
            }

            Log::info('Webhook AbacatePay recebido:', $requisicao->all());

            $evento = $requisicao->input('event');
            $dados = $requisicao->input('data', $requisicao->all());
            
            $idExterno = $requisicao->input('data.checkout.externalId') 
                ?? $requisicao->input('data.externalId') 
                ?? $requisicao->input('data.billing.externalId');

            $statusPagamento = $requisicao->input('data.checkout.status') 
                ?? $requisicao->input('data.status') 
                ?? $requisicao->input('data.billing.status');

            $idTransacao = $requisicao->input('data.checkout.id') 
                ?? $requisicao->input('data.id') 
                ?? $requisicao->input('data.billing.id');

            if (!$idExterno) {
                Log::warning('Webhook AbacatePay sem identificador externo', $requisicao->all());
                return response()->json(['erro' => 'Identificador externo não encontrado no payload'], 400);
            }

            $partes = explode('_', $idExterno);
            $idAgencia = $partes[1] ?? str_replace('agencia_', '', $idExterno);
            $agencia = Agencia::find($idAgencia);

            if (!$agencia) {
                Log::error("Agência não encontrada para o externalId: {$idExterno}");
                return response()->json(['erro' => 'Agência não encontrada'], 404);
            }

            $statusRecebido = strtoupper($statusPagamento ?? '');
            
            if (in_array($evento, ['checkout.completed', 'subscription.completed', 'billing.paid']) || $statusRecebido === 'PAID') {
                $agencia->status_pagamento = 'ativo';
                $agencia->pagamento_ativo = true;
            } elseif (in_array($evento, ['checkout.refunded', 'subscription.cancelled']) || in_array($statusRecebido, ['REFUNDED', 'CANCELLED'])) {
                $agencia->status_pagamento = 'cancelado';
                $agencia->pagamento_ativo = false;
            } elseif (in_array($evento, ['checkout.lost', 'subscription.expired']) || $statusRecebido === 'EXPIRED') {
                $agencia->status_pagamento = 'suspenso';
                $agencia->pagamento_ativo = false;
            } elseif ($statusRecebido === 'PENDING') {
                $agencia->status_pagamento = 'pendente';
                $agencia->pagamento_ativo = false;
            }

            if ($idTransacao) {
                $agencia->id_plataforma_pagamento = $idTransacao;
                $agencia->billing_id = $idTransacao;
                $agencia->id_cobranca = $idTransacao;
            }

            $agencia->save();

            Log::info("Webhook processado com sucesso. Agência {$agencia->id} - Status: {$agencia->status_pagamento}, Pagamento Ativo: " . ($agencia->pagamento_ativo ? 'sim' : 'não'));
            return response()->json(['mensagem' => 'Webhook processado com sucesso'], 200);

        } catch (\Exception $excecao) {
            Log::error('Erro ao processar Webhook AbacatePay: ' . $excecao->getMessage());
            return response()->json(['erro' => 'Erro interno ao processar webhook'], 500);
        }
    }
}
