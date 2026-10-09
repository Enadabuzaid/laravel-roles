<?php

declare(strict_types=1);

namespace Tests;

use Enadstack\LaravelRoles\RolesServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Permission\PermissionServiceProvider;
use Tests\Support\TestUser;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [PermissionServiceProvider::class, RolesServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $driver = env('DB_CONNECTION', 'sqlite');
        $app['config']->set('database.default', $driver);
        if ($driver === 'sqlite') {
            $app['config']->set('database.connections.sqlite.database', ':memory:');
        } else {
            $app['config']->set("database.connections.{$driver}.database", env('DB_DATABASE', 'roles_testing'));
        }
        $app['config']->set('cache.default', 'array');
        $app['config']->set('roles.permissions_file', null);
        $app['config']->set('roles.user_model', TestUser::class);
        $app['config']->set('auth.providers.users.model', TestUser::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(\Orchestra\Testbench\default_migration_path());
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
