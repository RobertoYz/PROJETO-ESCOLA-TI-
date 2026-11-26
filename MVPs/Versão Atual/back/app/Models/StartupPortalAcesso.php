<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class StartupPortalAcesso extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'startup_portal_acessos';
    protected $fillable = ['login', 'password', 'ativo'];
    protected $hidden = ['password'];
    protected $casts = ['ativo' => 'boolean', 'ultimo_acesso_em' => 'datetime'];

    public function startup()
    {
        return $this->belongsTo(Startup::class);
    }

    public function agencia()
    {
        return $this->belongsTo(Agencia::class);
    }

    public function setLoginAttribute(string $login): void
    {
        $this->attributes['login'] = strtolower(trim($login));
    }

    public function disponivel(): bool
    {
        return $this->ativo && $this->startup
            && (int) $this->startup->agencia_id === (int) $this->agencia_id
            && $this->agencia?->isPagamentoAtivo();
    }

    public static function resumo(?self $perfil): array
    {
        return [
            'existe' => $perfil !== null,
            'ativo' => $perfil?->ativo ?? false,
            'login' => $perfil?->login,
            'ultimo_acesso_em' => $perfil?->ultimo_acesso_em,
        ];
    }
}
