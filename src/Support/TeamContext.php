<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Support;

use Closure;
use Spatie\Permission\PermissionRegistrar;

/**
 * Thin wrapper over Spatie's permissions team id so apps set the tenant in one place.
 */
final class TeamContext
{
    public static function enabled(): bool
    {
        return (bool) config('permission.teams');
    }

    public static function key(): string
    {
        return (string) config('permission.column_names.team_foreign_key', 'team_id');
    }

    public static function set(int|string|null $teamId): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
    }

    public static function get(): int|string|null
    {
        return app(PermissionRegistrar::class)->getPermissionsTeamId();
    }

    /**
     * Run a callback inside a team (null = global/platform), then restore the previous team.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function run(int|string|null $teamId, Closure $callback): mixed
    {
        $previous = self::get();
        self::set($teamId);

        try {
            return $callback();
        } finally {
            self::set($previous);
        }
    }
}
