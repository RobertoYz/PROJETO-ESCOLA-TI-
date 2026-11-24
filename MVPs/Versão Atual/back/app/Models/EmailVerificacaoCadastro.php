<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailVerificacaoCadastro extends Model
{
    protected $table = 'email_verificacoes_cadastro';

    protected $guarded = ['id'];

    protected $hidden = ['codigo_hash', 'verification_token_hash'];

    protected function casts(): array
    {
        return [
            'codigo_expira_em' => 'datetime',
            'ultimo_envio_em' => 'datetime',
            'token_expira_em' => 'datetime',
            'verificado_em' => 'datetime',
            'consumido_em' => 'datetime',
        ];
    }
}
