<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Concerns;

use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Roles\Actions\AssignRoleToUser;
use Enadstack\LaravelRoles\Roles\Actions\RemoveRoleFromUser;
use Enadstack\LaravelRoles\Roles\Actions\SyncUserRoles;
use Enadstack\LaravelRoles\Users\Enums\UserStatus;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Permission\Traits\HasRoles;

trait HasAccess
{
    use HasRoles;

    public function assignAccessRole(string|Role $role, bool $confirmed = false): static
    {
        app(AssignRoleToUser::class)->handle($this, $role, $confirmed);

        return $this;
    }

    public function removeAccessRole(string|Role $role): static
    {
        app(RemoveRoleFromUser::class)->handle($this, $role);

        return $this;
    }

    /** @param  list<string|Role>  $roles */
    public function syncAccessRoles(array $roles, bool $confirmed = false): static
    {
        app(SyncUserRoles::class)->handle($this, $roles, $confirmed);

        return $this;
    }

    public function isOwner(): bool
    {
        return $this->hasRole((string) config('roles.super_role'));
    }

    /** @return HasOne<UserAccessState, $this> */
    public function accessState(): HasOne
    {
        return $this->hasOne(UserAccessState::class, 'user_id');
    }

    /** Active when the user has no state row (hosts that never backfilled). */
    public function accessStatus(): UserStatus
    {
        $state = $this->accessState;

        return $state instanceof UserAccessState
            ? $state->status
            : (UserStatus::tryFrom((string) config('roles.users.default_status', 'active')) ?? UserStatus::Active);
    }

    public function isAccessActive(): bool
    {
        return $this->accessStatus() === UserStatus::Active;
    }
}
