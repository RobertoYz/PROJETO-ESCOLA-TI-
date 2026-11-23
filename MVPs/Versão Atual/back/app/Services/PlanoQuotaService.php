<?php

namespace App\Services;

use App\Exceptions\PlanLimitExceededException;
use App\Models\Agencia;
use App\Models\Plano;
use App\Models\User;

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

    public function obterResumoCotaEquipe(Agencia $agencia): array
    {
        $plano = $agencia->plano_id
            ? Plano::find($agencia->plano_id)
            : ($agencia->relationLoaded('plano') ? $agencia->plano : null);

        $limite = $plano && $plano->limite_membros_equipe !== null
            ? (int) $plano->limite_membros_equipe
            : null;
        $planoNome = $plano?->nome ?? 'Standard';

        $baseQuery = User::where('agencia_id', $agencia->id);

        $ativos = (int) (clone $baseQuery)->where(function ($query) {
            $query->where('status', 'ativo')->orWhereNull('status');
        })->count();

        $pendentes = (int) (clone $baseQuery)->where('status', 'pendente')->count();
        $inativos = (int) (clone $baseQuery)->where('status', 'inativo')->count();

        $usados = $ativos + $pendentes;
        $ilimitado = $limite === null;
        $restantes = $ilimitado ? null : max(0, $limite - $usados);
        $atingiuLimite = !$ilimitado && ($usados >= $limite);

        return [
            'usados' => $usados,
            'ativos' => $ativos,
            'pendentes' => $pendentes,
            'inativos' => $inativos,
            'limite' => $limite,
            'restantes' => $restantes,
            'ilimitado' => $ilimitado,
            'atingiu_limite' => $atingiuLimite,
            'plano_nome' => $planoNome,
            'inclui_titular' => true,
        ];
    }

    public function validarLimiteEquipe(Agencia $agencia, string $acao = 'convidar'): void
    {
        $resumo = $this->obterResumoCotaEquipe($agencia);

        if ($resumo['atingiu_limite'] === true) {
            $limite = (int) $resumo['limite'];
            $usados = (int) $resumo['usados'];
            $planoNome = (string) $resumo['plano_nome'];

            $mensagem = $acao === 'convidar'
                ? "Limite de {$limite} membro(s) do plano {$planoNome} atingido ({$usados}/{$limite}, incluindo o titular e convites pendentes). Inative um consultor ou faça upgrade do plano para convidar novos membros."
                : "Não é possível reativar este membro porque o plano {$planoNome} atingiu o limite de {$limite} vaga(s) ({$usados}/{$limite}). Inative outro consultor ou faça upgrade do plano.";

            throw new PlanLimitExceededException(
                $mensagem,
                'LIMITE_CONSULTORES_ATINGIDO',
                $usados,
                $limite,
                $planoNome,
                $resumo['restantes']
            );
        }
    }
}
