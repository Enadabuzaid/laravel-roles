<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Guardrails;

use Enadstack\LaravelRoles\Models\Role;
use Illuminate\Database\Eloquent\Model;

final class OwnerCounter
{
    public function count(): int
    {
        $role = $this->superRole();

        return $role === null ? 0 : $role->users()->count();
    }

    public function isOwner(Model $user): bool
    {
        $role = $this->superRole();

        return $role !== null && $role->users()->whereKey($user->getKey())->exists();
    }

    /**
     * Locks the super-role row so concurrent owner-affecting operations serialize (call inside a transaction).
     */
    public function lockSuperRole(): void
    {
        Role::withTrashed()
            ->where('name', (string) config('roles.super_role'))
            ->where('guard_name', (string) config('roles.guard'))
            ->lockForUpdate()
            ->first();
    }

    private function superRole(): ?Role
    {
        return Role::query()
            ->where('name', (string) config('roles.super_role'))
            ->where('guard_name', (string) config('roles.guard'))
            ->first();
    }
}
