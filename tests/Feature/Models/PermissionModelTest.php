<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Models\Permission;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

it('has the extended columns', function () {
    expect(Schema::hasColumns('permissions', ['module', 'label', 'description', 'risk', 'source', 'deprecated_at', 'last_synced_at', 'sort_order']))->toBeTrue();
});

it('defaults risk to low and source to registry', function () {
    $p = Permission::create(['name' => 'orders.view', 'guard_name' => 'web'])->fresh();

    expect($p->risk)->toBe('low')->and($p->source)->toBe('registry')->and($p->deprecated_at)->toBeNull();
});

it('scopes active permissions', function () {
    Permission::create(['name' => 'a.view', 'guard_name' => 'web']);
    Permission::create(['name' => 'b.view', 'guard_name' => 'web', 'deprecated_at' => now()]);

    expect(Permission::active()->pluck('name')->all())->toBe(['a.view']);
});

it('is the class spatie resolves', function () {
    expect(app(PermissionRegistrar::class)->getPermissionClass())->toBe(Permission::class);
});
