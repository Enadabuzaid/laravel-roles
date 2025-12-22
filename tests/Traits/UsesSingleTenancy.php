<?php

declare(strict_types=1);

namespace Tests\Traits;

use Enadstack\LaravelRoles\Contracts\TenantContextContract;
use Enadstack\LaravelRoles\Context\SingleTenantContext;

/**
 * UsesSingleTenancy Trait
 *
 * Sets up single tenancy mode for tests.
 * Use this when testing non-multi-tenant scenarios.
 */
trait UsesSingleTenancy
{
    /**
     * Set up single tenancy mode.
     *
     * @return void
     */
    protected function setUpSingleTenancy(): void
    {
        config(['roles.tenancy.mode' => 'single']);

        // Bind the single tenant context
        $this->app->singleton(TenantContextContract::class, function ($app) {
            return new SingleTenantContext();
        });
    }

    /**
     * Assert single tenancy context is correct.
     *
     * @return void
     */
    protected function assertSingleTenancyContext(): void
    {
        $context = app(TenantContextContract::class);

        $this->assertInstanceOf(SingleTenantContext::class, $context);
        $this->assertTrue($context->isSingleTenant());
        $this->assertFalse($context->isTeamScoped());
        $this->assertFalse($context->isMultiDatabase());
        $this->assertNull($context->tenantId());
        $this->assertEquals('single', $context->scopeKey());
    }
}
