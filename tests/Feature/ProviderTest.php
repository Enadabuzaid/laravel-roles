<?php

declare(strict_types=1);
use Illuminate\Support\ServiceProvider;

it('merges the roles config', function () {
    expect(config('roles.guard'))->toBe('web')
        ->and(config('roles.super_role'))->toBe('owner')
        ->and(config('roles.pagination.max'))->toBe(100)
        ->and(config('roles.modules.users'))->toBeTrue();
});

it('registers the publish groups', function () {
    $groups = ServiceProvider::publishableGroups();

    expect($groups)->toContain('roles-config', 'roles-migrations', 'roles-permissions');
});
