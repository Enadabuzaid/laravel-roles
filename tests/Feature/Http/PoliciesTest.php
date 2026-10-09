<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Models\Role;
use Illuminate\Support\Facades\Gate;

it('maps role policy abilities to permissions', function (): void {
    $user = makeUser();
    $user->assignRole(makeRole('viewer', ['access.roles.view']));

    $this->actingAs($user);

    expect(Gate::allows('viewAny', Role::class))->toBeTrue()
        ->and(Gate::allows('view', new Role))->toBeTrue()
        ->and(Gate::allows('create', Role::class))->toBeFalse()
        ->and(Gate::allows('update', new Role))->toBeFalse()
        ->and(Gate::allows('delete', new Role))->toBeFalse()
        ->and(Gate::allows('assign', new Role))->toBeFalse();
});

it('maps delete, restore and force delete to the delete permission', function (): void {
    $user = makeUser();
    $user->assignRole(makeRole('deleter', ['access.roles.delete', 'access.roles.assign']));

    $this->actingAs($user);

    expect(Gate::allows('delete', new Role))->toBeTrue()
        ->and(Gate::allows('restore', new Role))->toBeTrue()
        ->and(Gate::allows('forceDelete', new Role))->toBeTrue()
        ->and(Gate::allows('assign', new Role))->toBeTrue();
});

it('lets the owner do everything', function (): void {
    $this->actingAs(makeOwner());

    foreach (['viewAny', 'create', 'update', 'delete', 'assign'] as $ability) {
        expect(Gate::allows($ability, Role::class))->toBeTrue();
    }
    expect(Gate::allows('sync', Permission::class))->toBeTrue();
});

it('maps permission policy abilities', function (): void {
    $user = makeUser();
    $user->assignRole(makeRole('syncer', ['access.permissions.sync']));

    $this->actingAs($user);

    expect(Gate::allows('sync', Permission::class))->toBeTrue()
        ->and(Gate::allows('create', Permission::class))->toBeTrue()
        ->and(Gate::allows('viewAny', Permission::class))->toBeFalse();

    $viewer = makeUser('v@example.test');
    $viewer->assignRole(makeRole('pviewer', ['access.permissions.view']));

    expect(Gate::forUser($viewer)->allows('viewAny', Permission::class))->toBeTrue()
        ->and(Gate::forUser($viewer)->allows('sync', Permission::class))->toBeFalse();
});

it('denies when the permission does not exist', function (): void {
    $this->actingAs(makeUser());

    expect(Gate::allows('create', Role::class))->toBeFalse();
});
