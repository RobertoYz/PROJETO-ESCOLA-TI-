<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KanbanCard extends Model
{
    protected $table = 'kanban_cards';

    protected $fillable = [
        'board_id',
        'column_id',
        'edital_id',
        'title',
        'description',
        'startup_name',
        'position',
        'metadata'
    ];

    protected $casts = [
        'position' => 'double',
        'metadata' => 'array'
    ];

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
