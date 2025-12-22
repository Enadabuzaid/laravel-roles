<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Contracts;

/**
 * GuardResolverContract
 *
 * Responsible for resolving the current guard context.
 * Ensures guard consistency across the package.
 *
 * @package Enadstack\LaravelRoles\Contracts
 */
interface GuardResolverContract
{
    /**
     * Get the current guard name.
     *
     * @return string The guard name (e.g., 'web', 'api', 'admin')
     */
    public function guard(): string;

    /**
     * Get all configured guards.
     *
     * @return array<string> List of available guard names
     */
    public function availableGuards(): array;

    /**
     * Check if a guard is valid.
     *
     * @param string $guard
     * @return bool
     */
    public function isValidGuard(string $guard): bool;

    /**
     * Get the default guard from config.
     *
     * @return string
     */
    public function defaultGuard(): string;
}
