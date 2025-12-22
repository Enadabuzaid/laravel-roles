<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use Tests\Traits\UsesSingleTenancy;
use Tests\Traits\UsesTeamScopedTenancy;
use Tests\Traits\UsesMultiDatabaseTenancy;
use Enadstack\LaravelRoles\Context\ContextualCacheKeyBuilder;
use Enadstack\LaravelRoles\Contracts\CacheKeyBuilderContract;
use Enadstack\LaravelRoles\Contracts\TenantContextContract;
use Enadstack\LaravelRoles\Contracts\GuardResolverContract;
use Illuminate\Support\Facades\Cache;

/**
 * CacheKeyBuilder Unit Tests
 *
 * Tests cache key generation with context awareness.
 */
class CacheKeyBuilderTest extends TestCase
{
    use UsesSingleTenancy, UsesTeamScopedTenancy, UsesMultiDatabaseTenancy;

    /**
     * @test
     * @group unit
     * @group cache
     */
    public function generates_key_with_correct_prefix(): void
    {
        $builder = app(CacheKeyBuilderContract::class);
        $key = $builder->build('test_key');

        $this->assertStringStartsWith('laravel_roles:', $key);
    }

    /**
     * @test
     * @group unit
     * @group cache
     */
    public function different_tenant_ids_produce_different_keys(): void
    {
        $this->setUpTeamScopedTenancy(1);
        $builder1 = app(CacheKeyBuilderContract::class);
        $key1 = $builder1->build('permissions');

        $this->setUpTeamScopedTenancy(2);
        $builder2 = app(CacheKeyBuilderContract::class);
        $key2 = $builder2->build('permissions');

        $this->assertNotEquals($key1, $key2);
    }

    /**
     * @test
     * @group unit
     * @group cache
     */
    public function different_guards_produce_different_keys(): void
    {
        config(['roles.guard' => 'web']);
        $builder = app(CacheKeyBuilderContract::class);
        $key1 = $builder->build('matrix');

        config(['roles.guard' => 'api']);
        // Rebind to get new guard
        $this->app->forgetInstance(GuardResolverContract::class);
        $this->app->forgetInstance(CacheKeyBuilderContract::class);
        $builder2 = app(CacheKeyBuilderContract::class);
        $key2 = $builder2->build('matrix');

        $this->assertNotEquals($key1, $key2);
    }

    /**
     * @test
     * @group unit
     * @group cache
     */
    public function different_locales_produce_different_keys(): void
    {
        config(['roles.i18n.enabled' => true]);

        app()->setLocale('en');
        $builder = app(CacheKeyBuilderContract::class);
        $key1 = $builder->build('labels');

        app()->setLocale('ar');
        $key2 = $builder->build('labels');

        $this->assertNotEquals($key1, $key2);
    }

    /**
     * @test
     * @group unit
     * @group cache
     */
    public function single_tenant_tags_dont_include_tenant(): void
    {
        $this->setUpSingleTenancy();
        $builder = app(CacheKeyBuilderContract::class);
        $tags = $builder->tags();

        $this->assertContains('laravel_roles', $tags);
        $this->assertNotContains('tenant', $tags);
    }

    /**
     * @test
     * @group unit
     * @group cache
     */
    public function returns_correct_tags(): void
    {
        $this->setUpTeamScopedTenancy(5);
        $builder = app(CacheKeyBuilderContract::class);
        $tags = $builder->tags();

        $this->assertContains('laravel_roles', $tags);
    }

    /**
     * @test
     * @group unit
     * @group cache
     */
    public function returns_ttl_from_config(): void
    {
        config(['roles.cache.ttl' => 600]);
        $builder = app(CacheKeyBuilderContract::class);

        $this->assertEquals(600, $builder->ttl());
    }

    /**
     * @test
     * @group unit
     * @group cache
     */
    public function returns_enabled_status_from_config(): void
    {
        config(['roles.cache.enabled' => true]);
        $builder = app(CacheKeyBuilderContract::class);
        $this->assertTrue($builder->isEnabled());

        config(['roles.cache.enabled' => false]);
        $this->assertFalse($builder->isEnabled());
    }

    /**
     * @test
     * @group unit
     * @group cache
     */
    public function remember_returns_computed_value_when_cache_disabled(): void
    {
        config(['roles.cache.enabled' => false]);
        $builder = app(CacheKeyBuilderContract::class);

        $counter = 0;
        $callback = function () use (&$counter) {
            $counter++;
            return 'value';
        };

        $result1 = $builder->remember('test', $callback);
        $result2 = $builder->remember('test', $callback);

        $this->assertEquals('value', $result1);
        $this->assertEquals('value', $result2);
        // Called twice since caching is disabled
        $this->assertEquals(2, $counter);
    }

    /**
     * @test
     * @group unit
     * @group cache
     */
    public function remember_caches_value_when_enabled(): void
    {
        config(['roles.cache.enabled' => true]);
        Cache::flush();

        $builder = app(CacheKeyBuilderContract::class);

        $counter = 0;
        $callback = function () use (&$counter) {
            $counter++;
            return 'cached_value';
        };

        $result1 = $builder->remember('cache_test', $callback);
        $result2 = $builder->remember('cache_test', $callback);

        $this->assertEquals('cached_value', $result1);
        $this->assertEquals('cached_value', $result2);
        // Called only once due to caching
        $this->assertEquals(1, $counter);
    }

    /**
     * @test
     * @group unit
     * @group cache
     */
    public function forget_clears_cached_value(): void
    {
        config(['roles.cache.enabled' => true]);
        Cache::flush();

        $builder = app(CacheKeyBuilderContract::class);

        $counter = 0;
        $callback = function () use (&$counter) {
            $counter++;
            return 'value_' . $counter;
        };

        $result1 = $builder->remember('forget_test', $callback);
        $builder->forget('forget_test');
        $result2 = $builder->remember('forget_test', $callback);

        $this->assertEquals('value_1', $result1);
        $this->assertEquals('value_2', $result2);
    }

    /**
     * @test
     * @group unit
     * @group cache
     */
    public function keys_are_stable_with_same_context(): void
    {
        $this->setUpSingleTenancy();
        $builder = app(CacheKeyBuilderContract::class);

        $key1 = $builder->build('stability_test');
        $key2 = $builder->build('stability_test');

        $this->assertEquals($key1, $key2);
    }

    /**
     * @test
     * @group unit
     * @group cache
     */
    public function multi_database_tenant_produces_unique_keys(): void
    {
        $this->setUpMultiDatabaseTenancy('tenant_a');
        $builder1 = app(CacheKeyBuilderContract::class);
        $key1 = $builder1->build('data');

        $this->setUpMultiDatabaseTenancy('tenant_b');
        $builder2 = app(CacheKeyBuilderContract::class);
        $key2 = $builder2->build('data');

        $this->assertNotEquals($key1, $key2);
        $this->assertStringContainsString('tenant_a', $key1);
        $this->assertStringContainsString('tenant_b', $key2);
    }
}
