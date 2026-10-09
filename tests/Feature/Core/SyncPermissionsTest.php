<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Core\Actions\SyncPermissions;
use Enadstack\LaravelRoles\Core\Registry\PermissionRegistry;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Models\Role;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

function syncWith(string $file, bool $dry = false, bool $prune = false)
{
    return (new SyncPermissions(new PermissionRegistry(dirname(__DIR__, 2).'/Support/registry/'.$file.'.php', [])))->handle($dry, $prune);
}

it('adds new permissions with labels, module, risk and sort order', function () {
    $result = syncWith('valid');

    expect($result->added)->toBe(['orders.view', 'orders.update', 'orders.refund'])->and($result->hasChanges())->toBeTrue();

    $refund = Permission::where('name', 'orders.refund')->first();
    expect($refund->risk)->toBe('high')->and($refund->module)->toBe('orders')
        ->and($refund->label)->toBe(['en' => 'Refund orders'])->and($refund->last_synced_at)->not->toBeNull()
        ->and($refund->guard_name)->toBe('web');
});

it('is idempotent and uses a fixed number of queries on a no-op run', function () {
    syncWith('valid');

    DB::enableQueryLog();
    $result = syncWith('valid');
    $queries = count(DB::getQueryLog());

    expect($result->hasChanges())->toBeFalse()->and($queries)->toBeLessThanOrEqual(4);
});

it('dry run reports but writes nothing', function () {
    $result = syncWith('valid', dry: true);

    expect($result->added)->toHaveCount(3)->and(Permission::count())->toBe(0);
});

it('updates changed metadata', function () {
    syncWith('valid');
    Permission::where('name', 'orders.view')->update(['risk' => 'high', 'label' => json_encode(['en' => 'Old'])]);

    $result = syncWith('valid');

    expect($result->updated)->toBe(['orders.view'])->and(Permission::where('name', 'orders.view')->value('risk'))->toBe('low');
});

it('deprecates registry permissions that disappear and restores them when they return', function () {
    syncWith('valid');
    Permission::create(['name' => 'ghost.view', 'guard_name' => 'web', 'source' => 'registry']);

    $result = syncWith('valid');
    expect($result->deprecated)->toBe(['ghost.view'])->and(Permission::where('name', 'ghost.view')->first()->deprecated_at)->not->toBeNull();

    Permission::where('name', 'orders.view')->update(['deprecated_at' => now()]);
    expect(syncWith('valid')->restored)->toBe(['orders.view']);
});

it('never touches manual permissions', function () {
    Permission::create(['name' => 'custom.thing', 'guard_name' => 'web', 'source' => 'manual']);

    $result = syncWith('valid', prune: true);

    expect($result->deprecated)->toBe([])->and(Permission::where('name', 'custom.thing')->exists())->toBeTrue();
});

it('prunes only deprecated permissions nobody holds', function () {
    syncWith('valid');
    $free = Permission::create(['name' => 'free.old', 'guard_name' => 'web', 'source' => 'registry', 'deprecated_at' => now()]);
    $held = Permission::create(['name' => 'held.old', 'guard_name' => 'web', 'source' => 'registry', 'deprecated_at' => now()]);
    Role::create(['name' => 'r', 'guard_name' => 'web'])->givePermissionTo($held);

    $result = syncWith('valid', prune: true);

    expect($result->pruned)->toBe(['free.old'])
        ->and(Permission::where('name', 'free.old')->exists())->toBeFalse()
        ->and(Permission::where('name', 'held.old')->exists())->toBeTrue();
});

it('clears the spatie permission cache after writing', function () {
    $registrar = app(PermissionRegistrar::class);
    $registrar->getPermissions(); // warm cache

    syncWith('valid');

    expect($registrar->getPermissions()->pluck('name')->all())->toContain('orders.view');
});

it('does not report a spurious update when the database reorders json keys', function () {
    syncWith('reordered');

    $result = syncWith('reordered');

    expect($result->updated)->toBe([])->and($result->hasChanges())->toBeFalse();
});
