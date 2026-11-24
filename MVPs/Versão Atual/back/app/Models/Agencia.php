<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agencia extends Model
{
    protected $table = 'agencias';

    protected $fillable = [
        'nome',
        'owner_id',
        'plano_id',
        'plano_upgrade_pendente_id',
        'upgrade_transacao_id',
        'upgrade_dias_extras',
        'upgrade_forma_pagamento',
        'plano_agendado_id',
        'forma_pagamento',
        'id_transacao',
        'url_checkout',
        'status_pagamento',
        'pagamento_expira_em',
        'assinatura_expira_em',
        'alerta_vencimento_proximo_enviado_em',
        'alerta_falha_pagamento_enviado_em',
    ];

    protected $casts = [
        'pagamento_expira_em' => 'datetime',
        'assinatura_expira_em' => 'datetime',
        'alerta_vencimento_proximo_enviado_em' => 'datetime',
        'alerta_falha_pagamento_enviado_em' => 'datetime',
    ];

    public function isPagamentoAtivo(): bool
    {
        if ($this->status_pagamento !== 'ativo') {
            return false;
        }

        if ($this->assinatura_expira_em === null) {
            return true;
        }

        return $this->assinatura_expira_em->copy()->addDays(2)->isFuture();
    }

    public function emPeriodoCarencia(): bool
    {
        return $this->status_pagamento === 'ativo'
            && $this->assinatura_expira_em !== null
            && $this->assinatura_expira_em->isPast()
            && $this->assinatura_expira_em->copy()->addDays(2)->isFuture();
    }

    public function emJanelaVencimentoProximo(): bool
    {
        return $this->status_pagamento === 'ativo'
            && $this->assinatura_expira_em !== null
            && $this->assinatura_expira_em->isFuture()
            && $this->assinatura_expira_em->lte(now()->addDays(5));
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class, 'plano_id');
    }

    public function planoAgendado(): BelongsTo
    {
        return $this->belongsTo(Plano::class, 'plano_agendado_id');
    }

    public function upgradePendente(): BelongsTo
    {
        return $this->belongsTo(Plano::class, 'plano_upgrade_pendente_id');
    }

    public function limparUpgradePendente(): void
    {
        $this->plano_upgrade_pendente_id = null;
        $this->upgrade_transacao_id = null;
        $this->upgrade_dias_extras = 0;
        $this->upgrade_forma_pagamento = null;
        $this->url_checkout = null;
        $this->pagamento_expira_em = null;
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'agencia_id');
    }

    public function users(): HasMany
    {
        return $this->usuarios();
    }

    public function startups(): HasMany
    {
        return $this->hasMany(Startup::class);
    }

    public function kanbanBoards(): HasMany
    {
        return $this->hasMany(KanbanBoard::class, 'agencia_id');
    }
}
