<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Http\Middleware;

use Closure;
use Enadstack\LaravelRoles\Roles\Support\ActorRoles;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `access:any` - the user must be an owner or hold at least one `access.*` permission.
 */
final class RequireAccess
{
    public function handle(Request $request, Closure $next, string $requirement = 'any'): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        if (ActorRoles::for($request)['is_owner'] || $this->holdsAccessPermission($user)) {
            return $next($request);
        }

        throw new AuthorizationException('This action is unauthorized.');
    }

    private function holdsAccessPermission(object $user): bool
    {
        if (! method_exists($user, 'getAllPermissions')) {
            return false;
        }

        return $user->getAllPermissions()->pluck('name')->contains(static fn (mixed $name): bool => is_string($name) && str_starts_with($name, 'access.'));
    }
}
