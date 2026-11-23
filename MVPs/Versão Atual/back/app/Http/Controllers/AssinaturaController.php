<?php

namespace App\Http\Controllers;

use App\Adapters\AbacatePayAdapter;
use App\Models\Plano;
use App\Services\AbacatePayService;
use App\Services\MinutaLimitService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssinaturaController extends Controller
{
    protected AbacatePayAdapter $adapter;

    public function __construct(?AbacatePayAdapter $adapter = null)
    {
        $this->adapter = $adapter ?? new AbacatePayAdapter();
    }

    /**
     * 1. Endpoint de Consulta da Assinatura Atual (EDTAN-160 / EDTAN-311)
     * GET /api/assinatura/atual
     */
    public function atual(Request $requisicao, MinutaLimitService $limitService, \App\Services\PlanoQuotaService $quotaService): JsonResponse
    {
        $usuario = $requisicao->user();
        $agencia = ($usuario && $usuario->agencia) ? $usuario->agencia->fresh() : null;

        if (!$usuario || !$agencia) {
            return response()->json([
                'message' => 'Agência não vinculada a este usuário.',
            ], 404);
        }

        if (!$usuario->isOwner()) {
            return response()->json([
                'message' => 'Apenas o titular financeiro da consultoria possui acesso à gestão de assinatura e faturamento.',
            ], 403);
        }

        $plano = $agencia->plano ?? Plano::find($agencia->plano_id) ?? Plano::first();

        $resumoCotaStartups = $quotaService->getUsageSummary($agencia);
        $startupsCadastradas = $resumoCotaStartups['startups_atual'];
        $membrosEquipe = $agencia->usuarios()->count();
        $consumoMinutas = $limitService->obterConsumoMinutas($usuario);
        $minutasUsadas = (int) ($consumoMinutas['usadas'] ?? 0);

        $expiraEm = $agencia->assinatura_expira_em;
        $expiraNoFuturo = $expiraEm !== null && $expiraEm->isFuture();
        $expiraVencida = $expiraEm !== null && $expiraEm->isPast();

        $diasRestantes = $expiraNoFuturo
            ? (int) max(0, ceil(now()->floatDiffInDays($expiraEm, false)))
            : 0;

        $statusReal = $agencia->status_pagamento;
        $statusEfetivo = ($statusReal === 'ativo' && $expiraVencida) ? 'vencido' : $statusReal;

        $podeRenovar = ($statusReal !== 'ativo')
            || ($expiraEm === null)
            || $expiraVencida
            || ($diasRestantes <= 5);

        $dataVencimentoFormatada = $expiraEm ? $expiraEm->format('d/m/Y') : null;
        $dataRepeticaoFormatada = $expiraEm ? $expiraEm->format('d/m/Y') : null;
        $dataLiberacaoRenovacao = $expiraEm
            ? $expiraEm->copy()->subDays(5)->format('d/m/Y')
            : now()->format('d/m/Y');

        $cobrancaAberta = !empty($agencia->url_checkout)
            && $agencia->pagamento_expira_em !== null
            && $agencia->pagamento_expira_em->isFuture();

        $urlCheckout = $cobrancaAberta ? $agencia->url_checkout : null;

        $formaPagamento = in_array(strtoupper((string) $agencia->forma_pagamento), ['PIX', 'CARD'], true)
            ? strtoupper((string) $agencia->forma_pagamento)
            : 'PIX';
        $formaPagamentoLabel = $formaPagamento === 'CARD' ? 'Cartão de Crédito' : 'PIX';

        $emCarencia = $agencia->emPeriodoCarencia();
        $vencimentoProximo = $agencia->emJanelaVencimentoProximo();
        $exibirAlertaAssinatura = $vencimentoProximo || $emCarencia;
        $alertaAssinaturaNivel = $emCarencia
            ? 'critico'
            : ($vencimentoProximo ? 'atencao' : null);

        $alertaAssinaturaMensagem = null;
        if ($emCarencia) {
            $alertaAssinaturaMensagem = 'Assinatura vencida em período de carência. Atualize sua forma de pagamento para evitar a pausa da conta.';
        } elseif ($vencimentoProximo) {
            $alertaAssinaturaMensagem = "Sua assinatura vence em {$diasRestantes} dia(s) ({$dataVencimentoFormatada}). Renove agora sem perder os dias restantes.";
        }

        return response()->json([
            'plano' => [
                'id' => $plano?->id,
                'nome' => $plano?->nome ?? 'Plano Padrão',
                'preco' => $plano ? (float) $plano->preco : 0.0,
                'preco_formatado' => $plano ? number_format((float) $plano->preco, 2, ',', '.') : '0,00',
                'limite_startups' => (int) ($plano?->limite_startups ?? 0),
                'limite_membros_equipe' => (int) ($plano?->limite_membros_equipe ?? 0),
                'limite_minutas_ia' => (int) ($plano?->limite_minutas_ia ?? 5),
            ],
            'uso_atual' => [
                'startups_cadastradas' => $startupsCadastradas,
                'membros_equipe' => $membrosEquipe,
                'minutas_ia' => $minutasUsadas,
            ],
            'cota_startups' => $resumoCotaStartups,
            'forma_pagamento' => $formaPagamento,
            'forma_pagamento_label' => $formaPagamentoLabel,
            'em_carencia' => $emCarencia,
            'vencimento_proximo' => $vencimentoProximo,
            'exibir_alerta_assinatura' => $exibirAlertaAssinatura,
            'alerta_assinatura_nivel' => $alertaAssinaturaNivel,
            'alerta_assinatura_mensagem' => $alertaAssinaturaMensagem,
            'status_pagamento' => $statusEfetivo,
            'status_pagamento_real' => $statusReal,
            'assinatura_ativa' => $agencia->isPagamentoAtivo(),
            'assinatura_expira_em' => $expiraEm ? $expiraEm->toIso8601String() : null,
            'assinatura_expira_em_formatada' => $dataVencimentoFormatada,
            'data_vencimento_formatada' => $dataVencimentoFormatada,
            'data_repeticao_formatada' => $dataRepeticaoFormatada,
            'dias_restantes' => $diasRestantes,
            'pode_renovar' => $podeRenovar,
            'data_liberacao_renovacao' => $dataLiberacaoRenovacao,
            'data_liberacao_renovacao_formatada' => $dataLiberacaoRenovacao,
            'cobranca_aberta' => $cobrancaAberta,
            'url_checkout' => $urlCheckout,
            'pagamento_expira_em' => $cobrancaAberta ? $agencia->pagamento_expira_em->toIso8601String() : null,
            'perfil_acesso' => $usuario->perfil_acesso ?? 'consultor_admin',
        ], 200);
    }

    /**
     * 2. Geração Idempotente de Pagamento para Renovação (EDTAN-160 / EDTAN-311)
     * POST /api/assinatura/renovar
     */
    public function renovar(Request $requisicao, AbacatePayService $servicoAbacatePay): JsonResponse
    {
        $usuario = $requisicao->user();

        if (!$usuario) {
            return response()->json([
                'message' => 'Usuário não autenticado.',
            ], 401);
        }

        if (!$usuario->isOwner()) {
            return response()->json([
                'message' => 'Apenas o administrador da consultoria pode gerar uma cobrança de renovação da assinatura.',
            ], 403);
        }

        $dadosValidados = $requisicao->validate([
            'forma_pagamento' => 'nullable|string|in:PIX,CARD',
        ], [
            'forma_pagamento.in' => 'A forma de pagamento deve ser PIX ou CARD.',
        ]);

        $agencia = $usuario->agencia ? $usuario->agencia->fresh() : null;

        if (!$agencia) {
            return response()->json([
                'message' => 'Agência não vinculada a este usuário.',
            ], 404);
        }

        $plano = $agencia->plano ?? Plano::find($agencia->plano_id) ?? Plano::first();

        if (!$plano) {
            return response()->json([
                'message' => 'Nenhum plano vinculado à agência.',
            ], 404);
        }

        $expiraEm = $agencia->assinatura_expira_em;
        $expiraNoFuturo = $expiraEm !== null && $expiraEm->isFuture();
        $diasRestantes = $expiraNoFuturo
            ? (int) max(0, ceil(now()->floatDiffInDays($expiraEm, false)))
            : 0;

        if ($agencia->status_pagamento === 'ativo' && $expiraNoFuturo && $diasRestantes > 5) {
            return response()->json([
                'message' => 'A renovação antecipada fica disponível faltando 5 dias para o vencimento da assinatura.',
                'dias_restantes' => $diasRestantes,
                'data_liberacao_renovacao' => $expiraEm->copy()->subDays(5)->format('d/m/Y'),
            ], 422);
        }

        $formaAtual = in_array(strtoupper((string) $agencia->forma_pagamento), ['PIX', 'CARD'], true)
            ? strtoupper((string) $agencia->forma_pagamento)
            : 'PIX';

        $formaSolicitada = !empty($dadosValidados['forma_pagamento'])
            ? strtoupper($dadosValidados['forma_pagamento'])
            : $formaAtual;

        $mesmaFormaPagamento = ($formaSolicitada === $formaAtual);

        // Idempotência: só reaproveita a url_checkout aberta (60 min) se a forma_pagamento solicitada for igual à atual da agência
        if (
            $mesmaFormaPagamento
            && !empty($agencia->url_checkout)
            && $agencia->pagamento_expira_em !== null
            && $agencia->pagamento_expira_em->isFuture()
        ) {
            return response()->json([
                'message' => 'Cobrança de renovação já está ativa e disponível para pagamento.',
                'reaproveitada' => true,
                'forma_pagamento' => $formaAtual,
                'id_transacao' => $agencia->id_transacao,
                'url_checkout' => $agencia->url_checkout,
                'pagamento_expira_em' => $agencia->pagamento_expira_em->toIso8601String(),
            ], 200);
        }

        try {
            $agencia->forma_pagamento = $formaSolicitada;
            $cobranca = $servicoAbacatePay->gerarCobranca($agencia, $usuario, $plano, $formaSolicitada);
            $dadosCobranca = $this->adapter->normalizarRetornoCobranca($cobranca);
            $urlCheckout = $dadosCobranca['url_checkout'];
            $idTransacao = $dadosCobranca['id_transacao'];

            if (empty($urlCheckout) || !str_starts_with($urlCheckout, 'http') || !str_contains($urlCheckout, 'abacatepay.com')) {
                throw new Exception('O gateway de pagamento AbacatePay não retornou uma URL de checkout válida.');
            }

            $agencia->forma_pagamento = $formaSolicitada;
            $agencia->id_transacao = $idTransacao;
            $agencia->url_checkout = $urlCheckout;
            $agencia->pagamento_expira_em = Carbon::now()->addMinutes(60);

            if ($expiraNoFuturo) {
                $agencia->status_pagamento = 'ativo';
            }

            $agencia->save();

            return response()->json([
                'message' => 'Cobrança de renovação gerada com sucesso.',
                'reaproveitada' => false,
                'forma_pagamento' => $agencia->forma_pagamento,
                'id_transacao' => $agencia->id_transacao,
                'url_checkout' => $agencia->url_checkout,
                'pagamento_expira_em' => $agencia->pagamento_expira_em->toIso8601String(),
            ], 200);
        } catch (Exception $excecao) {
            return response()->json([
                'message' => 'Erro ao gerar cobrança de renovação: ' . $excecao->getMessage(),
            ], 500);
        }
    }

    /**
     * 3. Atualização de Forma de Pagamento (PIX <-> CARD) (EDTAN-311)
     * POST /api/assinatura/atualizar-forma-pagamento
     */
    public function atualizarFormaPagamento(Request $requisicao, AbacatePayService $servicoAbacatePay): JsonResponse
    {
        $usuario = $requisicao->user();

        if (!$usuario) {
            return response()->json([
                'message' => 'Usuário não autenticado.',
            ], 401);
        }

        if (!$usuario->isOwner()) {
            return response()->json([
                'message' => 'Apenas o administrador da consultoria pode atualizar a forma de pagamento da assinatura.',
            ], 403);
        }

        $dadosValidados = $requisicao->validate([
            'forma_pagamento' => 'required|string|in:PIX,CARD',
        ], [
            'forma_pagamento.required' => 'Selecione a forma de pagamento desejada (PIX ou Cartão de Crédito).',
            'forma_pagamento.in' => 'A forma de pagamento deve ser PIX ou CARD.',
        ]);

        $agencia = $usuario->agencia ? $usuario->agencia->fresh() : null;

        if (!$agencia) {
            return response()->json([
                'message' => 'Agência não vinculada a este usuário.',
            ], 404);
        }

        $plano = $agencia->plano ?? Plano::find($agencia->plano_id) ?? Plano::first();

        if (!$plano) {
            return response()->json([
                'message' => 'Nenhum plano vinculado à agência.',
            ], 404);
        }

        $novaForma = strtoupper($dadosValidados['forma_pagamento']);
        $novaFormaLabel = $novaForma === 'CARD' ? 'Cartão de Crédito' : 'PIX';

        $expiraEm = $agencia->assinatura_expira_em;
        $expiraNoFuturo = $expiraEm !== null && $expiraEm->isFuture();
        $expiraVencida = $expiraEm !== null && $expiraEm->isPast();
        $diasRestantes = $expiraNoFuturo
            ? (int) max(0, ceil(now()->floatDiffInDays($expiraEm, false)))
            : 0;

        $deveGerarCheckoutAgora = ($agencia->status_pagamento !== 'ativo')
            || $agencia->emPeriodoCarencia()
            || $expiraVencida
            || ($expiraNoFuturo && $diasRestantes <= 5);

        if (!$deveGerarCheckoutAgora) {
            $agencia->forma_pagamento = $novaForma;
            $agencia->save();

            return response()->json([
                'message' => "Forma de pagamento atualizada para {$novaFormaLabel} com sucesso! A nova preferência será utilizada no próximo ciclo de renovação.",
                'forma_pagamento' => $novaForma,
                'forma_pagamento_label' => $novaFormaLabel,
                'requer_checkout' => false,
                'url_checkout' => null,
            ], 200);
        }

        try {
            $agencia->forma_pagamento = $novaForma;
            $cobranca = $servicoAbacatePay->gerarCobranca($agencia, $usuario, $plano, $novaForma);
            $dadosCobranca = $this->adapter->normalizarRetornoCobranca($cobranca);
            $urlCheckout = $dadosCobranca['url_checkout'];
            $idTransacao = $dadosCobranca['id_transacao'];

            if (empty($urlCheckout) || !str_starts_with($urlCheckout, 'http') || !str_contains($urlCheckout, 'abacatepay.com')) {
                throw new Exception('O gateway de pagamento AbacatePay não retornou uma URL de checkout válida.');
            }

            $agencia->forma_pagamento = $novaForma;
            $agencia->id_transacao = $idTransacao;
            $agencia->url_checkout = $urlCheckout;
            $agencia->pagamento_expira_em = Carbon::now()->addMinutes(60);
            $agencia->save();

            return response()->json([
                'message' => "Forma de pagamento atualizada para {$novaFormaLabel}. Redirecionando para o checkout...",
                'forma_pagamento' => $novaForma,
                'forma_pagamento_label' => $novaFormaLabel,
                'requer_checkout' => true,
                'id_transacao' => $agencia->id_transacao,
                'url_checkout' => $agencia->url_checkout,
                'pagamento_expira_em' => $agencia->pagamento_expira_em->toIso8601String(),
            ], 200);
        } catch (Exception $excecao) {
            return response()->json([
                'message' => 'Erro ao atualizar forma de pagamento e gerar cobrança: ' . $excecao->getMessage(),
            ], 500);
        }
    }
}
