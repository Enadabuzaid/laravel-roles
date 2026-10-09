<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Support;

use Enadstack\LaravelRoles\Users\Enums\UserStatus;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * How a target user stands relative to the acting user for management operations: the single
 * source of truth shared by the TargetAboveActorRule guardrail (which refuses) and the user
 * resources (which show `lock_reason`), so the two cannot drift.
 *
 * Reasons, in precedence order: `self` (the actor is the target), then, unless the actor is an owner,
 * `owner` (the target holds the super role) and `above` (the target holds a permission the actor lacks).
 * Everything is computed with a fixed number of queries however many targets are evaluated.
 */
final class TargetStanding
{
    /**
     * @param  iterable<Model>  $targets
     * @return array<int|string, array{is_owner: bool, reason: 'self'|'owner'|'above'|null}> keyed by target key
     */
    public function evaluate(Model $actor, iterable $targets): array
    {
        $keys = [];
        $models = [];
        foreach ($targets as $target) {
            $models[] = $target;
            $keys[] = $target->getKey();
        }

        if ($models === []) {
            return [];
        }

        $actorKey = $actor->getKey();
        $owners = $this->ownerKeys($actor, [...$keys, $actorKey]);
        // An owner who is suspended (e.g. with a surviving session) is no owner for this purpose.
        $actorIsOwner = isset($owners[$actorKey]) && $this->countsAsActive($actorKey);

        $needPermissions = [];
        if (! $actorIsOwner) {
            foreach ($models as $target) {
                if ($target->getKey() !== $actorKey && ! isset($owners[$target->getKey()])) {
                    $needPermissions[] = $target->getKey();
                }
            }
        }

        $permissions = $needPermissions === [] ? [] : $this->permissionNames($actor, [...$needPermissions, $actorKey]);
        $held = $permissions[$actorKey] ?? [];

        $result = [];
        foreach ($models as $target) {
            $key = $target->getKey();
            $isOwner = isset($owners[$key]);

            $reason = match (true) {
                $key === $actorKey => 'self',
                $actorIsOwner => null,
                $isOwner => 'owner',
                array_diff_key($permissions[$key] ?? [], $held) !== [] => 'above',
                default => null,
            };

            $result[$key] = ['is_owner' => $isOwner, 'reason' => $reason];
        }

        return $result;
    }

    /** @return 'self'|'owner'|'above'|null */
    public function reasonFor(Model $actor, Model $target): ?string
    {
        return $this->evaluate($actor, [$target])[$target->getKey()]['reason'];
    }

    private function countsAsActive(int|string $key): bool
    {
        $status = DB::table((new UserAccessState)->getTable())->where('user_id', $key)->value('status');

        return ! is_string($status) || (UserStatus::tryFrom($status)?->countsAsActiveOwner() ?? true);
    }

    /**
     * Users (of the given keys) holding a live super role of the configured guard.
     *
     * @param  list<int|string>  $keys
     * @return array<int|string, true>
     */
    public function ownerKeys(Model $sample, array $keys): array
    {
        $tables = (array) config('permission.table_names');
        $roles = (string) ($tables['roles'] ?? 'roles');
        $pivot = (string) ($tables['model_has_roles'] ?? 'model_has_roles');
        $morphKey = (string) config('permission.column_names.model_morph_key', 'model_id');

        $owners = [];
        $rows = DB::table($pivot)
            ->join($roles, $roles.'.id', '=', $pivot.'.role_id')
            ->where($pivot.'.model_type', $sample->getMorphClass())
            ->whereIn($pivot.'.'.$morphKey, array_values(array_unique($keys)))
            ->where($roles.'.guard_name', (string) config('roles.guard'))
            ->where($roles.'.name', (string) config('roles.super_role'))
            ->whereNull($roles.'.deleted_at')
            ->pluck($pivot.'.'.$morphKey);

        foreach ($rows as $key) {
            $owners[is_numeric($key) ? (int) $key : (string) $key] = true;
        }

        return $owners;
    }

    /**
     * Effective permission names (through live roles of the guard, plus direct) per user key.
     *
     * @param  list<int|string>  $keys
     * @return array<int|string, array<string, true>>
     */
    private function permissionNames(Model $sample, array $keys): array
    {
        $tables = (array) config('permission.table_names');
        $roles = (string) ($tables['roles'] ?? 'roles');
        $permissions = (string) ($tables['permissions'] ?? 'permissions');
        $modelRoles = (string) ($tables['model_has_roles'] ?? 'model_has_roles');
        $modelPermissions = (string) ($tables['model_has_permissions'] ?? 'model_has_permissions');
        $rolePermissions = (string) ($tables['role_has_permissions'] ?? 'role_has_permissions');
        $morphKey = (string) config('permission.column_names.model_morph_key', 'model_id');
        $guard = (string) config('roles.guard');
        $keys = array_values(array_unique($keys));

        $viaRoles = DB::table($modelRoles)
            ->join($roles, $roles.'.id', '=', $modelRoles.'.role_id')
            ->join($rolePermissions, $rolePermissions.'.role_id', '=', $roles.'.id')
            ->join($permissions, $permissions.'.id', '=', $rolePermissions.'.permission_id')
            ->where($modelRoles.'.model_type', $sample->getMorphClass())
            ->whereIn($modelRoles.'.'.$morphKey, $keys)
            ->where($roles.'.guard_name', $guard)
            ->whereNull($roles.'.deleted_at')
            ->where($permissions.'.guard_name', $guard)
            ->whereNull($permissions.'.deprecated_at')
            ->get([$modelRoles.'.'.$morphKey.' as user_key', $permissions.'.name as permission_name']);

        $direct = DB::table($modelPermissions)
            ->join($permissions, $permissions.'.id', '=', $modelPermissions.'.permission_id')
            ->where($modelPermissions.'.model_type', $sample->getMorphClass())
            ->whereIn($modelPermissions.'.'.$morphKey, $keys)
            ->where($permissions.'.guard_name', $guard)
            ->whereNull($permissions.'.deprecated_at')
            ->get([$modelPermissions.'.'.$morphKey.' as user_key', $permissions.'.name as permission_name']);

        $names = [];
        foreach ([$viaRoles, $direct] as $rows) {
            foreach ($rows as $row) {
                $key = is_numeric($row->user_key) ? (int) $row->user_key : (string) $row->user_key;
                $names[$key][(string) $row->permission_name] = true;
            }
        }

        return $names;
    }
}
