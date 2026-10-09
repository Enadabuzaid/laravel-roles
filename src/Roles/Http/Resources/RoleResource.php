<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Http\Resources;

use Enadstack\LaravelRoles\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

final class RoleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $role = $this->role();

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
            'abilities' => self::abilities($request),
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
     * The policy abilities do not depend on the role, so they are resolved once per request.
     *
     * @return array{update: bool, delete: bool, assign: bool}
     */
    private static function abilities(Request $request): array
    {
        /** @var array{update: bool, delete: bool, assign: bool}|null $cached */
        $cached = $request->attributes->get('roles.abilities');

        if ($cached === null) {
            $model = (string) config('permission.models.role');
            $cached = [
                'update' => Gate::allows('update', $model),
                'delete' => Gate::allows('delete', $model),
                'assign' => Gate::allows('assign', $model),
            ];
            $request->attributes->set('roles.abilities', $cached);
        }

        return $cached;
    }
}
