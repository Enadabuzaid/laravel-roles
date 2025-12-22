<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use Tests\Traits\UsesWebGuard;
use Tests\Traits\UsesApiGuard;
use Enadstack\LaravelRoles\Context\ConfigGuardResolver;
use Enadstack\LaravelRoles\Contracts\GuardResolverContract;
use InvalidArgumentException;

/**
 * GuardResolver Unit Tests
 *
 * Tests guard resolution from config.
 */
class GuardResolverTest extends TestCase
{
    use UsesWebGuard, UsesApiGuard;

    /**
     * @test
     * @group unit
     * @group guard
     */
    public function resolves_correct_guard_from_config(): void
    {
        config(['roles.guard' => 'web']);
        $resolver = new ConfigGuardResolver();

        $this->assertEquals('web', $resolver->guard());
    }

    /**
     * @test
     * @group unit
     * @group guard
     */
    public function falls_back_to_laravel_default_guard(): void
    {
        config(['roles.guard' => null]);
        config(['auth.defaults.guard' => 'api']);

        $resolver = new ConfigGuardResolver();
        $this->assertEquals('api', $resolver->guard());
    }

    /**
     * @test
     * @group unit
     * @group guard
     */
    public function returns_available_guards(): void
    {
        config(['auth.guards' => [
            'web' => ['driver' => 'session'],
            'api' => ['driver' => 'token'],
            'custom' => ['driver' => 'session'],
        ]]);

        $resolver = new ConfigGuardResolver();
        $guards = $resolver->availableGuards();

        $this->assertContains('web', $guards);
        $this->assertContains('api', $guards);
        $this->assertContains('custom', $guards);
    }

    /**
     * @test
     * @group unit
     * @group guard
     */
    public function validates_guard_exists(): void
    {
        config(['auth.guards' => [
            'web' => ['driver' => 'session'],
            'api' => ['driver' => 'token'],
        ]]);

        $resolver = new ConfigGuardResolver();

        $this->assertTrue($resolver->isValid('web'));
        $this->assertTrue($resolver->isValid('api'));
        $this->assertFalse($resolver->isValid('nonexistent'));
    }

    /**
     * @test
     * @group unit
     * @group guard
     */
    public function returns_default_guard(): void
    {
        config(['auth.defaults.guard' => 'api']);

        $resolver = new ConfigGuardResolver();
        $this->assertEquals('api', $resolver->defaultGuard());
    }

    /**
     * @test
     * @group unit
     * @group guard
     */
    public function can_set_override_guard(): void
    {
        config(['roles.guard' => 'web']);

        $resolver = new ConfigGuardResolver();
        $this->assertEquals('web', $resolver->guard());

        $resolver->setGuard('api');
        $this->assertEquals('api', $resolver->guard());
    }

    /**
     * @test
     * @group unit
     * @group guard
     */
    public function can_clear_override(): void
    {
        config(['roles.guard' => 'web']);

        $resolver = new ConfigGuardResolver();
        $resolver->setGuard('api');
        $this->assertEquals('api', $resolver->guard());

        $resolver->clearOverride();
        $this->assertEquals('web', $resolver->guard());
    }

    /**
     * @test
     * @group unit
     * @group guard
     */
    public function throws_exception_for_invalid_guard_override(): void
    {
        config(['auth.guards' => [
            'web' => ['driver' => 'session'],
        ]]);

        $resolver = new ConfigGuardResolver();

        $this->expectException(InvalidArgumentException::class);
        $resolver->setGuard('invalid_guard');
    }

    /**
     * @test
     * @group unit
     * @group guard
     */
    public function with_guard_executes_callback_in_context(): void
    {
        config(['roles.guard' => 'web']);

        $resolver = new ConfigGuardResolver();

        $result = $resolver->withGuard('api', function () use ($resolver) {
            return $resolver->guard();
        });

        $this->assertEquals('api', $result);
        $this->assertEquals('web', $resolver->guard());
    }

    /**
     * @test
     * @group unit
     * @group guard
     */
    public function does_not_break_with_multiple_guards(): void
    {
        config(['auth.guards' => [
            'web' => ['driver' => 'session'],
            'api' => ['driver' => 'token'],
            'admin' => ['driver' => 'session'],
            'sanctum' => ['driver' => 'sanctum'],
        ]]);
        config(['roles.guard' => 'web']);

        $resolver = new ConfigGuardResolver();

        $this->assertEquals('web', $resolver->guard());
        $this->assertCount(4, $resolver->availableGuards());
    }

    /**
     * @test
     * @group unit
     * @group guard
     */
    public function web_guard_trait_sets_up_correctly(): void
    {
        $this->setUpWebGuard();
        $this->assertWebGuardActive();
    }

    /**
     * @test
     * @group unit
     * @group guard
     */
    public function api_guard_trait_sets_up_correctly(): void
    {
        $this->setUpApiGuard();
        $this->assertApiGuardActive();
    }
}
