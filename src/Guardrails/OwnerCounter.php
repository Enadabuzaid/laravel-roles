<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Guardrails;

use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Users\Enums\UserStatus;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Enadstack\LaravelRoles\Users\Support\TargetStanding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class OwnerCounter
{
    /**
     * Active owners: users holding the super role whose access state is not suspended, deactivated, invited or
     * pending verification (a locked owner still counts: lockouts are transient). A missing state row counts as active.
     * Fail-closed on purpose: it needs the `user_access_states` table, so guardrailed writes fail loudly without it
     * (the doctor `users-state` check reports that state).
     */
    public function count(): int
    {
        $role = $this->superRole();

        return $role === null ? 0 : $this->activeOnly($role->users())->count();
    }

    /** Role-based: whether the user holds the super role, whatever their access state. */
    public function isOwner(Model $user): bool
    {
        return isset(app(TargetStanding::class)->ownerKeys($user, [$user->getKey()])[$user->getKey()]);
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
                ->whereIn($states.'.status', array_map(
                    static fn (UserStatus $status): string => $status->value,
                    array_values(array_filter(UserStatus::cases(), static fn (UserStatus $status): bool => ! $status->countsAsActiveOwner())),
                ));
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
