<?php

namespace App\Policies;

use App\Enums\Permissao;
use App\Models\User;

class EquipePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->agencia_id !== null
            && ($user->isOwner() || $user->temPermissao(Permissao::EQUIPE_GERENCIAR));
    }

    public function create(User $user): bool
    {
        return $user->agencia_id !== null
            && ($user->isOwner() || $user->temPermissao(Permissao::EQUIPE_GERENCIAR));
    }

    public function manageMember(User $actor, User $target): bool
    {
        if (!$this->viewAny($actor)) {
            return false;
        }

        if ($actor->agencia_id === null || (int) $actor->agencia_id !== (int) $target->agencia_id) {
            return false;
        }

        if ((int) $actor->id === (int) $target->id) {
            return false;
        }

        if ($target->isOwner()) {
            return false;
        }

        $agencia = $actor->agencia;
        if ($agencia && $agencia->owner_id !== null && (int) $agencia->owner_id === (int) $target->id) {
            return false;
        }

        if (!$actor->isOwner()) {
            $permissoesTarget = is_array($target->permissoes) ? $target->permissoes : [];
            $targetTemEquipeGerenciar = in_array(Permissao::EQUIPE_GERENCIAR->value, $permissoesTarget, true)
                || $target->temPermissao(Permissao::EQUIPE_GERENCIAR);

            if ($targetTemEquipeGerenciar) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, string> $permissoesSolicitadas
     */
    public function assignPermissions(User $actor, array $permissoesSolicitadas, ?User $target = null): bool
    {
        if (!$this->create($actor)) {
            return false;
        }

        if ($target !== null && !$this->manageMember($actor, $target)) {
            return false;
        }

        if (in_array(Permissao::ASSINATURA_GERENCIAR->value, $permissoesSolicitadas, true)) {
            return false;
        }

        if ($actor->isOwner()) {
            return true;
        }

        if (in_array(Permissao::EQUIPE_GERENCIAR->value, $permissoesSolicitadas, true)) {
            return false;
        }

        $permissoesAtor = $actor->permissoesEfetivas();
        foreach ($permissoesSolicitadas as $permissao) {
            if (!in_array($permissao, $permissoesAtor, true)) {
                return false;
            }
        }

        return true;
    }
}
