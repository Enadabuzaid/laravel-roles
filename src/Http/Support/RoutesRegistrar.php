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
        $rateLimit = config('roles.http.rate_limit');

        if (is_string($rateLimit) && $rateLimit !== '') {
            $middleware[] = 'throttle:'.$rateLimit;
        }

        Route::prefix((string) config('roles.routes.prefix'))
            ->middleware($middleware)
            ->name('access.')
            ->group($routesFile);
    }
}
