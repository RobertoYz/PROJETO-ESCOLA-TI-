<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Startup extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::deleting(function (Startup $startup) {
            $perfil = $startup->portalAcesso()->lockForUpdate()->first();
            if ($perfil) {
                $perfil->tokens()->delete();
                $perfil->delete();
            }
        });
    }

    public function delete()
    {
        return $this->getConnection()->transaction(function () {
            $this->newQueryWithoutScopes()->whereKey($this->getKey())->lockForUpdate()->first();
            return parent::delete();
        });
    }

    public function portalAcesso()
    {
        return $this->hasOne(StartupPortalAcesso::class);
    }

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