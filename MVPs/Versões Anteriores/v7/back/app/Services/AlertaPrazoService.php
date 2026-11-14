<?php

namespace App\Services;

use App\Models\AlertaPrazo;
use App\Models\Edital;
use App\Models\KanbanCard;
use App\Models\Startup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class AlertaPrazoService
{
    /**
     * RF-47 / EDTAN-171: Verifica editais com prazos críticos e gera/atualiza alertas idempotentes
     */
    public function verificarEGerarAlertasPrazos(
        User $user,
        ?Startup $startup = null,
        int $diasMaximos = 15,
        bool $enviarEmail = true
    ): array {
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

            // Dispara e-mail para prazos críticos ou urgentes caso ainda não tenha sido enviado
            if ($enviarEmail && !$alerta->email_enviado && in_array($urgencia, ['critico', 'urgente'])) {
                $this->enviarEmailAlertaPrazo($user, $alerta);
            }

            $alertasGerados[] = $alerta->load(['edital', 'startup', 'kanbanCard']);
        }

        return $alertasGerados;
    }

    /**
     * Envia e-mail de alerta de prazo crítico com template dark institucional Anjo Nexus
     */
    public function enviarEmailAlertaPrazo(User $user, AlertaPrazo $alerta): bool
    {
        try {
            $edital = $alerta->edital ?? Edital::find($alerta->edital_id);
            $tituloEdital = $edital ? $edital->title : $alerta->titulo;
            $fonte = $edital?->fonte ?? 'Órgão de Fomento';
            $dataLimite = Carbon::parse($alerta->data_limite)->format('d/m/Y \à\s H:i');
            $dias = $alerta->dias_restantes;

            $corDestaque = $alerta->nivel_urgencia === 'critico' ? '#ef4444' : '#f59e0b';
            $textoUrgencia = $alerta->nivel_urgencia === 'critico'
                ? "🚨 CRÍTICO — Restam {$dias} dia(s)"
                : "⚠️ URGENTE — Restam {$dias} dia(s)";

            $linkRadar = config('app.frontend_url', 'https://anjonexus.intalenti.com.br') . "/paginas/RadarEditais.html?edital_id={$alerta->edital_id}";

            $htmlEmail = "
            <div style='font-family: Arial, sans-serif; max-width: 580px; margin: 0 auto; background-color: #0b0e14; color: #f8fafc; padding: 32px; border-radius: 14px; border: 1px solid #1e2430;'>
                <div style='text-align: center; margin-bottom: 24px;'>
                    <span style='background: rgba(99, 102, 241, 0.15); color: #818cf8; font-size: 11px; font-weight: bold; padding: 4px 10px; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.5px;'>
                        Anjo Nexus • Central de Oportunidades
                    </span>
                    <h2 style='color: #f8fafc; margin: 12px 0 4px 0; font-size: 20px;'>Alerta de Prazo Crítico</h2>
                    <span style='background: {$corDestaque}; color: #ffffff; font-size: 11px; font-weight: bold; padding: 3px 8px; border-radius: 6px;'>
                        {$textoUrgencia}
                    </span>
                </div>

                <p style='color: #cbd5e1; line-height: 1.6; font-size: 14px;'>
                    Olá, <strong>{$user->name}</strong>!
                </p>
                <p style='color: #94a3b8; line-height: 1.6; font-size: 13px;'>
                    Identificamos que um edital relevante para as suas operações está com a data limite de submissão muito próxima. Veja os detalhes abaixo:
                </p>

                <div style='background: #141720; border: 1px solid #283042; border-left: 4px solid {$corDestaque}; border-radius: 10px; padding: 16px 20px; margin: 20px 0;'>
                    <div style='font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: bold; margin-bottom: 4px;'>Fonte: {$fonte}</div>
                    <div style='font-weight: bold; font-size: 15px; color: #f1f5f9; margin-bottom: 8px;'>{$tituloEdital}</div>
                    <div style='color: #cbd5e1; font-size: 13px; line-height: 1.5; margin-bottom: 12px;'>{$alerta->mensagem}</div>
                    <div style='font-size: 12px; color: #94a3b8; border-top: 1px solid #1e2430; padding-top: 8px;'>
                        <span>📅 Data Limite: <strong>{$dataLimite}</strong></span> &nbsp;•&nbsp; 
                        <span>⏳ Dias Restantes: <strong style='color: {$corDestaque};'>{$dias}</strong></span>
                    </div>
                </div>

                <div style='text-align: center; margin: 28px 0;'>
                    <a href='{$linkRadar}' style='background: linear-gradient(135deg, #2563eb, #3b82f6); color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 14px; display: inline-block; box-shadow: 0 4px 14px rgba(37,99,235,0.4);'>
                        Acessar Edital no Radar →
                    </a>
                </div>

                <p style='color: #64748b; font-size: 11px; line-height: 1.5; text-align: center; margin-top: 24px; border-top: 1px solid #1e2430; padding-top: 16px;'>
                    Você está recebendo este e-mail porque possui notificações automáticas ativadas para editais e propostas na plataforma Anjo Nexus.
                </p>
            </div>";

            Mail::html($htmlEmail, function ($mensagem) use ($user, $tituloEdital, $textoUrgencia) {
                $mensagem->to($user->email, $user->name)
                         ->subject("{$textoUrgencia}: {$tituloEdital} — Anjo Nexus");
            });

            $alerta->update([
                'email_enviado' => true,
                'email_enviado_em' => now()
            ]);

            return true;
        } catch (\Exception $e) {
            Log::warning("Falha ao enviar e-mail de alerta de prazo (ID {$alerta->id}): " . $e->getMessage());
            return false;
        }
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

