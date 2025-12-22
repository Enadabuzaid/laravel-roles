<?php

declare(strict_types=1);

namespace Tests\Traits;

use Enadstack\LaravelRoles\Contracts\TenantContextContract;
use Enadstack\LaravelRoles\Context\MultiDatabaseTenantContext;

/**
 * UsesMultiDatabaseTenancy Trait
 *
 * Sets up multi-database tenancy mode for tests.
 * Fakes external tenant provider without requiring actual package.
 */
trait UsesMultiDatabaseTenancy
{
    /**
     * Current tenant ID for tests.
     *
     * @var int|string|null
     */
    protected $testTenantId = 'tenant_1';

    /**
     * Set up multi-database tenancy mode.
     *
     * @param string|int $tenantId
     * @return void
     */
    protected function setUpMultiDatabaseTenancy($tenantId = 'tenant_1'): void
    {
        $this->testTenantId = $tenantId;

        config(['roles.tenancy.mode' => 'multi_database']);
        config(['roles.tenancy.provider' => 'mock']);

        // Bind a mocked multi-database context using the resolver pattern
        $this->app->singleton(TenantContextContract::class, function ($app) {
            $context = new MultiDatabaseTenantContext();
            $context->setTenantIdResolver(fn() => $this->testTenantId);
            return $context;
        });
    }

    /**
     * Switch to a different tenant.
     *
     * @param string|int $tenantId
     * @return void
     */
    protected function switchToTenant($tenantId): void
    {
        $this->testTenantId = $tenantId;

        // Rebind the context with new tenant
        $this->app->forgetInstance(TenantContextContract::class);
        $this->app->singleton(TenantContextContract::class, function ($app) {
            $context = new MultiDatabaseTenantContext();
            $context->setTenantIdResolver(fn() => $this->testTenantId);
            return $context;
        });
    }

    /**
     * Assert multi-database tenancy context is correct.
     *
     * @return void
     */
    protected function assertMultiDatabaseTenancyContext(): void
    {
        $context = app(TenantContextContract::class);

        $this->assertInstanceOf(MultiDatabaseTenantContext::class, $context);
        $this->assertTrue($context->isMultiDatabase());
        $this->assertFalse($context->isTeamScoped());
        $this->assertEquals($this->testTenantId, $context->tenantId());
    }

    /**
     * Run callback as specific tenant.
     *
     * @param string|int $tenantId
     * @param callable $callback
     * @return mixed
     */
    protected function runAsTenant($tenantId, callable $callback)
    {
        $previousTenant = $this->testTenantId;
        $this->switchToTenant($tenantId);

        try {
            return $callback();
        } finally {
            $this->switchToTenant($previousTenant);
        }
    }
}
