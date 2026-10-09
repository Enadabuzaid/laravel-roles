<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Roles\Actions\SeedDefaultRoles;

it('creates the default roles with arabic and english labels', function () {
    $roles = app(SeedDefaultRoles::class)->handle();

    expect($roles->keys()->all())->toBe(['owner', 'admin', 'manager', 'viewer'])
        ->and(Role::where('name', 'owner')->first()->label)->toEqual(['en' => 'Owner', 'ar' => 'المالك'])
        ->and(Role::where('name', 'owner')->first()->is_system)->toBeTrue()
        ->and(Role::where('name', 'viewer')->first()->is_default)->toBeTrue();
});

it('is idempotent and never overwrites edited roles', function () {
    app(SeedDefaultRoles::class)->handle();
    Role::where('name', 'admin')->update(['label' => json_encode(['en' => 'Custom'])]);

    app(SeedDefaultRoles::class)->handle();

    expect(Role::count())->toBe(4)->and(Role::where('name', 'admin')->first()->label)->toBe(['en' => 'Custom']);
});

it('restores a soft-deleted role instead of colliding', function () {
    app(SeedDefaultRoles::class)->handle();
    Role::where('name', 'owner')->first()->delete();

    app(SeedDefaultRoles::class)->handle(only: ['owner']);

    expect(Role::where('name', 'owner')->exists())->toBeTrue()->and(Role::withTrashed()->where('name', 'owner')->count())->toBe(1);
});

it('honours the only filter', function () {
    expect(app(SeedDefaultRoles::class)->handle(only: ['owner'])->keys()->all())->toBe(['owner'])->and(Role::count())->toBe(1);
});
