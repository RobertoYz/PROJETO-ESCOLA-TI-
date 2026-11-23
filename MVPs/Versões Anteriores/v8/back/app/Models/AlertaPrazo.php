<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertaPrazo extends Model
{
    protected $table = 'alerta_prazos';

    protected $fillable = [
        'user_id',
        'startup_id',
        'edital_id',
        'kanban_card_id',
        'tipo_prazo',
        'data_limite',
        'dias_restantes',
        'nivel_urgencia',
        'titulo',
        'mensagem',
        'lido',
        'email_enviado',
        'email_enviado_em'
    ];

    protected $casts = [
        'data_limite' => 'datetime',
        'dias_restantes' => 'integer',
        'lido' => 'boolean',
        'email_enviado' => 'boolean',
        'email_enviado_em' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function startup(): BelongsTo
    {
        return $this->belongsTo(Startup::class);
    }

    public function edital(): BelongsTo
    {
        return $this->belongsTo(Edital::class);
    }

    public function kanbanCard(): BelongsTo
    {
        return $this->belongsTo(KanbanCard::class, 'kanban_card_id');
    }
}

