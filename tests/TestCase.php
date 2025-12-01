<?php

namespace Tests;

use Enadstack\LaravelRoles\Providers\RolesServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Permission\PermissionServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Auth\User as TestUser;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            PermissionServiceProvider::class,
            RolesServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // In tests we allow all policy checks to simplify request flows.
        Gate::before(function () {
            return true;
        });

        // Provide a lightweight Authenticatable user so Spatie and Gate integrations receive an Authorizable instance.
        $user = new TestUser();
        $user->id = 1;
        $user->name = 'test';
        $user->email = 'test@example.com';

        $this->actingAs($user);
    }

    protected function defineEnvironment($app): void
    {
        // Base app key
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        // Use sqlite in-memory for speed
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        // Default guard web
        $app['config']->set('auth.defaults.guard', 'web');
        $app['config']->set('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);
        // Use a valid eloquent provider for tests (array driver is invalid for auth providers)
        $app['config']->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => \Illuminate\Foundation\Auth\User::class,
        ]);

        // Cache: array store (no tags)
        $app['config']->set('cache.default', 'array');
        $app['config']->set('cache.stores.array', ['driver' => 'array']);

        // Package route middleware relaxed for tests
        $app['config']->set('roles.routes.middleware', ['api']);
        $app['config']->set('roles.routes.prefix', 'admin/acl');

        // Disable i18n for tests to avoid complex migration logic
        $app['config']->set('roles.i18n.enabled', false);
        $app['config']->set('roles.tenancy.mode', 'single');
    }

    protected function defineDatabaseMigrations(): void
    {
        // Create base Spatie permission tables using the stub
        $this->artisan('vendor:publish', ['--provider' => PermissionServiceProvider::class])->run();

        // Run all migrations in order
        $this->loadMigrationsFrom(database_path('migrations'));
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
