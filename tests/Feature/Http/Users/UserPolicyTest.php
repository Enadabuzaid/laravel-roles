<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\RolesServiceProvider;
use Enadstack\LaravelRoles\Users\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Tests\Support\TestUser;

it('maps user policy abilities to permissions', function (): void {
    $user = makeUser();
    $user->assignRole(makeRole('mod', ['access.users.view', 'access.users.suspend']));
    $this->actingAs($user);

    expect(Gate::getPolicyFor(TestUser::class))->toBeInstanceOf(UserPolicy::class)
        ->and(Gate::allows('viewAny', TestUser::class))->toBeTrue()
        ->and(Gate::allows('view', $user))->toBeTrue()
        ->and(Gate::allows('suspend', $user))->toBeTrue()
        ->and(Gate::allows('create', TestUser::class))->toBeFalse()
        ->and(Gate::allows('update', $user))->toBeFalse()
        ->and(Gate::allows('delete', $user))->toBeFalse();
});

it('lets the owner do everything through the policy', function (): void {
    $this->actingAs(makeOwner());

    foreach (['viewAny', 'create', 'update', 'suspend', 'delete'] as $ability) {
        expect(Gate::allows($ability, TestUser::class))->toBeTrue();
    }
});

it('never replaces a policy the host already registered for the user model', function (): void {
    $hostPolicy = new class
    {
        public function viewAny(): bool
        {
            return true;
        }
    };
    Gate::policy(TestUser::class, $hostPolicy::class);

    (new RolesServiceProvider($this->app))->boot();

    expect(Gate::getPolicyFor(TestUser::class))->toBeInstanceOf($hostPolicy::class);
});
