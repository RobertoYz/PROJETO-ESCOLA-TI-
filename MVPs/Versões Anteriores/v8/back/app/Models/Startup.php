<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Startup extends Model
{
    use HasFactory;

    protected $fillable = [
        'agencia_id',
        'cnpj',
        'razao_social',
        'nome_fantasia',
        'data_abertura',
        'email',
        'telefone',
        'cep',
        'logradouro',
        'numero',
        'bairro',
        'cidade',
        'estado',
        'porte',
        'regime_tributario',
        'nicho_atuacao',
        'pitch'
    ];

    /**
     * Relacionamento: Uma startup pertence a uma agência.
     */
    public function agencia()
    {
        return $this->belongsTo(Agencia::class);
    }
}