<?php

namespace App\Models;

use App\Enums\Permissao;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'agencia_id',
        'perfil_acesso',
        'role',
        'permissoes',
        'status',
        'convite_token',
        'convite_codigo_criptografado',
        'convite_expira_em',
        'convite_tentativas',
        'convidado_por_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'convite_token',
        'convite_codigo_criptografado',
    ];

    public function setRoleAttribute($value): void
    {
        $this->attributes['perfil_acesso'] = $value;
    }

    public function getRoleAttribute(): string
    {
        return $this->attributes['perfil_acesso'] ?? 'consultor_admin';
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissoes' => 'array',
            'convite_codigo_criptografado' => 'encrypted',
            'convite_expira_em' => 'datetime',
            'convite_tentativas' => 'integer',
        ];
    }

    public function agencia(): BelongsTo
    {
        return $this->belongsTo(Agencia::class, 'agencia_id');
    }

    public function convidadoPor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'convidado_por_id');
    }

    public function favoritos(): HasMany
    {
        return $this->hasMany(EditalFavorito::class, 'user_id');
    }

    public function isOwner(): bool
    {
        $statusAtual = $this->attributes['status'] ?? 'ativo';
        if ($statusAtual !== 'ativo') {
            return false;
        }

        $perfil = $this->attributes['perfil_acesso'] ?? 'consultor_admin';

        if ($perfil === 'admin_global') {
            return true;
        }

        $agencia = $this->agencia;
        if ($agencia && $agencia->owner_id !== null) {
            return (int) $agencia->owner_id === (int) $this->id;
        }

        return $perfil === 'consultor_admin';
    }

    public function temPermissao(string|Permissao $permissao): bool
    {
        $statusAtual = $this->attributes['status'] ?? 'ativo';
        if ($statusAtual !== 'ativo') {
            return false;
        }

        $codigo = $permissao instanceof Permissao ? $permissao->value : (string) $permissao;

        if ($this->isOwner()) {
            return true;
        }

        if ($codigo === Permissao::ASSINATURA_GERENCIAR->value) {
            return false;
        }

        $permissoesBrutas = $this->permissoes;
        $perfil = $this->attributes['perfil_acesso'] ?? 'consultor_admin';

        if ($permissoesBrutas === null) {
            if ($perfil === 'consultor_padrao') {
                return in_array($codigo, [
                    Permissao::STARTUPS_CRIAR_EDITAR->value,
                    Permissao::KANBAN_GERENCIAR->value,
                    Permissao::MINUTAS_GERENCIAR->value,
                ], true);
            }

            if ($perfil === 'consultor_admin') {
                return in_array($codigo, Permissao::valoresDelegaveis(true), true);
            }

            return false;
        }

        return in_array($codigo, $this->permissoesEfetivas(), true);
    }

    /**
     * @return array<int, string>
     */
    public function permissoesEfetivas(): array
    {
        $statusAtual = $this->attributes['status'] ?? 'ativo';
        if ($statusAtual !== 'ativo') {
            return [];
        }

        if ($this->isOwner()) {
            return Permissao::valores();
        }

        $permissoesBrutas = $this->permissoes;
        $perfil = $this->attributes['perfil_acesso'] ?? 'consultor_admin';

        if ($permissoesBrutas === null) {
            if ($perfil === 'consultor_padrao') {
                return [
                    Permissao::STARTUPS_CRIAR_EDITAR->value,
                    Permissao::KANBAN_GERENCIAR->value,
                    Permissao::MINUTAS_GERENCIAR->value,
                ];
            }

            if ($perfil === 'consultor_admin') {
                return Permissao::valoresDelegaveis(true);
            }

            return [];
        }

        if (!is_array($permissoesBrutas)) {
            return [];
        }

        $delegaveis = Permissao::valoresDelegaveis(true);

        return array_values(array_unique(array_filter(
            $permissoesBrutas,
            fn ($item) => is_string($item) && in_array($item, $delegaveis, true)
        )));
    }
}
