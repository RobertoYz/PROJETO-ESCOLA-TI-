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
     * 1. Endpoint de Consulta da Assinatura Atual (EDTAN-160)
     * GET /api/assinatura/atual
     */
    public function atual(Request $requisicao, MinutaLimitService $limitService): JsonResponse
    {
        $usuario = $requisicao->user();
        $agencia = $usuario ? $usuario->agencia : null;

        if (!$usuario || !$agencia) {
            return response()->json([
                'message' => 'Agência não vinculada a este usuário.',
            ], 404);
        }

        $plano = $agencia->plano ?? Plano::find($agencia->plano_id) ?? Plano::first();

        $startupsCadastradas = $agencia->startups()->count();
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

        $dataLiberacaoRenovacao = $expiraEm
            ? $expiraEm->copy()->subDays(5)->format('d/m/Y')
            : now()->format('d/m/Y');

        $cobrancaAberta = !empty($agencia->url_checkout)
            && $agencia->pagamento_expira_em !== null
            && $agencia->pagamento_expira_em->isFuture();

        $urlCheckout = $cobrancaAberta ? $agencia->url_checkout : null;

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
            'forma_pagamento' => 'PIX',
            'status_pagamento' => $statusEfetivo,
            'status_pagamento_real' => $statusReal,
            'assinatura_ativa' => $agencia->isPagamentoAtivo(),
            'assinatura_expira_em' => $expiraEm ? $expiraEm->toIso8601String() : null,
            'assinatura_expira_em_formatada' => $expiraEm ? $expiraEm->format('d/m/Y') : null,
            'dias_restantes' => $diasRestantes,
            'pode_renovar' => $podeRenovar,
            'data_liberacao_renovacao' => $dataLiberacaoRenovacao,
            'cobranca_aberta' => $cobrancaAberta,
            'url_checkout' => $urlCheckout,
            'pagamento_expira_em' => $cobrancaAberta ? $agencia->pagamento_expira_em->toIso8601String() : null,
            'perfil_acesso' => $usuario->perfil_acesso ?? 'consultor_admin',
        ], 200);
    }

    /**
     * 2. Geração Idempotente de Pagamento PIX para Renovação (EDTAN-160)
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

        if ($usuario->perfil_acesso !== 'consultor_admin') {
            return response()->json([
                'message' => 'Apenas o administrador da consultoria pode gerar uma cobrança de renovação da assinatura.',
            ], 403);
        }

        $agencia = $usuario->agencia;

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

        // Idempotência: se já houver cobrança PIX válida na janela de 60 minutos, reaproveita sem chamar o gateway externo
        if (
            !empty($agencia->url_checkout)
            && $agencia->pagamento_expira_em !== null
            && $agencia->pagamento_expira_em->isFuture()
        ) {
            return response()->json([
                'message' => 'Cobrança PIX de renovação já está ativa e disponível para pagamento.',
                'reaproveitada' => true,
                'id_transacao' => $agencia->id_transacao,
                'url_checkout' => $agencia->url_checkout,
                'pagamento_expira_em' => $agencia->pagamento_expira_em->toIso8601String(),
            ], 200);
        }

        try {
            $cobranca = $servicoAbacatePay->gerarCobranca($agencia, $usuario, $plano);
            $dadosCobranca = $this->adapter->normalizarRetornoCobranca($cobranca);
            $urlCheckout = $dadosCobranca['url_checkout'];
            $idTransacao = $dadosCobranca['id_transacao'];

            if (empty($urlCheckout) || !str_starts_with($urlCheckout, 'http') || !str_contains($urlCheckout, 'abacatepay.com')) {
                throw new Exception('O gateway de pagamento AbacatePay não retornou uma URL de checkout válida.');
            }

            $agencia->id_transacao = $idTransacao;
            $agencia->url_checkout = $urlCheckout;
            $agencia->pagamento_expira_em = Carbon::now()->addMinutes(60);

            if ($expiraNoFuturo) {
                $agencia->status_pagamento = 'ativo';
            }

            $agencia->save();

            return response()->json([
                'message' => 'Cobrança PIX de renovação gerada com sucesso.',
                'reaproveitada' => false,
                'id_transacao' => $agencia->id_transacao,
                'url_checkout' => $agencia->url_checkout,
                'pagamento_expira_em' => $agencia->pagamento_expira_em->toIso8601String(),
            ], 200);
        } catch (Exception $excecao) {
            return response()->json([
                'message' => 'Erro ao gerar cobrança PIX de renovação: ' . $excecao->getMessage(),
            ], 500);
        }
    }
}
