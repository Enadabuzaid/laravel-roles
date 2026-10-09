<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Http\Middleware;

use Closure;
use Enadstack\LaravelRoles\Roles\Support\ActorRoles;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * `access:any` - the user must be an owner or hold at least one `access.*` permission.
 * `access:public` - a pass-through marker for the single public route (invitation acceptance): it requires no
 * user and checks nothing; the doctor allows it for that route only.
 */
final class RequireAccess
{
    public function handle(Request $request, Closure $next, string $requirement = 'any'): Response
    {
        if ($requirement === 'public') {
            return $next($request);
        }

        if ($requirement !== 'any') {
            throw new InvalidArgumentException("Unsupported access middleware argument [{$requirement}]; only [access:any] and [access:public] are supported.");
        }

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

        $guard = (string) config('roles.guard');

        return $user->getAllPermissions()->where('guard_name', $guard)->whereNull('deprecated_at')->pluck('name')->contains(static fn (mixed $name): bool => is_string($name) && str_starts_with($name, 'access.'));
    }
}
