<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Resources;

use Enadstack\LaravelRoles\Users\Models\SavedView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SavedViewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $view = $this->resource;

        if (! $view instanceof SavedView) {
            throw new \LogicException('SavedViewResource wraps a SavedView.');
        }

        return [
            'id' => $view->getKey(),
            'page' => $view->page,
            'name' => $view->name,
            'filters' => $view->filters === [] ? (object) [] : $view->filters,
            'created_at' => $view->created_at,
        ];
    }
}
