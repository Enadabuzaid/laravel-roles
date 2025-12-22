<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Context;

/**
 * SingleTenantContext
 *
 * Tenant context for single-tenant mode (no multi-tenancy).
 * All operations are global - no tenant scoping is applied.
 *
 * @package Enadstack\LaravelRoles\Context
 */
class SingleTenantContext extends AbstractTenantContext
{
    /**
     * {@inheritdoc}
     */
    public function mode(): string
    {
        return 'single';
    }

    /**
     * {@inheritdoc}
     */
    public function tenantId(): int|string|null
    {
        // Single tenant mode has no tenant ID
        return null;
    }

    /**
     * {@inheritdoc}
     *
     * In single-tenant mode, returns 'global' for all cache keys.
     */
    public function scopeKey(): string
    {
        return 'global';
    }

    /**
     * {@inheritdoc}
     *
     * No Spatie team context is set in single-tenant mode.
     */
    public function applyToSpatie(): void
    {
        // No-op for single tenant mode
        // Spatie's team feature is not used
    }
}
