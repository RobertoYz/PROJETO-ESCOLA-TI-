<?php

namespace App\Services;

use App\Adapters\AbacatePayAdapter;
use App\Models\Agencia;
use App\Models\Plano;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TrocaPlanoService
{
    public function __construct(
        private PlanoQuotaService $cotas,
        private AbacatePayService $gateway,
        private AbacatePayAdapter $adapter,
    ) {}

    public function simular(User $usuario, Plano $destino): array
    {
        $this->autorizar($usuario);

        return $this->resumo($usuario->agencia, $destino);
    }

    public function autorizar(User $usuario): void
    {
        abort_unless($usuario->isOwner(), 403, 'Somente o titular pode gerenciar a troca de plano.');
    }

    private function impedimentos(Agencia $agencia, Plano $destino): array
    {
        $usos = [
            'startups' => [$this->cotas->getUsageSummary($agencia)['startups_atual'], $destino->limite_startups],
            'membros' => [$this->cotas->obterResumoCotaEquipe($agencia)['usados'], $destino->limite_membros_equipe],
        ];
        $impedimentos = [];
        foreach ($usos as $recurso => [$uso, $limite]) {
            if ($limite !== null && $uso > $limite) {
                $impedimentos[$recurso] = ['uso' => $uso, 'limite' => $limite, 'excedente' => $uso - $limite];
            }
        }

        return $impedimentos;
    }

    private function resumo(Agencia $agencia, Plano $destino): array
    {
        $atual = $agencia->plano;
        abort_if($atual?->is($destino), 422, 'Este já é o plano atual da agência.');
        $ativo = $atual && $agencia->status_pagamento === 'ativo' && $agencia->assinatura_expira_em?->isFuture();
        $precoAtual = (int) round((float) $atual?->preco * 100);
        $precoNovo = (int) round((float) $destino->preco * 100);
        $tipo = 'troca_imediata';
        $dias = 0;
        if ($ativo) {
            $tipo = $precoNovo < $precoAtual ? 'downgrade' : 'upgrade';
            $dias = max(0, (int) now()->startOfDay()->diffInDays($agencia->assinatura_expira_em->copy()->startOfDay(), false));
        }
        $saldo = $tipo === 'upgrade' ? $precoAtual * $dias : 0;
        $credito = intdiv($saldo + 15, 30);
        $extras = $precoNovo > 0 ? intdiv($saldo + intdiv($precoNovo, 2), $precoNovo) : 0;
        $impedimentos = $tipo === 'upgrade' ? [] : $this->impedimentos($agencia, $destino);

        return [
            'tipo' => $tipo,
            'plano_atual' => $atual?->only('id', 'nome', 'preco'),
            'plano_destino' => $destino->only('id', 'nome', 'preco'),
            'dias_restantes' => $dias,
            'credito_estimado' => $credito / 100,
            'dias_extras' => $extras,
            'novo_ciclo_dias' => 30 + $extras,
            'valor_a_pagar' => $tipo === 'downgrade' ? 0 : $precoNovo / 100,
            'data_vigencia' => $tipo === 'downgrade' ? $agencia->assinatura_expira_em->toIso8601String() : null,
            'permitido' => !$impedimentos,
            'impedimentos' => $impedimentos,
        ];
    }

    public function trocar(User $usuario, Plano $destino, ?string $forma, ?string $tipoSimulado = null): array
    {
        $this->autorizar($usuario);

        return DB::transaction(function () use ($usuario, $destino, $forma, $tipoSimulado) {
            $agencia = Agencia::lockForUpdate()->findOrFail($usuario->agencia_id);
            $resumo = $this->resumo($agencia, $destino);
            abort_if($tipoSimulado !== null && $tipoSimulado !== $resumo['tipo'], 409,
                'A vigência da assinatura mudou. Refaça a simulação antes de confirmar.');
            if (!$resumo['permitido']) {
                throw new HttpResponseException(response()->json($resumo + [
                    'message' => 'O uso atual excede a cota do plano escolhido.',
                    'codigo_erro' => 'DOWNGRADE_EXCEDE_COTA_PLANO',
                ], 422));
            }
            if ($resumo['tipo'] === 'downgrade') {
                if ($agencia->plano_upgrade_pendente_id) {
                    $agencia->limparUpgradePendente();
                }
                $agencia->update(['plano_agendado_id' => $destino->id]);

                return $resumo + ['plano_agendado' => $resumo['plano_destino']];
            }

            $forma ??= $agencia->forma_pagamento ?? 'PIX';
            $cobranca = $this->adapter->normalizarRetornoCobranca(
                $this->gateway->gerarCobranca($agencia, $usuario, $destino, $forma, true)
            );
            abort_if(empty($cobranca['id_transacao']), 502, 'O gateway não identificou a cobrança de troca.');
            $agencia->update([
                'plano_upgrade_pendente_id' => $destino->id,
                'upgrade_transacao_id' => $cobranca['id_transacao'],
                'upgrade_dias_extras' => $resumo['dias_extras'],
                'upgrade_forma_pagamento' => $forma,
                'url_checkout' => $cobranca['url_checkout'],
                'pagamento_expira_em' => now()->addHour(),
            ]);

            return $resumo + $cobranca;
        });
    }

    public function cancelarDowngradeAgendado(User $usuario): void
    {
        $this->autorizar($usuario);
        Agencia::whereKey($usuario->agencia_id)->update(['plano_agendado_id' => null]);
    }

    public function aplicarDowngradeVencido(Agencia $agencia): void
    {
        $destino = $agencia->planoAgendado;
        if (!$destino || !$agencia->assinatura_expira_em?->lte(now())) {
            return;
        }
        $impedimentos = $this->impedimentos($agencia, $destino);
        if ($impedimentos) {
            Log::warning('Downgrade agendado cancelado por excesso de cota.', [
                'agencia_id' => $agencia->id, 'impedimentos' => $impedimentos,
            ]);
        } else {
            $agencia->plano_id = $destino->id;
            $agencia->setRelation('plano', $destino);
            if (!$agencia->plano_upgrade_pendente_id) {
                $agencia->url_checkout = null;
                $agencia->pagamento_expira_em = null;
            }
        }
        $agencia->update(['plano_agendado_id' => null]);
    }
}
