<?php

use Tests\TestCase;

uses(TestCase::class);

use Enadstack\LaravelRoles\Commands\SyncCommand;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

it('syncs permissions from config and maps to roles', function () {
    config()->set('roles.guard', 'web');
    config()->set('roles.seed.permission_groups', [
        'offers' => ['list', 'create', 'show', 'update'],
    ]);
    config()->set('roles.seed.map', [
        'admin' => ['offers.*'],
    ]);

    // Run sync
    Artisan::call('roles:sync');

    expect(Permission::where('name', 'offers.create')->exists())->toBeTrue();
    $admin = Role::where('name', 'admin')->firstOrFail();
    expect($admin->hasPermissionTo('offers.create'))->toBeTrue();
});

it('prunes permissions not in config when asked', function () {
    config()->set('roles.guard', 'web');
    config()->set('roles.seed.permission_groups', [
        'offers' => ['list'],
    ]);

    Artisan::call('roles:sync'); // creates offers.list

    // Add extra perm then prune
    Permission::create(['name' => 'ghost.perm', 'guard_name' => 'web']);
    expect(Permission::where('name', 'ghost.perm')->exists())->toBeTrue();

    Artisan::call('roles:sync', ['--prune' => true]);

    expect(Permission::where('name', 'ghost.perm')->exists())->toBeFalse();
});
