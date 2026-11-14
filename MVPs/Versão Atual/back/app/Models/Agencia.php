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
        'plano_id',
        'id_transacao',
        'url_checkout',
        'status_pagamento',
        'pagamento_expira_em',
        'assinatura_expira_em',
    ];

    protected $casts = [
        'pagamento_expira_em' => 'datetime',
        'assinatura_expira_em' => 'datetime',
    ];

    public function isPagamentoAtivo(): bool
    {
        return $this->status_pagamento === 'ativo'
            && ($this->assinatura_expira_em === null || $this->assinatura_expira_em->isFuture());
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class, 'plano_id');
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
