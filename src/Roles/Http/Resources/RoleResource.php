<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Http\Resources;

use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Roles\Support\ActorRoles;
use Enadstack\LaravelRoles\Roles\Support\RoleRisk;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

final class RoleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $role = $this->role();
        $lock = self::lock($request, $role);

        return [
            'id' => $role->getKey(),
            'name' => $role->name,
            'label' => $role->getAttribute('label'),
            'label_text' => $role->label_text,
            'description' => $role->getAttribute('description'),
            'description_text' => $role->translated('description'),
            'color' => $role->getAttribute('color'),
            'is_system' => $role->is_system,
            'is_default' => $role->getAttribute('is_default'),
            'sort_order' => $role->getAttribute('sort_order'),
            'members_count' => $this->when($role->getAttribute('members_count') !== null, fn (): int => (int) $role->getAttribute('members_count')),
            'permissions_count' => $this->when($role->getAttribute('permissions_count') !== null, fn (): int => (int) $role->getAttribute('permissions_count')),
            'permissions' => $this->whenLoaded('permissions', fn (): array => $role->permissions->map(fn ($permission): array => [
                'id' => $permission->getKey(),
                'name' => $permission->name,
                'label_text' => $permission->getAttribute('label_text'),
                'risk' => $permission->getAttribute('risk'),
                'module' => $permission->getAttribute('module'),
            ])->values()->all()),
            'deleted_at' => $role->getAttribute('deleted_at'),
            'created_at' => $role->created_at,
            'updated_at' => $role->updated_at,
            'is_owner' => $lock['is_owner'],
            'locked' => $lock['reason'] !== null,
            'lock_reason' => $lock['reason'],
            'abilities' => self::abilities($request, $lock),
        ];
    }

    private function role(): Role
    {
        $role = $this->resource;

        if (! $role instanceof Role) {
            throw new \LogicException('RoleResource wraps a Role.');
        }

        return $role;
    }

    /**
     * Why the role can't be freely edited: the owner role, a system role, or a role the current user holds.
     *
     * @return array{is_owner: bool, reason: 'owner'|'system'|'held'|null, actor_is_owner: bool}
     */
    private static function lock(Request $request, Role $role): array
    {
        $actor = ActorRoles::for($request);
        $isOwner = RoleRisk::isOwnerRole($role) && $role->guard_name === (string) config('roles.guard');

        $reason = match (true) {
            $isOwner => 'owner',
            (bool) $role->is_system => 'system',
            ! $actor['is_owner'] && isset($actor['held'][(int) $role->getKey()]) => 'held',
            default => null,
        };

        return ['is_owner' => $isOwner, 'reason' => $reason, 'actor_is_owner' => $actor['is_owner']];
    }

    /**
     * The policy abilities do not depend on the role and are resolved once per request; the per-role flags
     * only narrow them to what the server would actually accept.
     *
     * @param  array{is_owner: bool, reason: 'owner'|'system'|'held'|null, actor_is_owner: bool}  $lock
     * @return array{create: bool, update: bool, rename: bool, update_permissions: bool, delete: bool, assign: bool}
     */
    private static function abilities(Request $request, array $lock): array
    {
        /** @var array{create: bool, update: bool, delete: bool, assign: bool}|null $cached */
        $cached = $request->attributes->get('roles.abilities');

        if ($cached === null) {
            $model = (string) config('permission.models.role');
            $cached = [
                'create' => Gate::allows('create', $model),
                'update' => Gate::allows('update', $model),
                'delete' => Gate::allows('delete', $model),
                'assign' => Gate::allows('assign', $model),
            ];
            $request->attributes->set('roles.abilities', $cached);
        }

        $fixed = in_array($lock['reason'], ['owner', 'system'], true);

        return [
            'create' => $cached['create'],
            'update' => $cached['update'],
            'rename' => $cached['update'] && ! $fixed,
            'update_permissions' => $cached['update'] && $lock['reason'] !== 'owner' && $lock['reason'] !== 'held',
            'delete' => $cached['delete'] && ! $fixed,
            'assign' => $cached['assign'] && ! ($lock['is_owner'] && ! $lock['actor_is_owner']),
        ];
    }
}
