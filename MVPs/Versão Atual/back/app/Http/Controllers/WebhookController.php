<?php

namespace App\Http\Controllers;

use App\Adapters\AbacatePayAdapter;
use Illuminate\Http\Request;
use App\Models\Agencia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
            $chaveSecretaEsperada = config('services.abacatepay.webhook_secret');

            if (!$chaveSecretaEsperada || !is_string($chaveSecretaRecebida)
                || !hash_equals($chaveSecretaEsperada, $chaveSecretaRecebida)) {
                Log::warning('Webhook AbacatePay recusado: webhookSecret inválido ou ausente.');
                return response()->json(['message' => 'Não autorizado', 'erro' => 'Não autorizado'], 401);
            }

            $dadosAdaptados = $this->adapter->deWebhookPayload($requisicao->all());

            if (!$dadosAdaptados['id_externo']) {
                Log::warning('Webhook AbacatePay sem identificador externo');
                return response()->json(['message' => 'Identificador externo não encontrado no payload', 'erro' => 'Identificador externo não encontrado no payload'], 400);
            }

            return DB::transaction(function () use ($dadosAdaptados) {
                $agencia = Agencia::lockForUpdate()->find($dadosAdaptados['id_agencia']);

                if (!$agencia) {
                    Log::error("Agência não encontrada para o externalId: {$dadosAdaptados['id_externo']}");
                    return response()->json(['message' => 'Agência não encontrada', 'erro' => 'Agência não encontrada'], 404);
                }

                $idTransacao = $dadosAdaptados['id_transacao'];
                $status = $dadosAdaptados['status_pagamento'];
                $upgrade = $idTransacao && $agencia->plano_upgrade_pendente_id && $idTransacao === $agencia->upgrade_transacao_id;
                $eventoTroca = str_ends_with($dadosAdaptados['id_externo'], '_troca');
                if ($upgrade) {
                    if ($status === 'ativo') {
                        $agencia->plano_id = $agencia->plano_upgrade_pendente_id;
                        $agencia->forma_pagamento = $agencia->upgrade_forma_pagamento ?? $agencia->forma_pagamento;
                        $agencia->assinatura_expira_em = now()->addDays(30 + $agencia->upgrade_dias_extras);
                        $agencia->status_pagamento = 'ativo';
                        $agencia->id_transacao = $idTransacao;
                        $agencia->plano_agendado_id = null;
                        $agencia->alerta_vencimento_proximo_enviado_em = null;
                        $agencia->alerta_falha_pagamento_enviado_em = null;
                        $agencia->limparUpgradePendente();
                        $agencia->save();
                    }

                    return response()->json(['message' => 'Webhook de troca processado.']);
                }

                if (($eventoTroca && ($idTransacao !== $agencia->id_transacao || !in_array($status, ['cancelado', 'suspenso'], true)))
                    || ($agencia->plano_upgrade_pendente_id && $idTransacao !== $agencia->id_transacao)) {
                    return response()->json(['message' => 'Evento sem alteração na assinatura.']);
                }

                if ($dadosAdaptados['status_pagamento']) {
                    $agencia->status_pagamento = $dadosAdaptados['status_pagamento'];
                    if ($dadosAdaptados['status_pagamento'] === 'ativo') {
                        if ($agencia->assinatura_expira_em && $agencia->assinatura_expira_em->isFuture()) {
                            $agencia->assinatura_expira_em = $agencia->assinatura_expira_em->copy()->addDays(30);
                        } else {
                            $agencia->assinatura_expira_em = now()->addDays(30);
                        }
                        if (!$agencia->plano_upgrade_pendente_id) {
                            $agencia->url_checkout = null;
                            $agencia->pagamento_expira_em = null;
                        }
                        $agencia->alerta_vencimento_proximo_enviado_em = null;
                        $agencia->alerta_falha_pagamento_enviado_em = null;
                    } elseif (in_array($dadosAdaptados['status_pagamento'], ['cancelado', 'suspenso'], true)) {
                        if (!$agencia->plano_upgrade_pendente_id) {
                            $agencia->url_checkout = null;
                            $agencia->pagamento_expira_em = null;
                        }
                    }
                }

                if ($dadosAdaptados['id_transacao']) {
                    $agencia->id_transacao = $dadosAdaptados['id_transacao'];
                }

                $agencia->save();

                Log::info("Webhook processado com sucesso. Agência {$agencia->id} - Status: {$agencia->status_pagamento}");
                return response()->json(['message' => 'Webhook processado com sucesso'], 200);
            });

        } catch (\Exception $excecao) {
            Log::error('Erro ao processar Webhook AbacatePay: ' . $excecao->getMessage());
            return response()->json(['message' => 'Erro interno ao processar webhook', 'erro' => 'Erro interno ao processar webhook'], 500);
        }
    }
}
