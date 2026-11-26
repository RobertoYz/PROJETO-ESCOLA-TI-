<?php

namespace App\Models;

use App\Traits\ExcluiAnexosDoKanban;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KanbanCard extends Model
{
    use ExcluiAnexosDoKanban;

    protected const CHAVE_CARD = 'id';

    protected $table = 'kanban_cards';

    protected $fillable = [
        'board_id',
        'column_id',
        'edital_id',
        'title',
        'description',
        'startup_id',
        'startup_name',
        'position',
        'metadata'
    ];

    protected $casts = [
        'position' => 'double',
        'metadata' => 'array'
    ];

    public function anexos(): HasMany
    {
        return $this->hasMany(KanbanAnexo::class, 'card_id')->orderByDesc('tamanho_bytes')->orderBy('id');
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(KanbanBoard::class, 'board_id');
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(KanbanColumn::class, 'column_id');
    }

    public function edital(): BelongsTo
    {
        return $this->belongsTo(Edital::class, 'edital_id');
    }
}
