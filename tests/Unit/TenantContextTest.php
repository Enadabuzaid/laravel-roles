<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use Tests\Traits\UsesSingleTenancy;
use Tests\Traits\UsesTeamScopedTenancy;
use Tests\Traits\UsesMultiDatabaseTenancy;
use Enadstack\LaravelRoles\Context\SingleTenantContext;
use Enadstack\LaravelRoles\Context\TeamScopedTenantContext;
use Enadstack\LaravelRoles\Context\MultiDatabaseTenantContext;
use Enadstack\LaravelRoles\Contracts\TenantContextContract;

/**
 * TenantContext Unit Tests
 *
 * Tests each tenant context implementation independently.
 */
class TenantContextTest extends TestCase
{
    use UsesSingleTenancy, UsesTeamScopedTenancy, UsesMultiDatabaseTenancy;

    /**
     * @test
     * @group unit
     * @group tenancy
     */
    public function single_tenant_context_returns_null_tenant_id(): void
    {
        $this->setUpSingleTenancy();
        $context = app(TenantContextContract::class);

        $this->assertNull($context->tenantId());
    }

    /**
     * @test
     * @group unit
     * @group tenancy
     */
    public function single_tenant_context_returns_single_as_scope_key(): void
    {
        $this->setUpSingleTenancy();
        $context = app(TenantContextContract::class);

        $this->assertEquals('single', $context->scopeKey());
    }

    /**
     * @test
     * @group unit
     * @group tenancy
     */
    public function single_tenant_is_not_multi_tenant(): void
    {
        $this->setUpSingleTenancy();
        $this->assertSingleTenancyContext();
    }

    /**
     * @test
     * @group unit
     * @group tenancy
     */
    public function team_scoped_context_resolves_team_id(): void
    {
        $this->setUpTeamScopedTenancy(42);
        $context = app(TenantContextContract::class);

        $this->assertEquals(42, $context->tenantId());
    }

    /**
     * @test
     * @group unit
     * @group tenancy
     */
    public function team_scoped_context_applies_spatie_team_context(): void
    {
        $this->setUpTeamScopedTenancy(5);

        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $this->assertEquals(5, $registrar->getPermissionsTeamId());
    }

    /**
     * @test
     * @group unit
     * @group tenancy
     */
    public function team_scoped_scope_key_contains_team_id(): void
    {
        $this->setUpTeamScopedTenancy(123);
        $context = app(TenantContextContract::class);

        $this->assertStringContainsString('123', $context->scopeKey());
        $this->assertStringContainsString('team_scoped', $context->scopeKey());
    }

    /**
     * @test
     * @group unit
     * @group tenancy
     */
    public function team_scoped_context_is_multi_tenant(): void
    {
        $this->setUpTeamScopedTenancy();
        $this->assertTeamScopedTenancyContext();
    }

    /**
     * @test
     * @group unit
     * @group tenancy
     */
    public function team_scoped_can_switch_teams(): void
    {
        $this->setUpTeamScopedTenancy(1);
        $context = app(TenantContextContract::class);

        $this->assertEquals(1, $context->tenantId());

        $this->switchToTeam(2);
        // Re-resolve context after switch
        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $this->assertEquals(2, $registrar->getPermissionsTeamId());
    }

    /**
     * @test
     * @group unit
     * @group tenancy
     */
    public function multi_database_context_resolves_tenant_id_from_adapter(): void
    {
        $this->setUpMultiDatabaseTenancy('tenant_abc');
        $context = app(TenantContextContract::class);

        $this->assertEquals('tenant_abc', $context->tenantId());
    }

    /**
     * @test
     * @group unit
     * @group tenancy
     */
    public function multi_database_context_does_not_touch_spatie_teams(): void
    {
        $this->setUpMultiDatabaseTenancy('tenant_xyz');

        // Spatie teams should not be affected
        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        // By default it should be null or previous value
        $this->assertNotEquals('tenant_xyz', $registrar->getPermissionsTeamId());
    }

    /**
     * @test
     * @group unit
     * @group tenancy
     */
    public function multi_database_scope_key_is_stable(): void
    {
        $this->setUpMultiDatabaseTenancy('tenant_stable');
        $context = app(TenantContextContract::class);

        $key1 = $context->scopeKey();
        $key2 = $context->scopeKey();

        $this->assertEquals($key1, $key2);
        $this->assertStringContainsString('tenant_stable', $key1);
    }

    /**
     * @test
     * @group unit
     * @group tenancy
     */
    public function multi_database_context_is_multi_tenant(): void
    {
        $this->setUpMultiDatabaseTenancy();
        $this->assertMultiDatabaseTenancyContext();
    }

    /**
     * @test
     * @group unit
     * @group tenancy
     */
    public function multi_database_can_switch_tenants(): void
    {
        $this->setUpMultiDatabaseTenancy('tenant_1');
        $context = app(TenantContextContract::class);

        $this->assertEquals('tenant_1', $context->tenantId());

        $this->switchToTenant('tenant_2');
        $newContext = app(TenantContextContract::class);
        $this->assertEquals('tenant_2', $newContext->tenantId());
    }

    /**
     * @test
     * @group unit
     * @group tenancy
     */
    public function run_as_tenant_executes_callback_in_tenant_context(): void
    {
        $this->setUpMultiDatabaseTenancy('original_tenant');

        $result = $this->runAsTenant('temporary_tenant', function () {
            return $this->testTenantId;
        });

        $this->assertEquals('temporary_tenant', $result);
        $this->assertEquals('original_tenant', $this->testTenantId);
    }

    /**
     * @test
     * @group unit
     * @group tenancy
     */
    public function different_tenancy_modes_produce_different_scope_keys(): void
    {
        $this->setUpSingleTenancy();
        $singleKey = app(TenantContextContract::class)->scopeKey();

        $this->setUpTeamScopedTenancy(1);
        $teamKey = app(TenantContextContract::class)->scopeKey();

        $this->setUpMultiDatabaseTenancy('tenant_1');
        $multiDbKey = app(TenantContextContract::class)->scopeKey();

        $this->assertNotEquals($singleKey, $teamKey);
        $this->assertNotEquals($teamKey, $multiDbKey);
        $this->assertNotEquals($singleKey, $multiDbKey);
    }
}
