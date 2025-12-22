<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Context;

use Enadstack\LaravelRoles\Contracts\TenantContextContract;

/**
 * AbstractTenantContext
 *
 * Base class for tenant context implementations.
 * Provides shared functionality for all tenancy modes.
 *
 * @package Enadstack\LaravelRoles\Context
 */
abstract class AbstractTenantContext implements TenantContextContract
{
    /**
     * Cached tenant ID.
     *
     * @var int|string|null
     */
    protected int|string|null $cachedTenantId = null;

    /**
     * Whether tenant ID has been explicitly set.
     *
     * @var bool
     */
    protected bool $isExplicitlySet = false;

    /**
     * {@inheritdoc}
     */
    public function isSingleTenant(): bool
    {
        return $this->mode() === 'single';
    }

    /**
     * {@inheritdoc}
     */
    public function isTeamScoped(): bool
    {
        return $this->mode() === 'team_scoped';
    }

    /**
     * {@inheritdoc}
     */
    public function isMultiDatabase(): bool
    {
        return $this->mode() === 'multi_database';
    }

    /**
     * {@inheritdoc}
     */
    public function teamForeignKey(): string
    {
        return config('roles.tenancy.team_foreign_key', config('permission.team_foreign_key', 'team_id'));
    }

    /**
     * {@inheritdoc}
     */
    public function setTenantId(int|string|null $tenantId): void
    {
        $this->cachedTenantId = $tenantId;
        $this->isExplicitlySet = true;
        $this->applyToSpatie();
    }

    /**
     * {@inheritdoc}
     */
    public function clearContext(): void
    {
        $this->cachedTenantId = null;
        $this->isExplicitlySet = false;

        if (app()->bound('permission.team_id')) {
            app()->forgetInstance('permission.team_id');
        }
    }

    /**
     * Get the configuration value for tenancy mode.
     *
     * @return string
     */
    protected function getConfigMode(): string
    {
        return config('roles.tenancy.mode', 'single');
    }
}
