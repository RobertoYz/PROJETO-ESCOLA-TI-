<?php

namespace App\Http\Controllers;

use App\Adapters\AbacatePayAdapter;
use Illuminate\Http\Request;
use App\Models\Agencia;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    protected AbacatePayAdapter $adapter;

    public function __construct(?AbacatePayAdapter $adapter = null)
    {
        $this->adapter = $adapter ?? new AbacatePayAdapter();
    }

    public function abacatePayWebhook(Request $requisicao)
    {
        try {
            $chaveSecretaRecebida = $requisicao->query('webhookSecret');
            $chaveSecretaEsperada = env('ABACATEPAY_WEBHOOK_SECRET');

            if ($chaveSecretaEsperada && $chaveSecretaRecebida !== $chaveSecretaEsperada) {
                Log::warning('Webhook AbacatePay recusado: webhookSecret inválido ou ausente.', ['recebido' => $chaveSecretaRecebida]);
                return response()->json(['message' => 'Não autorizado', 'erro' => 'Não autorizado'], 401);
            }

            Log::info('Webhook AbacatePay recebido:', $requisicao->all());

            $dadosAdaptados = $this->adapter->deWebhookPayload($requisicao->all());

            if (!$dadosAdaptados['id_externo']) {
                Log::warning('Webhook AbacatePay sem identificador externo', $requisicao->all());
                return response()->json(['message' => 'Identificador externo não encontrado no payload', 'erro' => 'Identificador externo não encontrado no payload'], 400);
            }

            $agencia = Agencia::find($dadosAdaptados['id_agencia']);

            if (!$agencia) {
                Log::error("Agência não encontrada para o externalId: {$dadosAdaptados['id_externo']}");
                return response()->json(['message' => 'Agência não encontrada', 'erro' => 'Agência não encontrada'], 404);
            }

            if ($dadosAdaptados['status_pagamento']) {
                $agencia->status_pagamento = $dadosAdaptados['status_pagamento'];
                if ($dadosAdaptados['status_pagamento'] === 'ativo') {
                    if ($agencia->assinatura_expira_em && $agencia->assinatura_expira_em->isFuture()) {
                        $agencia->assinatura_expira_em = $agencia->assinatura_expira_em->copy()->addDays(30);
                    } else {
                        $agencia->assinatura_expira_em = now()->addDays(30);
                    }
                    $agencia->url_checkout = null;
                    $agencia->pagamento_expira_em = null;
                }
            }

            if ($dadosAdaptados['id_transacao']) {
                $agencia->id_transacao = $dadosAdaptados['id_transacao'];
            }

            $agencia->save();

            Log::info("Webhook processado com sucesso. Agência {$agencia->id} - Status: {$agencia->status_pagamento}");
            return response()->json(['message' => 'Webhook processado com sucesso'], 200);

        } catch (\Exception $excecao) {
            Log::error('Erro ao processar Webhook AbacatePay: ' . $excecao->getMessage());
            return response()->json(['message' => 'Erro interno ao processar webhook', 'erro' => 'Erro interno ao processar webhook'], 500);
        }
    }
}
