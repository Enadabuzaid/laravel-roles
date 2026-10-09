<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Models\Role;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

it('has the extended columns', function () {
    expect(Schema::hasColumns('roles', ['label', 'description', 'color', 'is_system', 'is_default', 'sort_order', 'deleted_at']))->toBeTrue();
});

it('round-trips arabic and english labels', function () {
    $role = Role::create(['name' => 'manager', 'guard_name' => 'web', 'label' => ['en' => 'Manager', 'ar' => 'مدير'], 'is_system' => false]);

    expect($role->fresh()->label)->toEqual(['en' => 'Manager', 'ar' => 'مدير'])
        ->and($role->fresh()->is_system)->toBeFalse();
});

it('soft deletes and restores', function () {
    $role = Role::create(['name' => 'temp', 'guard_name' => 'web']);
    $role->delete();

    expect(Role::count())->toBe(0)->and(Role::withTrashed()->count())->toBe(1);
    $role->restore();
    expect(Role::count())->toBe(1);
});

it('is the class spatie resolves', function () {
    expect(config('permission.models.role'))->toBe(Role::class)
        ->and(app(PermissionRegistrar::class)->getRoleClass())->toBe(Role::class);
});
