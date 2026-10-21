<?php

namespace App\Models;

use App\Models\Agencia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'agencia_id',     
        'perfil_acesso',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function setRoleAttribute($value)
    {
        $this->attributes['perfil_acesso'] = $value;
    }

    public function getRoleAttribute()
    {
        return $this->attributes['perfil_acesso'] ?? 'consultor_admin';
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relacionamento entre um user que pertence a (belongsTo) uma agencia.
    public function agencia(): BelongsTo
    {
        return $this->belongsTo(Agencia::class, 'agencia_id');
    }
}