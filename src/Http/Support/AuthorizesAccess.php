<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Http\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Second authorization layer for the read routes: the route middleware is the first, this keeps a
 * host that re-registers the package routes without `can:` middleware from exposing the data.
 */
trait AuthorizesAccess
{
    /** @param  'role'|'permission'  $model */
    protected function authorizeAbility(Request $request, string $ability, string $model): void
    {
        Gate::forUser($request->user())->authorize($ability, (string) config("permission.models.{$model}"));
    }

    protected function authorizePermission(Request $request, string $permission): void
    {
        Gate::forUser($request->user())->authorize($permission);
    }
}
