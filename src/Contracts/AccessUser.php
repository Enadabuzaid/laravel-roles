<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Contracts;

use BackedEnum;
use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Role;

/**
 * Role-related methods a user model gains from Spatie's HasRoles trait.
 */
interface AccessUser
{
    /**
     * @param  string|int|array<mixed>|Role|Collection<int, mixed>|BackedEnum  ...$roles
     * @return $this
     */
    public function assignRole(...$roles): static;

    /**
     * @param  string|int|array<mixed>|Role|Collection<int, mixed>|BackedEnum  ...$role
     * @return $this
     */
    public function removeRole(...$role): static;

    /**
     * @param  string|int|array<mixed>|Role|Collection<int, mixed>|BackedEnum  ...$roles
     * @return $this
     */
    public function syncRoles(...$roles): static;

    /**
     * @param  string|int|array<mixed>|Role|Collection<int, mixed>|BackedEnum  $roles
     */
    public function hasRole($roles, ?string $guard = null): bool;

    /**
     * @return Collection<int, string>
     */
    public function getRoleNames(): Collection;
}
