<?php

declare(strict_types=1);

namespace Tests\Traits;

use Enadstack\LaravelRoles\Contracts\GuardResolverContract;
use Enadstack\LaravelRoles\Context\ConfigGuardResolver;

/**
 * UsesWebGuard Trait
 *
 * Sets up web guard for tests.
 */
trait UsesWebGuard
{
    /**
     * Set up web guard.
     *
     * @return void
     */
    protected function setUpWebGuard(): void
    {
        config(['roles.guard' => 'web']);
        config(['auth.defaults.guard' => 'web']);

        // Rebind guard resolver
        $this->app->singleton(GuardResolverContract::class, function ($app) {
            return new ConfigGuardResolver();
        });
    }

    /**
     * Assert web guard is active.
     *
     * @return void
     */
    protected function assertWebGuardActive(): void
    {
        $resolver = app(GuardResolverContract::class);
        $this->assertEquals('web', $resolver->guard());
    }
}
