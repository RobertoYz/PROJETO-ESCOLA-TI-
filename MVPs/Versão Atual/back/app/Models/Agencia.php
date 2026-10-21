<?php

namespace App\Models;

use App\Models\Plano;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agencia extends Model
{
    protected $table = 'agencias';

    protected $fillable = [
        'nome',
        'nome_fantasia',
        'plano_id',
        'id_plataforma_pagamento',
        'status_pagamento'
    ];

    public function setNomeFantasiaAttribute($value)
    {
        $this->attributes['nome'] = $value;
    }

    public function getNomeFantasiaAttribute()
    {
        return $this->attributes['nome'] ?? null;
    }

    // Uma agencia pertence a (belongsTo) um plano
    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class, 'plano_id');
    }

    // Uma agencia tem muitos (hasMany) usuarios
    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'agencia_id');
    }

    // Relacionamento das agencias com startups
    public function startups()
    {
        return $this->hasMany(Startup::class);
    }
}