<?php

namespace App\Console\Commands;

use App\Models\Agencia;
use App\Models\AlertaMatch;
use App\Models\AlertaPrazo;
use App\Models\EditalFavorito;
use App\Models\KanbanBoard;
use App\Models\KanbanCard;
use App\Models\KanbanColumn;
use App\Models\Minuta;
use App\Models\Plano;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessarCicloAssinaturas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'assinaturas:processar-ciclo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Processa o ciclo de vida diário das assinaturas: aviso preventivo (<= 5 dias), carência de 2 dias com alerta SMTP, suspensão e expurgo LGPD (3 dias nunca pagas / 30 dias suspensas).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $diasExpurgoNuncaPago = (int) env('ASSINATURA_DIAS_EXPURGO_NUNCA_PAGO', 3);
        $diasExpurgoSuspensa = (int) env('ASSINATURA_DIAS_EXPURGO_SUSPENSA', 30);

        $alertasPreventivosEnviados = 0;
        $expurgadasNuncaPagas = 0;
        $alertasCarenciaEnviados = 0;
        $contasSuspensas = 0;
        $expurgadasSuspensas = 0;

        // Fase 0 — Aviso Preventivo de Vencimento Próximo (<= 5 dias antes de vencer — EDTAN-310 / RF-102)
        Agencia::with('plano')
            ->where('status_pagamento', 'ativo')
            ->whereNotNull('assinatura_expira_em')
            ->where('assinatura_expira_em', '>', now())
            ->where('assinatura_expira_em', '<=', now()->addDays(5))
            ->whereNull('alerta_vencimento_proximo_enviado_em')
            ->chunkById(100, function ($agencias) use (&$alertasPreventivosEnviados) {
                foreach ($agencias as $agencia) {
                    if ($this->enviarEmailAlertaVencimentoProximo($agencia)) {
                        $agencia->alerta_vencimento_proximo_enviado_em = now();
                        $agencia->save();
                        $alertasPreventivosEnviados++;
                    }
                }
            });

        // A) Contas que NUNCA pagaram (status_pagamento = 'pendente' e assinatura_expira_em IS NULL)
        Agencia::where('status_pagamento', 'pendente')
            ->whereNull('assinatura_expira_em')
            ->where('created_at', '<=', now()->subDays($diasExpurgoNuncaPago))
            ->chunkById(100, function ($agencias) use (&$expurgadasNuncaPagas) {
                foreach ($agencias as $agencia) {
                    $this->executarHardDeleteEmCascata($agencia);
                    $expurgadasNuncaPagas++;
                }
            });

        // B) Contas que JÁ pagaram antes (assinatura_expira_em IS NOT NULL)
        Agencia::whereNotNull('assinatura_expira_em')
            ->where('assinatura_expira_em', '<=', now())
            ->chunkById(100, function ($agencias) use (
                $diasExpurgoSuspensa,
                &$alertasCarenciaEnviados,
                &$contasSuspensas,
                &$expurgadasSuspensas
            ) {
                foreach ($agencias as $agencia) {
                    $expiraEm = $agencia->assinatura_expira_em;
                    if (!$expiraEm || $expiraEm->isFuture()) {
                        continue;
                    }

                    // Fase 3 (Expurgo Legal — passou de vencimento + 30 dias)
                    if ($expiraEm->copy()->addDays($diasExpurgoSuspensa)->isPast()) {
                        $this->executarHardDeleteEmCascata($agencia);
                        $expurgadasSuspensas++;
                        continue;
                    }

                    // Fase 2 (Pausa/Suspensão — passou de vencimento + 2 dias)
                    if ($expiraEm->copy()->addDays(2)->isPast()) {
                        if ($agencia->status_pagamento !== 'suspenso') {
                            $agencia->status_pagamento = 'suspenso';
                            $agencia->save();
                            $contasSuspensas++;
                        }
                        continue;
                    }

                    // Fase 1 (Carência — entre vencimento e vencimento + 2 dias)
                    if ($agencia->status_pagamento === 'ativo' && $agencia->alerta_falha_pagamento_enviado_em === null) {
                        $this->enviarEmailAlertaCarencia($agencia);
                        $agencia->alerta_falha_pagamento_enviado_em = now();
                        $agencia->save();
                        $alertasCarenciaEnviados++;
                    }
                }
            });

        // C) Expurgo físico de startups excluídas via Soft Delete há mais de 30 dias (LGPD - EDTAN-249)
        $startupsSoftDeletedExpurgadas = Startup::onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays(30))
            ->forceDelete();

        $this->info(
            "Ciclo de assinaturas processado: {$alertasPreventivosEnviados} alertas preventivos (5 dias) enviados, "
            . "{$expurgadasNuncaPagas} nunca pagas expurgadas, "
            . "{$alertasCarenciaEnviados} alertas de carência enviados, "
            . "{$contasSuspensas} contas suspensas, "
            . "{$expurgadasSuspensas} contas suspensas expurgadas, "
            . "{$startupsSoftDeletedExpurgadas} startups em lixeira (>30d) expurgadas."
        );

        return self::SUCCESS;
    }

    /**
     * Envia e-mail HTML preventivo via SMTP para o consultor_admin faltando <= 5 dias para o vencimento (EDTAN-310 / RF-102).
     */
    protected function enviarEmailAlertaVencimentoProximo(Agencia $agencia): bool
    {
        $admin = $agencia->usuarios()->where('perfil_acesso', 'consultor_admin')->first()
            ?? $agencia->usuarios()->first();

        if (!$admin || empty($admin->email)) {
            return false;
        }

        $plano = $agencia->plano ?? Plano::find($agencia->plano_id);
        $nomePlano = $plano?->nome ?? 'Plano Corporativo';
        $formaPagamento = strtoupper((string) ($agencia->forma_pagamento ?? 'PIX'));
        $formaPagamentoLabel = $formaPagamento === 'CARD' ? 'Cartão de Crédito' : 'PIX';

        $expiraEm = $agencia->assinatura_expira_em;
        $diasRestantes = $expiraEm
            ? (int) max(1, ceil(now()->floatDiffInDays($expiraEm, false)))
            : 5;
        $vencimentoFmt = $expiraEm ? $expiraEm->format('d/m/Y') : now()->addDays(5)->format('d/m/Y');

        $htmlEmail = "
        <div style='font-family: Arial, sans-serif; max-width: 580px; margin: 0 auto; background-color: #0d0f1a; color: #f8fafc; padding: 32px; border-radius: 14px; border: 1px solid #2e364f;'>
            <h2 style='color: #38bdf8; margin-top: 0;'>📅 Aviso de Vencimento Próximo da Assinatura — Anjo Nexus</h2>
            <p style='color: #cbd5e1; line-height: 1.6;'>Olá, <strong>{$admin->name}</strong>!</p>
            <p style='color: #cbd5e1; line-height: 1.6;'>
                A assinatura da consultoria <strong>{$agencia->nome}</strong> está próxima do vencimento. Confira o resumo do seu ciclo atual:
            </p>
            <div style='background: #141927; border: 1px solid #283042; border-left: 4px solid #38bdf8; padding: 14px 16px; border-radius: 8px; margin: 20px 0; color: #e2e8f0; font-size: 14px; line-height: 1.6;'>
                <div><strong>Plano Atual:</strong> {$nomePlano}</div>
                <div><strong>Forma de Pagamento Cadastrada:</strong> {$formaPagamentoLabel}</div>
                <div><strong>Vencimento do Ciclo:</strong> {$vencimentoFmt} (faltam <strong>{$diasRestantes} dia(s)</strong>)</div>
            </div>
            <p style='color: #cbd5e1; line-height: 1.6;'>
                Acesse o painel do <strong>Anjo Nexus</strong> e clique em <strong>&quot;Minha Assinatura&quot;</strong> para realizar a renovação antecipada ou atualizar sua forma de pagamento.
            </p>
            <p style='color: #94a3b8; font-size: 13px; line-height: 1.5;'>
                Ao renovar antecipadamente, <strong>os dias restantes atuais são integralmente preservados</strong> e somados aos +30 dias do novo ciclo.
            </p>
        </div>";

        try {
            Mail::html($htmlEmail, function ($mensagem) use ($admin, $agencia, $diasRestantes, $vencimentoFmt) {
                $mensagem->to($admin->email, $admin->name)
                         ->subject("Sua assinatura Anjo Nexus vence em {$diasRestantes} dia(s) ({$vencimentoFmt}) - {$agencia->nome}");
            });

            return true;
        } catch (\Exception $excecao) {
            Log::error("Falha ao enviar e-mail preventivo de vencimento para agência {$agencia->id}: " . $excecao->getMessage());
            return false;
        }
    }

    /**
     * Envia e-mail HTML via SMTP para o consultor_admin avisando dos 2 dias de carência.
     */
    protected function enviarEmailAlertaCarencia(Agencia $agencia): void
    {
        $admin = $agencia->usuarios()->where('perfil_acesso', 'consultor_admin')->first()
            ?? $agencia->usuarios()->first();

        if (!$admin || empty($admin->email)) {
            return;
        }

        $vencimentoFmt = $agencia->assinatura_expira_em
            ? $agencia->assinatura_expira_em->format('d/m/Y')
            : now()->format('d/m/Y');
        $limiteCarenciaFmt = $agencia->assinatura_expira_em
            ? $agencia->assinatura_expira_em->copy()->addDays(2)->format('d/m/Y')
            : now()->addDays(2)->format('d/m/Y');

        $htmlEmail = "
        <div style='font-family: Arial, sans-serif; max-width: 580px; margin: 0 auto; background-color: #0d0f1a; color: #f8fafc; padding: 32px; border-radius: 14px; border: 1px solid #2e364f;'>
            <h2 style='color: #f59e0b; margin-top: 0;'>⚠️ Aviso de Pendência na Assinatura — Período de Carência (2 Dias)</h2>
            <p style='color: #cbd5e1; line-height: 1.6;'>Olá, <strong>{$admin->name}</strong>!</p>
            <p style='color: #cbd5e1; line-height: 1.6;'>
                Identificamos uma falha ou pendência no pagamento da assinatura da consultoria <strong>{$agencia->nome}</strong> (vencimento em <strong>{$vencimentoFmt}</strong>).
            </p>
            <p style='color: #cbd5e1; line-height: 1.6;'>
                Para evitar qualquer interrupção nas suas operações, concedemos um <strong>período de carência de 2 dias</strong> (até <strong>{$limiteCarenciaFmt}</strong>) com acesso total liberado.
            </p>
            <div style='background: #141927; border-left: 4px solid #f59e0b; padding: 14px 16px; border-radius: 8px; margin: 20px 0; color: #e2e8f0; font-size: 14px; line-height: 1.5;'>
                Acesse o painel do <strong>Anjo Nexus</strong> e clique em <strong>&quot;Minha Assinatura&quot; ➔ &quot;Atualizar Forma de Pagamento&quot;</strong> para cadastrar um novo cartão de crédito ou alternar para PIX.
            </div>
            <p style='color: #94a3b8; font-size: 13px; line-height: 1.5;'>
                Caso a regularização não seja concluída até o fim dos 2 dias de carência, sua conta será temporariamente pausada.
            </p>
        </div>";

        try {
            Mail::html($htmlEmail, function ($mensagem) use ($admin, $agencia) {
                $mensagem->to($admin->email, $admin->name)
                         ->subject("Aviso de Carência (2 Dias) - Regularize sua Assinatura ({$agencia->nome}) - Anjo Nexus");
            });
        } catch (\Exception $excecao) {
            Log::error("Falha ao enviar e-mail de carência para agência {$agencia->id}: " . $excecao->getMessage());
        }
    }

    /**
     * Executa o Hard Delete transacional em cascata nas 9 camadas do banco de dados.
     */
    protected function executarHardDeleteEmCascata(Agencia $agencia): void
    {
        DB::transaction(function () use ($agencia) {
            $usuariosParaExcluir = User::where('agencia_id', $agencia->id)->get();
            $idsUsuarios = $usuariosParaExcluir->pluck('id')->toArray();
            $emailsUsuarios = $usuariosParaExcluir->pluck('email')->filter()->toArray();

            foreach ($usuariosParaExcluir as $membro) {
                $membro->tokens()->delete();
            }

            if (!empty($idsUsuarios) && class_exists(\Laravel\Sanctum\PersonalAccessToken::class)) {
                \Laravel\Sanctum\PersonalAccessToken::where('tokenable_type', User::class)
                    ->whereIn('tokenable_id', $idsUsuarios)
                    ->delete();
            }

            $idsStartups = Startup::withTrashed()->where('agencia_id', $agencia->id)->pluck('id')->toArray();
            $idsBoards = KanbanBoard::where('agencia_id', $agencia->id)->pluck('id')->toArray();

            $idsColumns = !empty($idsBoards)
                ? KanbanColumn::whereIn('board_id', $idsBoards)->pluck('id')->toArray()
                : [];

            $idsCards = (!empty($idsBoards) || !empty($idsColumns))
                ? KanbanCard::where(function ($query) use ($idsBoards, $idsColumns) {
                    if (!empty($idsBoards)) {
                        $query->whereIn('board_id', $idsBoards);
                    }
                    if (!empty($idsColumns)) {
                        $query->orWhereIn('column_id', $idsColumns);
                    }
                })->pluck('id')->toArray()
                : [];

            // 1. Minutas
            if (!empty($idsUsuarios) || !empty($idsStartups) || !empty($idsCards)) {
                Minuta::where(function ($query) use ($idsStartups, $idsUsuarios, $idsCards) {
                    if (!empty($idsUsuarios)) {
                        $query->whereIn('user_id', $idsUsuarios);
                    }
                    if (!empty($idsStartups)) {
                        $query->orWhereIn('startup_id', $idsStartups);
                    }
                    if (!empty($idsCards)) {
                        $query->orWhereIn('kanban_card_id', $idsCards);
                    }
                })->delete();
            }

            // 2. Alertas e Notificações
            if (!empty($idsUsuarios) || !empty($idsStartups) || !empty($idsCards)) {
                AlertaPrazo::where(function ($query) use ($idsUsuarios, $idsStartups, $idsCards) {
                    if (!empty($idsUsuarios)) {
                        $query->whereIn('user_id', $idsUsuarios);
                    }
                    if (!empty($idsStartups)) {
                        $query->orWhereIn('startup_id', $idsStartups);
                    }
                    if (!empty($idsCards)) {
                        $query->orWhereIn('kanban_card_id', $idsCards);
                    }
                })->delete();
            }

            if (!empty($idsUsuarios) || !empty($idsStartups)) {
                AlertaMatch::where(function ($query) use ($idsUsuarios, $idsStartups) {
                    if (!empty($idsUsuarios)) {
                        $query->whereIn('user_id', $idsUsuarios);
                    }
                    if (!empty($idsStartups)) {
                        $query->orWhereIn('startup_id', $idsStartups);
                    }
                })->delete();

                // 3. Favoritos
                EditalFavorito::where(function ($query) use ($idsUsuarios, $idsStartups) {
                    if (!empty($idsUsuarios)) {
                        $query->whereIn('user_id', $idsUsuarios);
                    }
                    if (!empty($idsStartups)) {
                        $query->orWhereIn('startup_id', $idsStartups);
                    }
                })->delete();
            }

            // 4. Kanban (Cards, Colunas e Boards)
            if (!empty($idsCards)) {
                KanbanCard::whereIn('id', $idsCards)->delete();
            }
            if (!empty($idsColumns)) {
                KanbanColumn::whereIn('id', $idsColumns)->delete();
            }
            if (!empty($idsBoards)) {
                KanbanBoard::whereIn('id', $idsBoards)->delete();
            }

            // 5. Startups
            if (!empty($idsStartups)) {
                Startup::withTrashed()->whereIn('id', $idsStartups)->forceDelete();
            }

            // 6. Tokens de recuperação de senha e sessões
            if (!empty($emailsUsuarios)) {
                DB::table('password_reset_tokens')->whereIn('email', $emailsUsuarios)->delete();
            }
            if (!empty($idsUsuarios)) {
                DB::table('sessions')->whereIn('user_id', $idsUsuarios)->delete();
                User::whereIn('id', $idsUsuarios)->forceDelete();
            }

            // 7. Agência
            $agencia->forceDelete();
        });
    }
}
