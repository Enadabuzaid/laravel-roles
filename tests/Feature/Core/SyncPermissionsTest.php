<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Core\Actions\SyncPermissions;
use Enadstack\LaravelRoles\Core\Registry\PermissionRegistry;
use Enadstack\LaravelRoles\Core\Registry\PermissionRegistryException;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Models\Role;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\TestUser;

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

function syncWithGeneratedRegistry(int $count): int
{
    $file = tempnam(sys_get_temp_dir(), 'roles-registry-');
    $permissions = [];
    for ($i = 1; $i <= $count; $i++) {
        $permissions["gen.perm{$i}"] = ['label' => ['en' => "Perm {$i}", 'ar' => "صلاحية {$i}"], 'risk' => 'low'];
    }
    file_put_contents($file, '<?php return '.var_export(['gen' => ['permissions' => $permissions]], true).';');

    try {
        $registry = new PermissionRegistry($file, []);
        (new SyncPermissions($registry))->handle(false, false);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $result = (new SyncPermissions($registry))->handle(false, false);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        expect($result->hasChanges())->toBeFalse();

        return $queries;
    } finally {
        @unlink($file);
    }
}

it('uses the same number of queries for a small and a large registry', function () {
    $small = syncWithGeneratedRegistry(5);
    Permission::query()->delete();
    $large = syncWithGeneratedRegistry(120);

    expect($small)->toBeGreaterThan(0)->and($large)->toBe($small);
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

// Discrimination happens only on pgsql: jsonb reorders ['en','ar'] to ['ar','en'], which a strict
// array comparison would flag as a change. On sqlite the JSON text is stored verbatim.
it('does not report a spurious update when the database reorders json keys', function () {
    syncWith('reordered');

    $result = syncWith('reordered');

    expect($result->updated)->toBe([])->and($result->hasChanges())->toBeFalse();
});

it('leaves the database untouched when the registry file is unusable', function (string $file) {
    syncWith('valid');
    $snapshot = Permission::query()->orderBy('id')->get()->toArray();

    try {
        syncWith($file, prune: true);
        $thrown = null;
    } catch (PermissionRegistryException $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeInstanceOf(PermissionRegistryException::class)
        ->and(Permission::query()->orderBy('id')->get()->toArray())->toBe($snapshot);
})->with(['missing' => 'does-not-exist', 'not array' => 'not-array', 'bad risk' => 'bad-risk']);

it('never prunes a manual permission even when deprecated', function () {
    syncWith('valid');
    Permission::create(['name' => 'manual.old', 'guard_name' => 'web', 'source' => 'manual', 'deprecated_at' => now()]);

    $result = syncWith('valid', prune: true);

    expect($result->pruned)->toBe([])->and(Permission::where('name', 'manual.old')->exists())->toBeTrue();
});

it('never prunes a deprecated permission held directly by a user', function () {
    syncWith('valid');
    $held = Permission::create(['name' => 'direct.old', 'guard_name' => 'web', 'source' => 'registry', 'deprecated_at' => now()]);
    $user = TestUser::create(['name' => 'U', 'email' => 'u@example.test', 'password' => 'x']);
    $user->givePermissionTo($held);

    $result = syncWith('valid', prune: true);

    expect($result->pruned)->toBe([])->and(Permission::where('name', 'direct.old')->exists())->toBeTrue();
});

it('adopts a manual permission whose name appears in the registry', function () {
    Permission::create(['name' => 'orders.view', 'guard_name' => 'web', 'source' => 'manual']);

    $result = syncWith('valid');

    expect($result->updated)->toBe(['orders.view'])->and($result->added)->toBe(['orders.update', 'orders.refund'])
        ->and(Permission::where('name', 'orders.view')->value('source'))->toBe('registry');
});
