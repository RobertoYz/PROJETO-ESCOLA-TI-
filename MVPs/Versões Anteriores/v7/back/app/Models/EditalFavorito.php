<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EditalFavorito extends Model
{
    protected $table = 'edital_favoritos';

    protected $fillable = [
        'user_id',
        'edital_id',
        'startup_id'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function edital(): BelongsTo
    {
        return $this->belongsTo(Edital::class);
    }

    public function startup(): BelongsTo
    {
        return $this->belongsTo(Startup::class);
    }
}
