<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Guardrails;

use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Users\Enums\UserStatus;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class OwnerCounter
{
    /**
     * Active owners: users holding the super role whose access state is active or missing.
     * Suspended, deactivated, locked and not-yet-activated owners never count towards `min_owners`.
     */
    public function count(): int
    {
        $role = $this->superRole();

        return $role === null ? 0 : $this->activeOnly($role->users())->count();
    }

    /** Role-based: whether the user holds the super role, whatever their access state. */
    public function isOwner(Model $user): bool
    {
        $role = $this->superRole();

        return $role !== null && $role->users()->whereKey($user->getKey())->exists();
    }

    /** An owner whose access state is active or missing. */
    public function isActiveOwner(Model $user): bool
    {
        $role = $this->superRole();

        return $role !== null && $this->activeOnly($role->users()->whereKey($user->getKey()))->exists();
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

    /**
     * @template TRelation of BelongsToMany
     *
     * @param  TRelation  $users
     * @return TRelation
     */
    private function activeOnly(BelongsToMany $users): BelongsToMany
    {
        $states = (new UserAccessState)->getTable();

        return $users->whereNotExists(static function (QueryBuilder $query) use ($users, $states): void {
            $query->from($states)
                ->whereColumn($states.'.user_id', $users->getModel()->getQualifiedKeyName())
                ->where($states.'.status', '!=', UserStatus::Active->value);
        });
    }

    private function superRole(): ?Role
    {
        return Role::query()
            ->where('name', (string) config('roles.super_role'))
            ->where('guard_name', (string) config('roles.guard'))
            ->first();
    }
}
