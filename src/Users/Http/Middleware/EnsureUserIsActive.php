<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Middleware;

use Closure;
use Enadstack\LaravelRoles\Support\ApiResponse;
use Enadstack\LaravelRoles\Users\Support\SignInGate;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * `access.active` - ends the session of a signed-in user whose access state no longer allows signing in
 * (suspended, deactivated, locked...). Add it to the host's web group (or any route group) after the session middleware.
 * Web requests are redirected to the login route when one exists, JSON requests get 403 `account_inactive`.
 */
final class EnsureUserIsActive
{
    private const ATTRIBUTE = 'access.user_blocked';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $this->isBlocked($request, $user->getAuthIdentifier())) {
            return $next($request);
        }

        $this->endSession($request);
        $message = (string) trans('access::auth.inactive');

        if ($request->expectsJson()) {
            return ApiResponse::error($message, 'account_inactive', 403);
        }

        if (Route::has('login')) {
            return redirect()->route('login')->withErrors([(string) config('fortify.username', 'email') => $message]);
        }

        abort(403, $message);
    }

    private function isBlocked(Request $request, mixed $identifier): bool
    {
        $cached = $request->attributes->get(self::ATTRIBUTE);

        if (is_bool($cached)) {
            return $cached;
        }

        $blocked = app(SignInGate::class)->blocksIdentifier($identifier);
        $request->attributes->set(self::ATTRIBUTE, $blocked);

        return $blocked;
    }

    private function endSession(Request $request): void
    {
        foreach (array_unique([Auth::getDefaultDriver(), (string) config('roles.guard')]) as $name) {
            $this->logout(Auth::guard($name));
        }

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }

    /** Token guards (e.g. Sanctum's request guard) have no session to end; they are not stateful. */
    private function logout(object $guard): void
    {
        if ($guard instanceof StatefulGuard) {
            $guard->logout();
        }
    }
}
