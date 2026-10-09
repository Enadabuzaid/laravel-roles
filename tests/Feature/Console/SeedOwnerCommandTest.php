<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Models\Role;
use Tests\Support\TestUser;

it('seeds default roles', function () {
    $this->artisan('roles:seed')->assertExitCode(0);

    expect(Role::count())->toBe(4);
});

it('makes a user the owner, even when the owner role was trashed', function () {
    $user = TestUser::create(['name' => 'A', 'email' => 'boss@example.test', 'password' => 'x']);
    $this->artisan('roles:seed')->assertExitCode(0);
    Role::where('name', 'owner')->first()->delete();

    $this->artisan('roles:owner boss@example.test')->assertExitCode(0);

    expect($user->fresh()->isOwner())->toBeTrue();
});

it('fails for an unknown email', function () {
    $this->artisan('roles:owner nobody@example.test')->assertExitCode(1);
});
