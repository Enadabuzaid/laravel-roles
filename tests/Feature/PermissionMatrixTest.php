<?php

use Tests\TestCase;

uses(TestCase::class);

use Enadstack\LaravelRoles\Services\PermissionService;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Models\Permission;

it('builds a permission matrix', function () {
    $roleA = Role::create(['name' => 'roleA', 'guard_name' => 'web']);
    $roleB = Role::create(['name' => 'roleB', 'guard_name' => 'web']);
    $p1 = Permission::create(['name' => 'offers.create', 'guard_name' => 'web']);
    $p2 = Permission::create(['name' => 'offers.update', 'guard_name' => 'web']);

    $roleA->givePermissionTo($p1);
    $roleB->givePermissionTo([$p1, $p2]);

    $service = app(PermissionService::class);
    $matrix = $service->getPermissionMatrix();

    expect($matrix['roles'])->toBeArray();
    expect($matrix['matrix'])->toBeArray();
    $row = collect($matrix['matrix'])->firstWhere('permission_name', 'offers.create');
    expect($row['roles']['roleA']['has_permission'])->toBeTrue();
    expect($row['roles']['roleB']['has_permission'])->toBeTrue();
});
