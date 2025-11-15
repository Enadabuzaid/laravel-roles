<?php

namespace Enadstack\LaravelRoles\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionMatrixResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'roles' => $this->resource['roles'] ?? [],
            'matrix' => $this->resource['matrix'] ?? [],
            'generated_at' => now()->toISOString(),
        ];
    }
}
