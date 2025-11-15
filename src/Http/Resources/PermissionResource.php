<?php

namespace Enadstack\LaravelRoles\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'guard_name' => $this->guard_name,
            'group' => $this->when(isset($this->group), $this->group),
            'label' => $this->when(isset($this->label), $this->label),
            'description' => $this->when(isset($this->description), $this->description),
            'group_label' => $this->when(isset($this->group_label), $this->group_label),
            'roles_count' => $this->whenCounted('roles'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->when($this->deleted_at, $this->deleted_at?->toISOString()),
        ];
    }
}

