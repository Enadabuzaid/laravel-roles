<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Requests;

/** The pages a saved view can belong to (`roles.users.saved_view_pages`). */
final class SavedViewPages
{
    /** @return list<string> */
    public static function all(): array
    {
        return array_values(array_filter(array_map('strval', (array) config('roles.users.saved_view_pages', [])), static fn (string $page): bool => $page !== ''));
    }
}
