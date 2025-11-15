<?php

namespace Enadstack\LaravelRoles\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to set the current tenant/team ID for Spatie Permission's team-scoped mode.
 *
 * Usage:
 * 1. Add to your app's Http/Kernel.php or bootstrap/app.php middleware list
 * 2. Apply to routes that need tenancy scoping
 *
 * Example in route middleware:
 * Route::middleware(['auth:sanctum', SetPermissionTeamId::class])->group(...)
 */
class SetPermissionTeamId
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply if tenancy mode is team_scoped
        if (config('roles.tenancy.mode') !== 'team_scoped') {
            return $next($request);
        }

        // Get tenant ID from authenticated user (assumes user has a team_id or tenant_id property)
        $user = $request->user();

        if ($user) {
            // Attempt to get team ID from various common property names
            $teamId = $user->team_id
                ?? $user->tenant_id
                ?? $user->provider_id
                ?? null;

            // Or from request header (useful for API clients that explicitly pass tenant context)
            if (!$teamId && $request->hasHeader('X-Tenant-Id')) {
                $teamId = $request->header('X-Tenant-Id');
            }

            // Or from request query parameter
            if (!$teamId && $request->has('tenant_id')) {
                $teamId = $request->input('tenant_id');
            }

            // Set the tenant context for Spatie Permission
            if ($teamId) {
                app()->instance('permission.team_id', $teamId);
            }
        }

        return $next($request);
    }
}

