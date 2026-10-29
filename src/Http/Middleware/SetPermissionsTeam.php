<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Http\Middleware;

use Closure;
use Enadstack\LaravelRoles\Support\TeamContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias `access.team`. Sets the Spatie team id for the request: the `roles.team_resolver`
 * callable (receives the Request) when configured, otherwise the authenticated user's team key attribute.
 * Apps with their own tenant middleware can call TeamContext::set($tenantId) instead.
 */
final class SetPermissionsTeam
{
    public function handle(Request $request, Closure $next): Response
    {
        if (TeamContext::enabled()) {
            $resolver = config('roles.team_resolver');
            $teamId = is_callable($resolver)
                ? $resolver($request)
                : $request->user()?->getAttribute(TeamContext::key());

            TeamContext::set(is_int($teamId) || is_string($teamId) ? $teamId : null);
        }

        return $next($request);
    }
}
