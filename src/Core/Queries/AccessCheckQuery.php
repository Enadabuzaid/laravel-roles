<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Queries;

use Enadstack\LaravelRoles\Contracts\AccessUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

final class AccessCheckQuery
{
    /**
     * @return array{allowed: bool, via: 'owner'|'role'|'direct'|null, roles: list<string>}
     */
    public static function check(int|string $userId, string $permission): array
    {
        $model = config('roles.user_model');

        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            throw new \LogicException('roles.user_model must be an Eloquent model class.');
        }

        $user = $model::query()->findOrFail($userId);

        if (! $user instanceof AccessUser) {
            throw new \LogicException('roles.user_model must implement AccessUser.');
        }

        $guard = (string) config('roles.guard');
        $allowed = Gate::forUser($user)->allows($permission);
        $super = config('roles.super_role');

        if (is_string($super) && $super !== '' && $user->hasRole($super)) {
            return ['allowed' => $allowed, 'via' => $allowed ? 'owner' : null, 'roles' => []];
        }

        /** @var list<string> $roles */
        /** @phpstan-ignore method.notFound */
        $roles = $user->roles()->where('guard_name', $guard)->whereHas('permissions', static fn ($query) => $query->where('name', $permission)->where('guard_name', $guard))
            ->orderBy('name')->pluck('name')->all();

        if ($roles !== []) {
            return ['allowed' => $allowed, 'via' => 'role', 'roles' => $roles];
        }

        /** @phpstan-ignore method.notFound */
        $direct = $user->permissions()->where('guard_name', $guard)->where('name', $permission)->exists();

        return ['allowed' => $allowed, 'via' => $direct ? 'direct' : null, 'roles' => []];
    }
}
