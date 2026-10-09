<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Support;

use Closure;
use Enadstack\LaravelRoles\Support\Exceptions\AccessException;
use Illuminate\Database\Eloquent\Model;

class ActorContext
{
    private ?Model $explicit = null;

    private bool $hasExplicit = false;

    public function actor(): ?Model
    {
        if ($this->hasExplicit) {
            return $this->explicit;
        }

        // The default guard is the one the route middleware selected (e.g. auth:sanctum); the role guard is the fallback.
        $user = auth()->user() ?? auth((string) config('roles.guard'))->user();

        return $user instanceof Model ? $user : null;
    }

    /**
     * Whether a missing actor means "the system": only in console or inside an explicit runAs(null).
     */
    public function runningInConsole(): bool
    {
        return app()->runningInConsole();
    }

    public function set(?Model $actor): void
    {
        $this->explicit = $actor;
        $this->hasExplicit = true;
    }

    public function clear(): void
    {
        $this->explicit = null;
        $this->hasExplicit = false;
    }

    public function isSystem(): bool
    {
        return $this->actor() === null && ($this->hasExplicit || $this->runningInConsole());
    }

    /**
     * Fail closed: a guarded write with no actor outside console and without an explicit runAs(null) is refused.
     */
    public function assertKnownActor(): void
    {
        if ($this->actor() === null && ! $this->isSystem()) {
            throw new AccessException('Authentication required.', 'guardrail.actor_required', 403);
        }
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function runAs(?Model $actor, Closure $callback): mixed
    {
        $previous = [$this->explicit, $this->hasExplicit];
        $this->set($actor);

        try {
            return $callback();
        } finally {
            [$this->explicit, $this->hasExplicit] = $previous;
        }
    }
}
