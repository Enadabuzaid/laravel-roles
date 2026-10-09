<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;

final class ActorContext
{
    private ?Model $explicit = null;

    private bool $hasExplicit = false;

    public function actor(): ?Model
    {
        if ($this->hasExplicit) {
            return $this->explicit;
        }

        $user = auth((string) config('roles.guard'))->user();

        return $user instanceof Model ? $user : null;
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
        return $this->actor() === null;
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
