<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Http\Support;

use Illuminate\Support\Facades\Route;

final class RoutesRegistrar
{
    public static function register(string $module, string $routesFile): void
    {
        if (! config("roles.modules.{$module}")) {
            return;
        }

        $middleware = array_values((array) config('roles.routes.middleware', []));

        if (config('roles.routes.enforce_active', true)) {
            $middleware[] = 'access.active';
        }

        if (self::rateLimit() !== null) {
            $middleware[] = 'throttle:access';
        }

        Route::prefix((string) config('roles.routes.prefix'))
            ->middleware($middleware)
            ->name('access.')
            ->group($routesFile);
    }

    /**
     * `roles.http.rate_limit` as requests per minute (int), or the legacy `attempts,minutes` string; null disables the limit.
     *
     * @return array{attempts: int, minutes: int}|null
     */
    public static function rateLimit(): ?array
    {
        $value = config('roles.http.rate_limit');

        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value > 0 ? ['attempts' => (int) $value, 'minutes' => 1] : null;
        }

        if (is_string($value) && preg_match('/^\s*(\d+)\s*,\s*(\d+)\s*$/', $value, $match) === 1 && (int) $match[1] > 0 && (int) $match[2] > 0) {
            return ['attempts' => (int) $match[1], 'minutes' => (int) $match[2]];
        }

        return null;
    }
}
