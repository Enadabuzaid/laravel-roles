<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Context;

use Enadstack\LaravelRoles\Contracts\GuardResolverContract;

/**
 * ConfigGuardResolver
 *
 * Resolves the current guard from package configuration.
 * Ensures guard consistency across the package.
 *
 * @package Enadstack\LaravelRoles\Context
 */
class ConfigGuardResolver implements GuardResolverContract
{
    /**
     * Override guard (for testing or explicit context).
     *
     * @var string|null
     */
    protected ?string $overrideGuard = null;

    /**
     * {@inheritdoc}
     */
    public function guard(): string
    {
        // Return override if set
        if ($this->overrideGuard !== null) {
            return $this->overrideGuard;
        }

        // Check package config first
        $packageGuard = config('roles.guard');

        if ($packageGuard) {
            return $packageGuard;
        }

        // Fall back to Laravel's default guard
        return config('auth.defaults.guard', 'web');
    }

    /**
     * {@inheritdoc}
     */
    public function availableGuards(): array
    {
        return array_keys(config('auth.guards', []));
    }

    /**
     * {@inheritdoc}
     */
    public function isValidGuard(string $guard): bool
    {
        return in_array($guard, $this->availableGuards(), true);
    }

    /**
     * {@inheritdoc}
     */
    public function defaultGuard(): string
    {
        return config('roles.guard', config('auth.defaults.guard', 'web'));
    }

    /**
     * Set a temporary guard override.
     *
     * @param string $guard
     * @return static
     */
    public function setGuard(string $guard): static
    {
        if (!$this->isValidGuard($guard)) {
            throw new \InvalidArgumentException("Guard '{$guard}' is not configured in auth.guards.");
        }

        $this->overrideGuard = $guard;
        return $this;
    }

    /**
     * Clear the guard override.
     *
     * @return static
     */
    public function clearOverride(): static
    {
        $this->overrideGuard = null;
        return $this;
    }

    /**
     * Execute a callback with a specific guard context.
     *
     * @param string $guard
     * @param callable $callback
     * @return mixed
     */
    public function withGuard(string $guard, callable $callback): mixed
    {
        $previous = $this->overrideGuard;

        try {
            $this->setGuard($guard);
            return $callback();
        } finally {
            $this->overrideGuard = $previous;
        }
    }
}
