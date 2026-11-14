<?php

namespace App\Services;

use App\Models\AlertaMatch;
use App\Models\Startup;
use App\Models\Edital;
use App\Models\User;
use Illuminate\Support\Collection;

class AlertaMatchService
{
    protected MinutaAiService $minutaAiService;

    public function __construct(MinutaAiService $minutaAiService)
    {
        $this->minutaAiService = $minutaAiService;
    }

    /**
     * RF-26 (EDTAN-172): Avalia os editais abertos contra a startup e gera alertas para matches >= 70%
     */
    public function verificarEGerarAlertas(Startup $startup, User $usuario, int $limiteScore = 70): array
    {
        $editais = Edital::whereNotNull('title')->get();
        if ($editais->isEmpty()) {
            return [];
        }

        $ranking = $this->minutaAiService->calcularMatchEditais($startup, $editais);
        $alertasGerados = [];

        foreach ($ranking as $item) {
            $score = (int) ($item['match_percentual'] ?? $item['score'] ?? 0);

            if ($score >= $limiteScore) {
                $editalId = (int) $item['edital_id'];
                $nomeEdital = $item['titulo'] ?? 'Edital de Inovação';
                $nomeStartup = $startup->nome_fantasia ?: $startup->razao_social;

                $alerta = AlertaMatch::firstOrCreate(
                    [
                        'user_id' => $usuario->id,
                        'startup_id' => $startup->id,
                        'edital_id' => $editalId,
                    ],
                    [
                        'score_match' => $score,
                        'titulo' => "⚡ Oportunidade com {$score}% de Match",
                        'mensagem' => "O edital '{$nomeEdital}' possui {$score}% de aderência às características da startup {$nomeStartup}.",
                        'criterios' => $item['pontos_fortes'] ?? [],
                        'lido' => false
                    ]
                );

                $alertasGerados[] = $alerta->load(['edital', 'startup']);
            }
        }

        return $alertasGerados;
    }

    /**
     * Retorna a listagem de alertas do usuário com filtros opcionais
     */
    public function listarAlertas(User $usuario, ?int $startupId = null, bool $apenasNaoLidos = false): Collection
    {
        $query = AlertaMatch::with(['edital', 'startup'])
            ->where('user_id', $usuario->id);

        if ($startupId) {
            $query->where('startup_id', $startupId);
        }

        if ($apenasNaoLidos) {
            $query->where('lido', false);
        }

        return $query->orderByDesc('created_at')->get();
    }

    /**
     * Conta o número de alertas não lidos
     */
    public function contarNaoLidos(User $usuario, ?int $startupId = null): int
    {
        $query = AlertaMatch::where('user_id', $usuario->id)
            ->where('lido', false);

        if ($startupId) {
            $query->where('startup_id', $startupId);
        }

        return $query->count();
    }

    /**
     * Marca um alerta específico como lido
     */
    public function marcarComoLido(int $alertaId, User $usuario): bool
    {
        $alerta = AlertaMatch::where('id', $alertaId)
            ->where('user_id', $usuario->id)
            ->first();

        if ($alerta) {
            $alerta->update(['lido' => true]);
            return true;
        }

        return false;
    }

    /**
     * Marca todos os alertas como lidos
     */
    public function marcarTodosComoLidos(User $usuario, ?int $startupId = null): int
    {
        $query = AlertaMatch::where('user_id', $usuario->id)
            ->where('lido', false);

        if ($startupId) {
            $query->where('startup_id', $startupId);
        }

        return $query->update(['lido' => true]);
    }
}
