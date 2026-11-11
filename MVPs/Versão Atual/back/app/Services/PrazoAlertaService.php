<?php

namespace App\Services;

use App\Models\Edital;
use App\Models\KanbanCard;
use Carbon\Carbon;

class PrazoAlertaService
{
    /**
     * Calcula métricas e nível de criticidade a partir de uma data limite (deadline).
     *
     * @param string|\DateTimeInterface|null $deadline
     * @return array
     */
    public function calcularCriticidade($deadline): array
    {
        if (empty($deadline)) {
            return [
                'has_deadline' => false,
                'status' => 'sem_prazo',
                'nivel' => 'neutro',
                'rotulo' => 'Fluxo Contínuo / A Definir',
                'badge_class' => 'prazo-neutro',
                'dias_restantes' => null,
                'horas_restantes' => null,
                'minutos_restantes' => null,
                'is_critico' => false,
                'is_urgente' => false,
                'is_expirado' => false,
            ];
        }

        try {
            $dataFim = Carbon::parse($deadline);
        } catch (\Exception $e) {
            return [
                'has_deadline' => false,
                'status' => 'invalido',
                'nivel' => 'neutro',
                'rotulo' => 'Data Inválida',
                'badge_class' => 'prazo-neutro',
                'dias_restantes' => null,
                'horas_restantes' => null,
                'minutos_restantes' => null,
                'is_critico' => false,
                'is_urgente' => false,
                'is_expirado' => false,
            ];
        }

        $agora = Carbon::now();
        $diffMinutos = (int) $agora->diffInMinutes($dataFim, false);
        $diffHoras = (int) $agora->diffInHours($dataFim, false);
        $diffDias = (int) $agora->diffInDays($dataFim, false);

        if ($diffMinutos < 0) {
            return [
                'has_deadline' => true,
                'status' => 'expirado',
                'nivel' => 'expirado',
                'rotulo' => 'Prazo Encerrado',
                'badge_class' => 'prazo-expirado',
                'dias_restantes' => $diffDias,
                'horas_restantes' => $diffHoras,
                'minutos_restantes' => $diffMinutos,
                'is_critico' => false,
                'is_urgente' => false,
                'is_expirado' => true,
                'data_formatada' => $dataFim->format('d/m/Y H:i'),
            ];
        }

        // Crítico Urgente: <= 24 horas (inclui janela hiper-crítica de 4h)
        if ($diffHoras <= 24) {
            $minutosRestantes = $agora->diffInMinutes($dataFim) % 60;
            $texto = $diffHoras <= 4 
                ? "Crítico! Encerra em {$diffHoras}h {$minutosRestantes}m" 
                : "Urgente! Restam {$diffHoras}h";

            return [
                'has_deadline' => true,
                'status' => 'critico_24h',
                'nivel' => 'urgente',
                'rotulo' => $texto,
                'badge_class' => 'prazo-critico-24h',
                'dias_restantes' => 0,
                'horas_restantes' => $diffHoras,
                'minutos_restantes' => $diffMinutos,
                'is_critico' => true,
                'is_urgente' => true,
                'is_expirado' => false,
                'data_formatada' => $dataFim->format('d/m/Y H:i'),
            ];
        }

        // Crítico Alerta: <= 72 horas (3 dias)
        if ($diffHoras <= 72) {
            return [
                'has_deadline' => true,
                'status' => 'alerta_72h',
                'nivel' => 'alerta',
                'rotulo' => "Atenção: Restam {$diffDias} dias ({$diffHoras}h)",
                'badge_class' => 'prazo-alerta-72h',
                'dias_restantes' => $diffDias,
                'horas_restantes' => $diffHoras,
                'minutos_restantes' => $diffMinutos,
                'is_critico' => true,
                'is_urgente' => false,
                'is_expirado' => false,
                'data_formatada' => $dataFim->format('d/m/Y H:i'),
            ];
        }

        // Moderado: <= 7 dias
        if ($diffDias <= 7) {
            return [
                'has_deadline' => true,
                'status' => 'moderado_7dias',
                'nivel' => 'moderado',
                'rotulo' => "Restam {$diffDias} dias",
                'badge_class' => 'prazo-moderado',
                'dias_restantes' => $diffDias,
                'horas_restantes' => $diffHoras,
                'minutos_restantes' => $diffMinutos,
                'is_critico' => false,
                'is_urgente' => false,
                'is_expirado' => false,
                'data_formatada' => $dataFim->format('d/m/Y H:i'),
            ];
        }

        // Estável: > 7 dias
        return [
            'has_deadline' => true,
            'status' => 'estavel',
            'nivel' => 'estavel',
            'rotulo' => "Prazo: {$dataFim->format('d/m/Y')}",
            'badge_class' => 'prazo-estavel',
            'dias_restantes' => $diffDias,
            'horas_restantes' => $diffHoras,
            'minutos_restantes' => $diffMinutos,
            'is_critico' => false,
            'is_urgente' => false,
            'is_expirado' => false,
            'data_formatada' => $dataFim->format('d/m/Y H:i'),
        ];
    }

