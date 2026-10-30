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
        'billing_id',
        'id_cobranca',
        'status_pagamento',
        'pagamento_ativo',
        'checkout_url',
        'url_checkout',
        'pagamento_expira_em'
    ];

    protected $casts = [
        'pagamento_ativo' => 'boolean',
        'pagamento_expira_em' => 'datetime',
    ];

    public function setNomeFantasiaAttribute($valor)
    {
        $this->attributes['nome'] = $valor;
    }

    public function getNomeFantasiaAttribute()
    {
        return $this->attributes['nome'] ?? null;
    }

    public function getPagamentoAtivoAttribute()
    {
        return !empty($this->attributes['pagamento_ativo']) || ($this->attributes['status_pagamento'] ?? '') === 'ativo';
    }

    public function setPagamentoAtivoAttribute($valor)
    {
        $this->attributes['pagamento_ativo'] = (bool) $valor;
        if ($valor) {
            $this->attributes['status_pagamento'] = 'ativo';
        }
    }

    public function getBillingIdAttribute()
    {
        return $this->attributes['billing_id'] 
            ?? $this->attributes['id_cobranca'] 
            ?? $this->attributes['id_plataforma_pagamento'] 
            ?? null;
    }

    public function setBillingIdAttribute($valor)
    {
        $this->attributes['billing_id'] = $valor;
        $this->attributes['id_cobranca'] = $valor;
        $this->attributes['id_plataforma_pagamento'] = $valor;
    }

    public function getIdCobrancaAttribute()
    {
        return $this->getBillingIdAttribute();
    }

    public function setIdCobrancaAttribute($valor)
    {
        $this->setBillingIdAttribute($valor);
    }

    public function getCheckoutUrlAttribute()
    {
        return $this->attributes['checkout_url'] ?? $this->attributes['url_checkout'] ?? null;
    }

    public function setCheckoutUrlAttribute($valor)
    {
        $this->attributes['checkout_url'] = $valor;
        $this->attributes['url_checkout'] = $valor;
    }

    public function getUrlCheckoutAttribute()
    {
        return $this->getCheckoutUrlAttribute();
    }

    public function setUrlCheckoutAttribute($valor)
    {
        $this->setCheckoutUrlAttribute($valor);
    }

    public function getStatusAttribute()
    {
        return $this->attributes['status_pagamento'] ?? 'pendente';
    }

    public function setStatusAttribute($valor)
    {
        $this->attributes['status_pagamento'] = $valor;
    }

    // Uma agência pertence a um plano
    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class, 'plano_id');
    }

    // Uma agência tem muitos usuários
    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'agencia_id');
    }

    // Relacionamento das agências com startups
    public function startups(): HasMany
    {
        return $this->hasMany(Startup::class);
    }
}