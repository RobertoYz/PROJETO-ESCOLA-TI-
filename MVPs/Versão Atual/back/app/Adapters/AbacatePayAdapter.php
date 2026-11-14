<?php

namespace App\Adapters;

class AbacatePayAdapter
{
    /**
     * Converte entidades do domínio interno para o payload exigido pela API AbacatePay v2.
     */
    public function paraPayloadCriacao($agencia, $usuario, $plano, string $urlRetorno): array
    {
        $idProdutoAbacatePay = $plano->id_transacao ?? 'prod_sWfDg0eZXTLbtZEHYpqnMLKP';

        return [
            'frequency' => 'ONE_TIME',
            'items' => [
                [
                    'id' => $idProdutoAbacatePay,
                    'quantity' => 1,
                ],
            ],
            'customer' => [
                'name' => $usuario->name,
                'email' => $usuario->email,
            ],
            'externalId' => 'agencia_' . $agencia->id . '_' . uniqid(),
            'returnUrl' => $urlRetorno,
            'completionUrl' => $urlRetorno,
            'methods' => ['PIX'],
        ];
    }

    /**
     * Traduz a resposta da API de criação de cobrança do AbacatePay para o domínio interno.
     */
    public function deRespostaCriacao(array $respostaJson): array
    {
        $dados = $respostaJson['data'] ?? $respostaJson;

        return [
            'id_transacao' => $dados['id_transacao'] ?? $dados['id'] ?? null,
            'url_checkout' => $dados['url_checkout'] ?? $dados['url'] ?? null,
        ];
    }

    /**
     * Normaliza retornos de cobrança (seja de resposta direta, array adaptado ou string de URL).
     */
    public function normalizarRetornoCobranca(array|string $cobranca): array
    {
        if (is_array($cobranca)) {
            return $this->deRespostaCriacao($cobranca);
        }

        return [
            'id_transacao' => null,
            'url_checkout' => $cobranca,
        ];
    }

    /**
     * Traduz o payload de Webhook do AbacatePay para os campos padronizados do domínio interno.
     */
    public function deWebhookPayload(array $payload): array
    {
        $evento = $payload['event'] ?? null;
        $dados = $payload['data'] ?? $payload;

        $idExterno = $dados['checkout']['externalId']
            ?? $dados['externalId']
            ?? $dados['billing']['externalId']
            ?? null;

        $statusExterno = $dados['checkout']['status']
            ?? $dados['status']
            ?? $dados['billing']['status']
            ?? null;

        $idTransacao = $dados['checkout']['id']
            ?? $dados['id']
            ?? $dados['billing']['id']
            ?? null;

        $idAgencia = null;
        if ($idExterno) {
            $partes = explode('_', (string) $idExterno);
            $idAgencia = $partes[1] ?? str_replace('agencia_', '', (string) $idExterno);
        }

        return [
            'evento' => $evento,
            'id_externo' => $idExterno,
            'id_agencia' => $idAgencia,
            'id_transacao' => $idTransacao,
            'status_pagamento' => $this->traduzirStatus($statusExterno, $evento),
        ];
    }

    /**
     * Traduz status/eventos em inglês da API AbacatePay para o enum `status_pagamento` do domínio.
     */
    public function traduzirStatus(?string $statusExterno, ?string $evento = null): ?string
    {
        $statusNormalizado = strtoupper($statusExterno ?? '');

        if (
            in_array($evento, ['checkout.completed', 'subscription.completed', 'billing.paid'], true)
            || $statusNormalizado === 'PAID'
        ) {
            return 'ativo';
        }

        if (
            in_array($evento, ['checkout.refunded', 'subscription.cancelled'], true)
            || in_array($statusNormalizado, ['REFUNDED', 'CANCELLED'], true)
        ) {
            return 'cancelado';
        }

        if (
            in_array($evento, ['checkout.lost', 'subscription.expired'], true)
            || $statusNormalizado === 'EXPIRED'
        ) {
            return 'suspenso';
        }

        if ($statusNormalizado === 'PENDING') {
            return 'pendente';
        }

        return null;
    }
}
