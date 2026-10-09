<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Renders the MatrixQuery array (already shaped for the API) as the response data.
 */
final class MatrixResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $matrix */
        $matrix = (array) $this->resource;

        return [
            'modules' => $matrix['modules'] ?? [],
            'roles' => $matrix['roles'] ?? [],
            'summary' => $matrix['summary'] ?? [],
        ];
    }
}
