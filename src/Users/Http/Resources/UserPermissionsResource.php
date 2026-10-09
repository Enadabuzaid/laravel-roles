<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Resources;

use Enadstack\LaravelRoles\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Effective permissions (from UserPermissionsQuery::entries) grouped by module. `sources` lists
 * `role:<name>` for each granting role and `direct` for a direct grant; `roles`/`direct` give the same split.
 */
final class UserPermissionsResource extends JsonResource
{
    /** @return array<int, array<string, mixed>> */
    public function toArray(Request $request): array
    {
        /** @var list<array{permission: Permission, roles: list<string>, direct: bool}> $entries */
        $entries = (array) $this->resource;
        $groups = [];

        foreach ($entries as $entry) {
            $permission = $entry['permission'];
            $module = (string) ($permission->module ?? 'other');

            $groups[$module]['module'] = $module;
            $groups[$module]['permissions'][] = [
                'id' => $permission->getKey(),
                'name' => $permission->name,
                'module' => $permission->module,
                'label_text' => $permission->label_text,
                'risk' => $permission->risk,
                'is_deprecated' => $permission->deprecated_at !== null,
                'sources' => [...array_map(static fn (string $role): string => 'role:'.$role, $entry['roles']), ...($entry['direct'] ? ['direct'] : [])],
                'roles' => $entry['roles'],
                'direct' => $entry['direct'],
            ];
        }

        ksort($groups);

        return array_values(array_map(static fn (array $group): array => [
            'module' => $group['module'],
            'permissions_count' => count($group['permissions']),
            'permissions' => $group['permissions'],
        ], $groups));
    }
}
