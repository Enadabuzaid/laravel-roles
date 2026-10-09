<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Doctor\Checks;

use Enadstack\LaravelRoles\Core\Doctor\Check;
use Enadstack\LaravelRoles\Core\Doctor\CheckResult;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;

final class RoutesProtectedCheck implements Check
{
    private const PATTERN = '/^(can|permission|role|role_or_permission|access):/';

    public function name(): string
    {
        return 'routes-protected';
    }

    public function run(): CheckResult
    {
        $prefix = trim((string) config('roles.routes.prefix'), '/');
        if ($prefix === '') {
            return CheckResult::pass('No package routes registered.');
        }

        $routes = array_filter(
            Router::getRoutes()->getRoutes(),
            fn (Route $route): bool => $route->uri() === $prefix || str_starts_with($route->uri(), $prefix.'/'),
        );

        if ($routes === []) {
            return CheckResult::pass('No package routes registered.');
        }

        $open = [];
        foreach ($routes as $route) {
            $guarded = array_filter(
                $route->gatherMiddleware(),
                fn (mixed $middleware): bool => is_string($middleware) && preg_match(self::PATTERN, $middleware) === 1,
            );
            if ($guarded === []) {
                $open[] = $route->uri();
            }
        }

        return $open === []
            ? CheckResult::pass(count($routes).' package route(s) protected.')
            : CheckResult::fail('Routes without permission middleware: '.implode(', ', array_unique($open)));
    }
}
