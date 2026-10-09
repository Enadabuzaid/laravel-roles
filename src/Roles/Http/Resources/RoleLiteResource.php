<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Http\Resources;

use Enadstack\LaravelRoles\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class RoleLiteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $role = $this->resource;

        if (! $role instanceof Role) {
            throw new \LogicException('RoleLiteResource wraps a Role.');
        }

        return [
            'id' => $role->getKey(),
            'name' => $role->name,
            'label' => $role->getAttribute('label'),
            'label_text' => $role->label_text,
            'color' => $role->getAttribute('color'),
            'is_system' => $role->is_system,
        ];
    }
}
