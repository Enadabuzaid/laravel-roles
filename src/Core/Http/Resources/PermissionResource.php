<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Http\Resources;

use Enadstack\LaravelRoles\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PermissionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $permission = $this->resource;

        if (! $permission instanceof Permission) {
            throw new \LogicException('PermissionResource wraps a Permission.');
        }

        return [
            'id' => $permission->getKey(),
            'name' => $permission->name,
            'module' => $permission->module,
            'label' => $permission->label,
            'label_text' => $permission->label_text,
            'description' => $permission->description,
            'description_text' => $permission->translated('description'),
            'risk' => $permission->risk,
            'source' => $permission->source,
            'deprecated_at' => $permission->deprecated_at,
            'is_deprecated' => $permission->deprecated_at !== null,
            'last_synced_at' => $permission->last_synced_at,
            'sort_order' => $permission->sort_order,
            'roles_count' => $this->when($permission->getAttribute('roles_count') !== null, fn (): int => (int) $permission->getAttribute('roles_count')),
            'created_at' => $permission->created_at,
            'updated_at' => $permission->updated_at,
        ];
    }
}
