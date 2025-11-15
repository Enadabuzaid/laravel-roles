<?php

namespace Tests;

use Enadstack\LaravelRoles\Providers\RolesServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            PermissionServiceProvider::class,
            RolesServiceProvider::class,
        ];
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
        $app['config']->set('auth.providers.users', ['driver' => 'array']);

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
