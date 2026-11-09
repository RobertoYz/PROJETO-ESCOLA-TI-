<?php

namespace App\Services;

use App\Models\User;
use App\Models\Startup;
use App\Models\Plano;
use App\Models\Minuta;
use Carbon\Carbon;

class MinutaLimitService
{
    /**
     * EDTAN-250: Calcula o consumo de minutas geradas por IA no ciclo atual pelo plano
     */
    public function obterConsumoMinutas(User $usuario, ?Startup $startup = null): array
    {
        $agencia = $usuario->agencia ?? ($startup ? $startup->agencia : null);
        $plano = $agencia?->plano ?? Plano::find($agencia?->plano_id) ?? Plano::first();

        $limite = $plano?->limite_minutas_ia ?? 5;
        $agora = Carbon::now();
        $inicioMes = (clone $agora)->startOfMonth();

        $userIds = $agencia ? $agencia->usuarios()->pluck('id')->toArray() : [$usuario->id];
        if (empty($userIds)) {
            $userIds = [$usuario->id];
        }

        $startupIds = $agencia ? $agencia->startups()->pluck('id')->toArray() : ($startup ? [$startup->id] : []);

        $minutasUsadas = Minuta::where(function ($q) use ($userIds, $startupIds) {
            $q->whereIn('user_id', $userIds);
            if (!empty($startupIds)) {
                $q->orWhereIn('startup_id', $startupIds);
            }
        })
        ->where('created_at', '>=', $inicioMes)
        ->count();

        $disponiveis = max(0, $limite - $minutasUsadas);
        $atingiuLimite = $limite > 0 && $minutasUsadas >= $limite;
        $porcentagem = $limite > 0 ? min(100.0, round(($minutasUsadas / $limite) * 100, 1)) : 0.0;

        return [
            'plano_id' => $plano?->id,
            'plano_nome' => $plano?->nome ?? 'Standard',
            'limite' => $limite,
            'usadas' => $minutasUsadas,
            'disponiveis' => $disponiveis,
            'porcentagem_usada' => $porcentagem,
            'atingiu_limite' => $atingiuLimite,
            'ciclo_inicio' => $inicioMes->toDateString(),
            'ciclo_fim' => (clone $agora)->endOfMonth()->toDateString()
        ];
    }

    /**
     * Verifica se o usuário/agência ainda possui cota para gerar nova minuta
     */
    public function podeGerarMinuta(User $usuario, ?Startup $startup = null): bool
    {
        $consumo = $this->obterConsumoMinutas($usuario, $startup);
        return !$consumo['atingiu_limite'];
    }
}
