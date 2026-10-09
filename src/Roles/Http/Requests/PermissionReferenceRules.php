<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Http\Requests;

use Closure;

/**
 * Shared validation of permission id/name lists: bounded size, ids or short names only.
 */
final class PermissionReferenceRules
{
    public const MAX_PER_LIST = 500;

    public const MAX_ROLES = 200;

    public const MAX_TOTAL_REFERENCES = 5000;

    /** @return list<mixed> */
    public static function list(): array
    {
        return [
            'sometimes',
            'array',
            'max:'.self::MAX_PER_LIST,
            static function (string $attribute, mixed $value, Closure $fail): void {
                foreach ((array) $value as $reference) {
                    $valid = is_int($reference) ? $reference > 0 : (is_string($reference) && $reference !== '' && strlen($reference) <= 191);

                    if (! $valid) {
                        $fail("The {$attribute} entries must be permission ids or names.");

                        return;
                    }
                }
            },
        ];
    }

    /**
     * @return list<int|string>
     */
    public static function normalize(mixed $value): array
    {
        /** @var list<int|string> $list */
        $list = array_values((array) $value);

        return $list;
    }
}
