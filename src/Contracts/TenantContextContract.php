<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Contracts;

/**
 * TenantContextContract
 *
 * Responsible for all tenant-related logic in the package.
 * All tenancy decisions must be resolved through this abstraction.
 *
 * @package Enadstack\LaravelRoles\Contracts
 */
interface TenantContextContract
{
    /**
     * Get the current tenancy mode.
     *
     * @return string One of: 'single', 'team_scoped', 'multi_database'
     */
    public function mode(): string;

    /**
     * Get the current tenant identifier.
     *
     * @return int|string|null The tenant ID, or null if not applicable
     */
    public function tenantId(): int|string|null;

    /**
     * Get a scope key safe for use in cache keys.
     *
     * This key uniquely identifies the current tenant context
     * and is safe for use in cache key construction.
     *
     * @return string A cache-safe scope key (e.g., 'tenant_123', 'global', 'db_acme')
     */
    public function scopeKey(): string;

    /**
     * Apply the current tenant context to Spatie's permission system.
     *
     * In team_scoped mode, this sets the Spatie team context.
     * In other modes, this may be a no-op.
     *
     * @return void
     */
    public function applyToSpatie(): void;

    /**
     * Check if the current context is in single-tenant mode.
     *
     * @return bool
     */
    public function isSingleTenant(): bool;

    /**
     * Check if the current context is in team-scoped mode.
     *
     * @return bool
     */
    public function isTeamScoped(): bool;

    /**
     * Check if the current context is in multi-database mode.
     *
     * @return bool
     */
    public function isMultiDatabase(): bool;

    /**
     * Get the team foreign key name for Spatie teams.
     *
     * @return string
     */
    public function teamForeignKey(): string;

    /**
     * Set the tenant context temporarily.
     *
     * @param int|string|null $tenantId
     * @return void
     */
    public function setTenantId(int|string|null $tenantId): void;

    /**
     * Clear the current tenant context.
     *
     * @return void
     */
    public function clearContext(): void;
}
