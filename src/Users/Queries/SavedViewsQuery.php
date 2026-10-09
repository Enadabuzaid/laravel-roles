<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Queries;

use Enadstack\LaravelRoles\Users\Models\SavedView;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

final class SavedViewsQuery
{
    public const LIMIT_PER_PAGE = 25;

    /** @return Collection<int, SavedView> the user's own views ordered by page then name */
    public static function forUser(int|string $userId, ?string $page = null): Collection
    {
        return SavedView::query()
            ->where('user_id', $userId)
            ->when($page !== null, static fn ($query) => $query->where('page', $page))
            ->orderBy('page')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /** @throws ModelNotFoundException another user's view is simply not found */
    public static function own(int|string $userId, string|int $id): SavedView
    {
        return SavedView::query()->where('user_id', $userId)->findOrFail($id);
    }

    public static function countFor(int|string $userId, string $page): int
    {
        return SavedView::query()->where('user_id', $userId)->where('page', $page)->count();
    }
}
