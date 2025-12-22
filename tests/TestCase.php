<?php

declare(strict_types=1);

namespace Tests;

use Enadstack\LaravelRoles\Providers\RolesServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Permission\PermissionServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Traits\HasRoles;

/**
 * TestUser Model
 *
 * A lightweight user model for testing that supports Spatie roles.
 */
class TestUser extends Authenticatable
{
    use HasFactory, HasRoles;

    protected $table = 'users';
    protected $guarded = [];
}

/**
 * Base TestCase
 *
 * Extended Orchestra Testbench test case for Laravel Roles package.
 */
class TestCase extends Orchestra
{
    /**
     * Query counter for N+1 detection.
     */
    protected int $queryCount = 0;

    /**
     * Query log for debugging.
     */
    protected array $queryLog = [];

    /**
     * Get package providers.
     */
    protected function getPackageProviders($app): array
    {
        return [
            PermissionServiceProvider::class,
            RolesServiceProvider::class,
        ];
    }

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Clear caches between tests
        $this->clearCaches();

        // Allow all policy checks in tests
        Gate::before(function () {
            return true;
        });

        // Create and authenticate a test user
        $this->authenticateTestUser();
    }

    /**
     * Tear down the test environment.
     */
    protected function tearDown(): void
    {
        $this->clearCaches();
        parent::tearDown();
    }

    /**
     * Define environment setup.
     */
    protected function defineEnvironment($app): void
    {
        // Base app key
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));

        // Use sqlite in-memory for speed
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Default guard web
        $app['config']->set('auth.defaults.guard', 'web');
        $app['config']->set('auth.guards.web', [
            'driver' => 'session',
            'provider' => 'users',
        ]);
        $app['config']->set('auth.guards.api', [
            'driver' => 'token',
            'provider' => 'users',
        ]);
        $app['config']->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => TestUser::class,
        ]);

        // Cache: array store
        $app['config']->set('cache.default', 'array');
        $app['config']->set('cache.stores.array', ['driver' => 'array']);

        // Package route middleware relaxed for tests
        $app['config']->set('roles.routes.middleware', ['api']);
        $app['config']->set('roles.routes.prefix', 'admin/acl');

        // Default package config for tests
        $app['config']->set('roles.i18n.enabled', false);
        $app['config']->set('roles.tenancy.mode', 'single');
        $app['config']->set('roles.guard', 'web');
        $app['config']->set('roles.cache.enabled', true);
        $app['config']->set('roles.cache.ttl', 300);

        // UI disabled by default in tests
        $app['config']->set('roles.ui.enabled', false);
        $app['config']->set('roles.ui.driver', 'vue');

        // Expose me enabled for tests
        $app['config']->set('roles.routes.expose_me', true);
    }

    /**
     * Define database migrations.
     */
    protected function defineDatabaseMigrations(): void
    {
        // Create all tables in the correct order
        $this->createAllTestTables();
    }

    /**
     * Create all test tables.
     */
    protected function createAllTestTables(): void
    {
        // Users table
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        // Permissions table (with package extensions)
        Schema::create('permissions', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->text('description')->nullable();
            $table->string('group')->nullable();
            $table->string('status')->default('active');
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        // Roles table (with package extensions)
        Schema::create('roles', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        // Model has permissions pivot
        Schema::create('model_has_permissions', function ($table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_primary');
            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->onDelete('cascade');
            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
        });

        // Model has roles pivot
        Schema::create('model_has_roles', function ($table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_primary');
            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('cascade');
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
        });

        // Role has permissions pivot
        Schema::create('role_has_permissions', function ($table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->onDelete('cascade');
            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('cascade');
        });
    }

    /**
     * Authenticate a test user.
     */
    protected function authenticateTestUser(): TestUser
    {
        $user = $this->createTestUser();
        $this->actingAs($user);
        return $user;
    }

    /**
     * Create a test user.
     */
    protected function createTestUser(array $attributes = []): TestUser
    {
        return TestUser::create(array_merge([
            'name' => 'Test User',
            'email' => 'test' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ], $attributes));
    }

    /**
     * Clear all caches.
     */
    protected function clearCaches(): void
    {
        Cache::flush();

        if (app()->bound(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    /**
     * Start counting queries.
     */
    protected function startQueryCount(): void
    {
        $this->queryCount = 0;
        $this->queryLog = [];

        DB::listen(function ($query) {
            $this->queryCount++;
            $this->queryLog[] = $query->sql;
        });
    }

    /**
     * Stop counting queries and return count.
     */
    protected function stopQueryCount(): int
    {
        DB::flushQueryLog();
        return $this->queryCount;
    }

    /**
     * Assert query count is within limit.
     */
    protected function assertQueryCount(int $maxQueries, string $message = ''): void
    {
        $count = $this->stopQueryCount();
        $this->assertLessThanOrEqual(
            $maxQueries,
            $count,
            $message ?: "Expected at most {$maxQueries} queries, got {$count}."
        );
    }

    /**
     * Assert no N+1 queries.
     */
    protected function assertNoN1(callable $callback, int $maxQueries = 5, string $message = '')
    {
        $this->startQueryCount();
        $result = $callback();
        $this->assertQueryCount($maxQueries, $message);
        return $result;
    }

    /**
     * Set config value for this test.
     */
    protected function setConfig(string $key, $value): void
    {
        config([$key => $value]);
    }

    /**
     * Get the package base path.
     */
    protected function getPackageBasePath(): string
    {
        return dirname(__DIR__);
    }
}
