<?php

declare(strict_types=1);

namespace Tests;

use Enadstack\ActionLogger\ActionLoggerServiceProvider;
use Enadstack\LaravelRoles\RolesServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Permission\PermissionServiceProvider;
use Tests\Support\TestUser;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        self::removePublishedExtensionMigrations();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        self::removePublishedExtensionMigrations();
    }

    /**
     * roles:install publishes the extension migrations into the Testbench skeleton; they
     * must not leak into other tests, which load the stubs through tests/database/migrations.
     */
    private static function removePublishedExtensionMigrations(): void
    {
        $directory = \Orchestra\Testbench\default_skeleton_path('database/migrations');

        foreach (['extend_roles_table', 'extend_permissions_table'] as $name) {
            foreach (glob($directory.'/*_'.$name.'.php') ?: [] as $file) {
                @unlink($file);
            }
        }
    }

    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [PermissionServiceProvider::class, ActionLoggerServiceProvider::class, RolesServiceProvider::class];
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
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('cache.default', 'array');
        $app['config']->set('roles.permissions_file', null);
        $app['config']->set('action-logger.immutable', true);
        $app['config']->set('roles.user_model', TestUser::class);
        $app['config']->set('auth.providers.users.model', TestUser::class);
        $app['config']->set('roles.routes.middleware', ['web', 'auth']);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(\Orchestra\Testbench\default_migration_path());
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
