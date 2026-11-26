<?php

namespace App\Models;

use App\Traits\ExcluiAnexosDoKanban;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KanbanBoard extends Model
{
    use ExcluiAnexosDoKanban;

    protected const CHAVE_CARD = 'board_id';

    protected $table = 'kanban_boards';

    protected $fillable = [
        'agencia_id',
        'title'
    ];

    public function agencia(): BelongsTo
    {
        return $this->belongsTo(Agencia::class, 'agencia_id');
    }

    public function columns(): HasMany
    {
        return $this->hasMany(KanbanColumn::class, 'board_id')->orderBy('position');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(KanbanCard::class, 'board_id')->orderBy('position');
    }
}
