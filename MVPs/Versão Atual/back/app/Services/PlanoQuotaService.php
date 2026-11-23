<?php

namespace App\Services;

use App\Exceptions\PlanLimitExceededException;
use App\Models\Agencia;
use App\Models\Plano;

class PlanoQuotaService
{
    public function getUsageSummary(Agencia $agencia): array
    {
        $plano = $agencia->relationLoaded('plano') && $agencia->plano
            ? $agencia->plano
            : ($agencia->plano_id ? Plano::find($agencia->plano_id) : null);

        $startupsAtual = (int) $agencia->startups()->count();
        $startupsLimite = $plano && $plano->limite_startups !== null
            ? (int) $plano->limite_startups
            : null;
        $planoNome = $plano?->nome ?? 'Standard';

        $podeCriarStartup = $startupsLimite === null || $startupsAtual < $startupsLimite;

        return [
            'startups_atual' => $startupsAtual,
            'startups_limite' => $startupsLimite,
            'pode_criar_startup' => $podeCriarStartup,
            'plano_nome' => $planoNome,
        ];
    }

    public function assertCanCreateStartup(Agencia $agencia): void
    {
        $resumo = $this->getUsageSummary($agencia);
        $limite = $resumo['startups_limite'];
        $atual = $resumo['startups_atual'];
        $planoNome = $resumo['plano_nome'];

        if ($limite !== null && $atual >= $limite) {
            throw new PlanLimitExceededException(
                "Limite de startups do plano {$planoNome} atingido ({$atual}/{$limite}). Faça upgrade do seu plano em 'Minha Assinatura' ou exclua uma startup existente para liberar uma vaga.",
                'LIMITE_STARTUPS_ATINGIDO',
                $atual,
                $limite,
                $planoNome
            );
        }
    }
}
