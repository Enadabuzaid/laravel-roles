<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Core\Registry\PermissionRegistry;
use Enadstack\LaravelRoles\Models\Permission;

beforeEach(function () {
    config(['roles.permissions_file' => dirname(__DIR__, 2).'/Support/registry/valid.php']);
    app()->forgetInstance(PermissionRegistry::class);
});

it('syncs the registry and prints the diff', function () {
    $this->artisan('roles:sync')
        ->expectsOutputToContain('orders.refund')
        ->assertExitCode(0);

    expect(Permission::where('name', 'orders.view')->exists())->toBeTrue();
});

it('dry-run writes nothing', function () {
    $this->artisan('roles:sync --dry-run')->expectsOutputToContain('Dry run')->assertExitCode(0);

    expect(Permission::count())->toBe(0);
});

it('reports a no-op when nothing changed', function () {
    $this->artisan('roles:sync')->assertExitCode(0);
    $this->artisan('roles:sync')->expectsOutputToContain('Already in sync')->assertExitCode(0);
});

it('fails and changes nothing when the permissions file is missing', function () {
    $this->artisan('roles:sync')->assertExitCode(0);
    config(['roles.permissions_file' => '/nope/permissions.php']);
    app()->forgetInstance(PermissionRegistry::class);

    $this->artisan('roles:sync')->expectsOutputToContain('not found')->assertExitCode(1);

    expect(Permission::whereNotNull('deprecated_at')->count())->toBe(0);
});
