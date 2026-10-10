<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Queries;

use Enadstack\LaravelRoles\Core\Registry\PackagePermissions;
use Enadstack\LaravelRoles\Roles\Support\ActorRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class MeQuery
{
    /**
     * What the current user may do with the package, for the UI to enable or hide controls.
     *
     * @return array{permissions: list<string>, is_owner: bool, role_ids: list<int>, abilities: array{roles: array<string, bool>, permissions: array<string, bool>, matrix: array<string, bool>, users: array<string, bool>, invitations: array<string, bool>}}
     */
    public static function get(Request $request): array
    {
        $actor = ActorRoles::for($request);
        $user = $request->user();
        $gate = Gate::forUser($user);
        $names = array_map(
            static fn ($definition): string => $definition->name,
            PackagePermissions::definitions(array_keys(array_filter((array) config('roles.modules')))),
        );

        $held = [];
        if ($actor['is_owner']) {
            $held = $names;
        } elseif ($user !== null && method_exists($user, 'getAllPermissions')) {
            $owned = array_flip($user->getAllPermissions()->where('guard_name', (string) config('roles.guard'))->whereNull('deprecated_at')->pluck('name')->all());
            $held = array_values(array_filter($names, static fn (string $name): bool => isset($owned[$name])));
        }

        $roleModel = (string) config('permission.models.role');
        $permissionModel = (string) config('permission.models.permission');
        $live = $actor['live'];
        sort($live);
        $usersOn = (bool) config('roles.modules.users');
        $invitationsOn = $usersOn && (bool) config('roles.modules.invitations');
        $can = static fn (bool $on, string $ability): bool => $on && $gate->allows($ability);

        return [
            'permissions' => $held,
            'is_owner' => $actor['is_owner'],
            'role_ids' => $live,
            'abilities' => [
                'roles' => [
                    'view' => $gate->allows('viewAny', $roleModel),
                    'create' => $gate->allows('create', $roleModel),
                    'update' => $gate->allows('update', $roleModel),
                    'delete' => $gate->allows('delete', $roleModel),
                    'assign' => $gate->allows('assign', $roleModel),
                ],
                'permissions' => [
                    'view' => $gate->allows('viewAny', $permissionModel),
                    'sync' => $gate->allows('sync', $permissionModel),
                ],
                'matrix' => [
                    'view' => $gate->allows('viewAny', $permissionModel),
                    'update' => $gate->allows('update', $roleModel),
                ],
                'users' => [
                    'view' => $can($usersOn, 'access.users.view'),
                    'update' => $can($usersOn, 'access.users.update'),
                    'suspend' => $can($usersOn, 'access.users.suspend'),
                    'delete' => $can($usersOn, 'access.users.delete'),
                ],
                'invitations' => [
                    'view' => $can($invitationsOn, 'access.invitations.view'),
                    'create' => $can($invitationsOn, 'access.invitations.create'),
                    'revoke' => $can($invitationsOn, 'access.invitations.revoke'),
                ],
            ],
        ];
    }
}
