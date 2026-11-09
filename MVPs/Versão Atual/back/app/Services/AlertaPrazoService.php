<?php

namespace App\Services;

use App\Models\AlertaPrazo;
use App\Models\Edital;
use App\Models\KanbanCard;
use App\Models\Startup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class AlertaPrazoService
{
    /**
     * RF-47 / EDTAN-171: Verifica editais com prazos críticos e gera/atualiza alertas idempotentes
     */
    public function verificarEGerarAlertasPrazos(User $user, ?Startup $startup = null, int $diasMaximos = 15): array
    {
        $agora = Carbon::now();
        $limiteData = (clone $agora)->addDays($diasMaximos);

        // Busca editais com deadline definido que ainda estão ativos ou encerram em até $diasMaximos dias
        $editais = Edital::query()
            ->whereNotNull('deadline')
            ->where('deadline', '>=', (clone $agora)->subHours(12))
            ->where('deadline', '<=', $limiteData)
            ->get();

        $alertasGerados = [];

        foreach ($editais as $edital) {
            $dataLimite = Carbon::parse($edital->deadline);
            $segundosRestantes = $agora->diffInSeconds($dataLimite, false);
            
            // Calcula dias restantes arredondando para cima
            $diasRestantes = (int) max(0, ceil($segundosRestantes / 86400));

            if ($diasRestantes > $diasMaximos) {
                continue;
            }

            // Define nível de urgência baseado na proximidade do prazo
            if ($diasRestantes <= 3) {
                $urgencia = 'critico';
                $prefixoTitulo = '🚨 Prazo Crítico: ';
            } elseif ($diasRestantes <= 7) {
                $urgencia = 'urgente';
                $prefixoTitulo = '⚠️ Prazo Urgente: ';
            } else {
                $urgencia = 'atencao';
                $prefixoTitulo = '📅 Prazo Próximo: ';
            }

            $titulo = $prefixoTitulo . $edital->title;
            $dataFormatada = $dataLimite->format('d/m/Y \à\s H:i');

            if ($diasRestantes === 0) {
                $mensagem = "O edital '{$edital->title}' encerra HOJE ({$dataFormatada}). Conclua a submissão com urgência máxima!";
            } elseif ($diasRestantes === 1) {
                $mensagem = "Resta apenas 1 dia para o encerramento do edital '{$edital->title}' ({$dataFormatada}). Revise a proposta e documentos anexos.";
            } else {
                $mensagem = "Faltam {$diasRestantes} dias para o encerramento do edital '{$edital->title}' ({$dataFormatada}). Verifique requisitos e proposta.";
            }

            // Tenta identificar se há card no kanban atrelado a este edital
            $cardVinculado = null;
            if ($startup) {
                $cardVinculado = KanbanCard::where('edital_id', $edital->id)
                    ->where(function ($q) use ($startup) {
                        $q->where('startup_name', $startup->nome_fantasia)
                          ->orWhere('startup_name', $startup->razao_social);
                    })
                    ->first();
            }

            // Persiste de forma idempotente: um alerta por usuário + edital + urgência
            $alerta = AlertaPrazo::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'edital_id' => $edital->id,
                    'nivel_urgencia' => $urgencia,
                ],
                [
                    'startup_id' => $startup?->id,
                    'kanban_card_id' => $cardVinculado?->id,
                    'tipo_prazo' => 'submissao',
                    'data_limite' => $dataLimite,
                    'dias_restantes' => $diasRestantes,
                    'titulo' => $titulo,
                    'mensagem' => $mensagem,
                ]
            );

            $alertasGerados[] = $alerta->load(['edital', 'startup', 'kanbanCard']);
        }

        return $alertasGerados;
    }

    /**
     * Lista alertas de prazos críticos com ordenação por urgência
     */
    public function listarAlertas(
        User $user,
        ?int $startupId = null,
        bool $apenasNaoLidos = false,
        ?string $nivelUrgencia = null
    ): Collection {
        $query = AlertaPrazo::with(['edital', 'startup', 'kanbanCard'])
            ->where('user_id', $user->id);

        if ($startupId) {
            $query->where(function ($q) use ($startupId) {
                $q->where('startup_id', $startupId)
                  ->orWhereNull('startup_id');
            });
        }

        if ($apenasNaoLidos) {
            $query->where('lido', false);
        }

        if ($nivelUrgencia) {
            $query->where('nivel_urgencia', $nivelUrgencia);
        }

        // Ordena por critico (1) > urgente (2) > atencao (3) e prazo mais próximo primeiro
        return $query
            ->orderByRaw("CASE nivel_urgencia WHEN 'critico' THEN 1 WHEN 'urgente' THEN 2 ELSE 3 END")
            ->orderBy('data_limite', 'asc')
            ->get();
    }

    /**
     * Retorna a quantidade de alertas não lidos de prazos
     */
    public function contarNaoLidos(User $user, ?int $startupId = null): int
    {
        $query = AlertaPrazo::where('user_id', $user->id)
            ->where('lido', false);

        if ($startupId) {
            $query->where(function ($q) use ($startupId) {
                $q->where('startup_id', $startupId)
                  ->orWhereNull('startup_id');
            });
        }

        return $query->count();
    }

    /**
     * Marca um alerta específico como lido
     */
    public function marcarComoLido(int $alertaId, User $user): AlertaPrazo
    {
        $alerta = AlertaPrazo::where('id', $alertaId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $alerta->update(['lido' => true]);

        return $alerta;
    }

    /**
     * Marca todos os alertas de prazos como lidos
     */
    public function marcarTodosComoLidos(User $user, ?int $startupId = null): int
    {
        $query = AlertaPrazo::where('user_id', $user->id)
            ->where('lido', false);

        if ($startupId) {
            $query->where(function ($q) use ($startupId) {
                $q->where('startup_id', $startupId)
                  ->orWhereNull('startup_id');
            });
        }

        return $query->update(['lido' => true]);
    }
}
