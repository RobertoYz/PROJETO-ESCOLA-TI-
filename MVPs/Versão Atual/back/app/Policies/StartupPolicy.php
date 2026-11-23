<?php

namespace App\Policies;

use App\Models\Startup;
use App\Models\User;

class StartupPolicy
{
    public function create(User $user): bool
    {
        return $user->agencia_id !== null
            && in_array($user->perfil_acesso, ['admin_global', 'consultor_admin', 'consultor_padrao'], true);
    }

    public function view(User $user, Startup $startup): bool
    {
        return $user->agencia_id !== null
            && (int) $user->agencia_id === (int) $startup->agencia_id;
    }

    public function update(User $user, Startup $startup): bool
    {
        return $user->agencia_id !== null
            && (int) $user->agencia_id === (int) $startup->agencia_id;
    }

    public function delete(User $user, Startup $startup): bool
    {
        if ($user->agencia_id === null || (int) $user->agencia_id !== (int) $startup->agencia_id) {
            return false;
        }

        $agencia = $user->agencia;
        $ehTitular = $agencia && $agencia->owner_id !== null && (int) $agencia->owner_id === (int) $user->id;
        $ehConsultorAdmin = in_array($user->perfil_acesso, ['consultor_admin', 'admin_global'], true);

        return $ehTitular || $ehConsultorAdmin;
    }
}
