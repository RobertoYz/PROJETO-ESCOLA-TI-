<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plano extends Model
{
    protected $table = 'planos';

    protected $fillable = [
        'nome',
        'preco',
        'id_plano_mercado_pago',
        'limite_startups',
        'limite_membros_equipe'
    ];

    //um plano tem muitas (hasMany) agencias
    public function agencias(): HasMany
    {
        return $this->hasMany(Agencia::class, 'plano_id');
    }
}