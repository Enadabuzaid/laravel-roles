<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Middleware;

use Closure;
use Enadstack\LaravelRoles\Users\Support\HostSoftDeletes;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The trash routes exist only when the host user model uses SoftDeletes: anything else answers 404. */
final class EnsureHostSoftDeletes
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(HostSoftDeletes::enabled(), 404);

        return $next($request);
    }
}
