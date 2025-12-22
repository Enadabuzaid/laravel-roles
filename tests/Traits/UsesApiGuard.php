<?php

declare(strict_types=1);

namespace Tests\Traits;

use Enadstack\LaravelRoles\Contracts\GuardResolverContract;
use Enadstack\LaravelRoles\Context\ConfigGuardResolver;

/**
 * UsesApiGuard Trait
 *
 * Sets up API guard for tests.
 */
trait UsesApiGuard
{
    /**
     * Set up API guard.
     *
     * @return void
     */
    protected function setUpApiGuard(): void
    {
        config(['roles.guard' => 'api']);
        config(['auth.defaults.guard' => 'api']);

        // Rebind guard resolver
        $this->app->singleton(GuardResolverContract::class, function ($app) {
            return new ConfigGuardResolver();
        });
    }

    /**
     * Assert API guard is active.
     *
     * @return void
     */
    protected function assertApiGuardActive(): void
    {
        $resolver = app(GuardResolverContract::class);
        $this->assertEquals('api', $resolver->guard());
    }
}
