<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Actions;

use Enadstack\LaravelRoles\Support\Exceptions\AccessException;
use Enadstack\LaravelRoles\Users\Models\SavedView;
use Enadstack\LaravelRoles\Users\Queries\SavedViewsQuery;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves a personal filter preset. Views are a per-user preference, so they are deliberately not audited.
 */
final class CreateSavedView
{
    /** @param  array<string, mixed>  $filters */
    public function handle(int|string $userId, string $page, string $name, array $filters): SavedView
    {
        try {
            return DB::transaction(function () use ($userId, $page, $name, $filters): SavedView {
                SavedView::query()->where('user_id', $userId)->where('page', $page)->lockForUpdate()->pluck('id');

                if (SavedViewsQuery::countFor($userId, $page) >= SavedViewsQuery::LIMIT_PER_PAGE) {
                    throw new AccessException(
                        'You can save up to '.SavedViewsQuery::LIMIT_PER_PAGE.' views per page. Delete one first.',
                        'saved_view.limit',
                        422,
                    );
                }

                return SavedView::query()->create(['user_id' => $userId, 'page' => $page, 'name' => $name, 'filters' => $filters]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => ['You already have a saved view with this name.']]);
        }
    }
}