    /**
     * Retorna lista de alertas de prazos para uma determinada startup ou geral.
     */
    public function obterAlertas(?string $startupNome = null): array
    {
        $queryCards = KanbanCard::with(['edital', 'column'])
            ->whereHas('column', function ($q) {
                // Não alertar para oportunidades já submetidas ou finalizadas
                $q->whereNotIn('slug', ['done', 'submetido']);
            });

        if (!empty($startupNome)) {
            $queryCards->where(function ($sub) use ($startupNome) {
                $sub->where('startup_name', $startupNome)
                    ->orWhere('startup_name', 'like', "%{$startupNome}%");
            });
        }

        $cards = $queryCards->get();
        $alertas = [];

        foreach ($cards as $card) {
            $deadline = $card->edital->deadline ?? ($card->metadata['deadline'] ?? null);
            if (!$deadline) continue;

            $info = $this->calcularCriticidade($deadline);
            // Incluir cartões críticos (< 72h), moderados (< 7d) ou expirados recentes
            if ($info['has_deadline']) {
                $alertas[] = [
                    'card_id' => $card->id,
                    'card_titulo' => $card->title,
                    'startup_name' => $card->startup_name,
                    'coluna_nome' => $card->column->title ?? 'Pipeline',
                    'coluna_slug' => $card->column->slug ?? '',
                    'edital_id' => $card->edital_id,
                    'edital_titulo' => $card->edital->title ?? $card->title,
                    'edital_fonte' => $card->edital->fonte ?? ($card->metadata['fonte'] ?? 'N/A'),
                    'deadline' => $deadline,
                    'criticidade' => $info,
                ];
            }
        }

        // Ordenar: primeiro os mais urgentes com menor horas_restantes
        usort($alertas, function ($a, $b) {
            $hA = $a['criticidade']['horas_restantes'] ?? 999999;
            $hB = $b['criticidade']['horas_restantes'] ?? 999999;
            // Se expirado, coloca no fim dos alertas ativos
            if ($a['criticidade']['is_expirado'] && !$b['criticidade']['is_expirado']) return 1;
            if (!$a['criticidade']['is_expirado'] && $b['criticidade']['is_expirado']) return -1;
            return $hA <=> $hB;
        });

        // Contadores agregados
        $totalCriticos24h = count(array_filter($alertas, fn($a) => $a['criticidade']['status'] === 'critico_24h'));
        $totalAlerta72h = count(array_filter($alertas, fn($a) => $a['criticidade']['status'] === 'alerta_72h'));
        $totalModerados = count(array_filter($alertas, fn($a) => $a['criticidade']['status'] === 'moderado_7dias'));
        $totalExpirados = count(array_filter($alertas, fn($a) => $a['criticidade']['status'] === 'expirado'));

        return [
            'resumo' => [
                'total_alertas' => count($alertas),
                'criticos_24h' => $totalCriticos24h,
                'alerta_72h' => $totalAlerta72h,
                'moderados_7d' => $totalModerados,
                'expirados' => $totalExpirados,
                'tem_urgencia' => ($totalCriticos24h > 0 || $totalAlerta72h > 0),
            ],
            'itens' => $alertas
        ];
    }
}
