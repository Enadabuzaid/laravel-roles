<?php

declare(strict_types=1);

namespace Tests\Traits;

use Enadstack\LaravelRoles\Contracts\TenantContextContract;
use Enadstack\LaravelRoles\Context\TeamScopedTenantContext;
use Spatie\Permission\PermissionRegistrar;

/**
 * UsesTeamScopedTenancy Trait
 *
 * Sets up team-scoped tenancy mode for tests.
 * Fakes team context without requiring actual Spatie teams setup.
 */
trait UsesTeamScopedTenancy
{
    /**
     * Current team ID for tests.
     *
     * @var int|string|null
     */
    protected $testTeamId = 1;

    /**
     * Set up team-scoped tenancy mode.
     *
     * @param int|string|null $teamId
     * @return void
     */
    protected function setUpTeamScopedTenancy($teamId = 1): void
    {
        $this->testTeamId = $teamId;

        config(['roles.tenancy.mode' => 'team_scoped']);
        config(['roles.tenancy.team_foreign_key' => 'team_id']);

        // Enable Spatie teams
        config(['permission.teams' => true]);
        config(['permission.team_foreign_key' => 'team_id']);

        // Set the team on Spatie's registrar
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);

        // Bind the team-scoped context
        $this->app->singleton(TenantContextContract::class, function ($app) {
            $context = new TeamScopedTenantContext();
            $context->setTenantId($this->testTeamId);
            return $context;
        });
    }

    /**
     * Switch to a different team.
     *
     * @param int|string $teamId
     * @return void
     */
    protected function switchToTeam($teamId): void
    {
        $this->testTeamId = $teamId;
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);

        // Set on context
        $context = app(TenantContextContract::class);
        $context->setTenantId($teamId);
    }

    /**
     * Assert team-scoped tenancy context is correct.
     *
     * @return void
     */
    protected function assertTeamScopedTenancyContext(): void
    {
        $context = app(TenantContextContract::class);

        $this->assertInstanceOf(TeamScopedTenantContext::class, $context);
        $this->assertFalse($context->isSingleTenant());
        $this->assertTrue($context->isTeamScoped());
        $this->assertFalse($context->isMultiDatabase());
        $this->assertEquals($this->testTeamId, $context->tenantId());
    }

    /**
     * Assert data is team-isolated.
     *
     * @param string $model Model class
     * @param int $teamId Team ID to check
     * @param int $expectedCount Expected count
     * @return void
     */
    protected function assertTeamIsolation(string $model, int $teamId, int $expectedCount): void
    {
        $this->switchToTeam($teamId);
        $this->assertEquals($expectedCount, $model::count());
    }
}
