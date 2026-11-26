<?php

namespace App\Models;

use App\Traits\ExcluiAnexosDoKanban;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KanbanColumn extends Model
{
    use ExcluiAnexosDoKanban;

    protected const CHAVE_CARD = 'column_id';

    protected $table = 'kanban_columns';

    protected $fillable = [
        'board_id',
        'title',
        'position',
        'slug'
    ];

    protected $casts = [
        'position' => 'double'
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(KanbanBoard::class, 'board_id');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(KanbanCard::class, 'column_id')->orderBy('position');
    }
}
