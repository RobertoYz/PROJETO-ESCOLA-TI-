<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class TermoAceite extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'termos_aceites';
    protected $guarded = ['id', 'retencao_ate', 'preservacao_excepcional'];
    protected $hidden = ['titular_email_hmac', 'hmac_chave_id'];
    protected $casts = ['aceito_em' => 'datetime', 'retencao_ate' => 'datetime', 'preservacao_excepcional' => 'boolean'];

    public function termosUso(): BelongsTo
    {
        return $this->belongsTo(DocumentoLegal::class, 'documento_termos_id');
    }

    public function politicaPrivacidade(): BelongsTo
    {
        return $this->belongsTo(DocumentoLegal::class, 'documento_privacidade_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('O evento de aceite é imutável.'));
        static::deleting(fn () => throw new LogicException('Utilize o expurgo por prazo de retenção.'));
    }
}
