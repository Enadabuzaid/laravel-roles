<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Http\Requests;

use Enadstack\LaravelRoles\Models\Role;

/**
 * Looks up live (non-trashed) roles of the configured guard by name (string) or id (int).
 */
trait ResolvesRoleReferences
{
    protected function findRole(mixed $reference): ?Role
    {
        if (! is_string($reference) && ! is_int($reference)) {
            return null;
        }

        $query = Role::query()->where('guard_name', (string) config('roles.guard'));

        return (is_int($reference) ? $query->whereKey($reference) : $query->where('name', $reference))->first();
    }
}
