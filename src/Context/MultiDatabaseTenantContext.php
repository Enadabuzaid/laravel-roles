<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Context;

/**
 * MultiDatabaseTenantContext
 *
 * Tenant context for multi-database tenancy mode.
 * Designed to work with external packages like stancl/tenancy.
 * No hard dependency on any specific multi-tenancy package.
 *
 * @package Enadstack\LaravelRoles\Context
 */
class MultiDatabaseTenantContext extends AbstractTenantContext
{
    /**
     * Resolver callback for getting tenant ID from external provider.
     *
     * @var callable|null
     */
    protected $tenantIdResolver = null;

    /**
     * Resolver callback for getting tenant key from external provider.
     *
     * @var callable|null
     */
    protected $tenantKeyResolver = null;

    /**
     * {@inheritdoc}
     */
    public function mode(): string
    {
        return 'multi_database';
    }

    /**
     * {@inheritdoc}
     */
    public function tenantId(): int|string|null
    {
        // Return explicitly set tenant ID first
        if ($this->isExplicitlySet) {
            return $this->cachedTenantId;
        }

        // Use custom resolver if provided
        if ($this->tenantIdResolver !== null) {
            return ($this->tenantIdResolver)();
        }

        // Try common multi-tenancy package patterns
        return $this->resolveFromExternalProvider();
    }

    /**
     * {@inheritdoc}
     *
     * Returns a cache-safe scope key: 'db_{tenant_key}' or 'db_central'.
     */
    public function scopeKey(): string
    {
        $tenantKey = $this->getTenantKey();

        if ($tenantKey === null) {
            return 'db_central';
        }

        // Sanitize key for cache usage
        return 'db_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $tenantKey);
    }

    /**
     * {@inheritdoc}
     *
     * In multi-database mode, Spatie doesn't need team context
     * as each database is already tenant-isolated.
     */
    public function applyToSpatie(): void
    {
        // No-op for multi-database mode
        // Database isolation handles tenancy, not Spatie's team feature
    }

    /**
     * Set a custom resolver for tenant ID.
     *
     * @param callable $resolver
     * @return static
     */
    public function setTenantIdResolver(callable $resolver): static
    {
        $this->tenantIdResolver = $resolver;
        return $this;
    }

    /**
     * Set a custom resolver for tenant key (used in cache keys).
     *
     * @param callable $resolver
     * @return static
     */
    public function setTenantKeyResolver(callable $resolver): static
    {
        $this->tenantKeyResolver = $resolver;
        return $this;
    }

    /**
     * Get the tenant key for cache purposes.
     *
     * @return string|null
     */
    protected function getTenantKey(): ?string
    {
        // Use custom resolver if provided
        if ($this->tenantKeyResolver !== null) {
            return ($this->tenantKeyResolver)();
        }

        // Fall back to tenant ID
        $tenantId = $this->tenantId();

        return $tenantId !== null ? (string) $tenantId : null;
    }

    /**
     * Attempt to resolve tenant ID from common external providers.
     *
     * @return int|string|null
     */
    protected function resolveFromExternalProvider(): int|string|null
    {
        // Check configured provider
        $provider = config('roles.tenancy.provider');

        // stancl/tenancy
        if ($provider === 'stancl/tenancy' || class_exists('\Stancl\Tenancy\Tenancy')) {
            return $this->resolveFromStanclTenancy();
        }

        // spatie/laravel-multitenancy
        if ($provider === 'spatie/laravel-multitenancy' || class_exists('\Spatie\Multitenancy\Models\Tenant')) {
            return $this->resolveFromSpatieMultitenancy();
        }

        // tenancy/tenancy (hyn/multi-tenant successor)
        if ($provider === 'tenancy/tenancy' || class_exists('\Tenancy\Facades\Tenancy')) {
            return $this->resolveFromTenancyTenancy();
        }

        // Generic: check for 'tenant' binding
        if (app()->bound('tenant')) {
            $tenant = app('tenant');
            return $tenant->id ?? $tenant->getId() ?? null;
        }

        return null;
    }

    /**
     * Resolve from stancl/tenancy.
     *
     * @return int|string|null
     */
    protected function resolveFromStanclTenancy(): int|string|null
    {
        if (!function_exists('tenant')) {
            return null;
        }

        $tenant = tenant();

        if ($tenant === null) {
            return null;
        }

        return $tenant->getTenantKey() ?? $tenant->id ?? null;
    }

    /**
     * Resolve from spatie/laravel-multitenancy.
     *
     * @return int|string|null
     */
    protected function resolveFromSpatieMultitenancy(): int|string|null
    {
        if (!class_exists('\Spatie\Multitenancy\Models\Tenant')) {
            return null;
        }

        try {
            $tenant = \Spatie\Multitenancy\Models\Tenant::current();
            return $tenant?->id;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Resolve from tenancy/tenancy.
     *
     * @return int|string|null
     */
    protected function resolveFromTenancyTenancy(): int|string|null
    {
        if (!class_exists('\Tenancy\Facades\Tenancy')) {
            return null;
        }

        try {
            $tenant = \Tenancy\Facades\Tenancy::getTenant();
            return $tenant?->getTenantIdentifier() ?? $tenant?->id ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Check if we're in the central context (no tenant).
     *
     * @return bool
     */
    public function isCentral(): bool
    {
        return $this->tenantId() === null;
    }
}
