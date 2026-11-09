<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertaMatch extends Model
{
    protected $table = 'alerta_matches';

    protected $fillable = [
        'user_id',
        'startup_id',
        'edital_id',
        'score_match',
        'titulo',
        'mensagem',
        'criterios',
        'lido'
    ];

    protected $casts = [
        'criterios' => 'array',
        'lido' => 'boolean',
        'score_match' => 'integer'
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
}
