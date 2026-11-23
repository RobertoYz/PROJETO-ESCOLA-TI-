<?php

namespace App\Policies;

use App\Enums\Permissao;
use App\Models\Startup;
use App\Models\User;

class StartupPolicy
{
    public function create(User $user): bool
    {
        return $user->agencia_id !== null
            && $user->temPermissao(Permissao::STARTUPS_CRIAR_EDITAR);
    }

    public function view(User $user, Startup $startup): bool
    {
        return $user->agencia_id !== null
            && ($user->status ?? 'ativo') === 'ativo'
            && (int) $user->agencia_id === (int) $startup->agencia_id;
    }

    public function update(User $user, Startup $startup): bool
    {
        return $user->agencia_id !== null
            && (int) $user->agencia_id === (int) $startup->agencia_id
            && $user->temPermissao(Permissao::STARTUPS_CRIAR_EDITAR);
    }

    public function delete(User $user, Startup $startup): bool
    {
        return $user->agencia_id !== null
            && (int) $user->agencia_id === (int) $startup->agencia_id
            && $user->temPermissao(Permissao::STARTUPS_EXCLUIR);
    }
}
