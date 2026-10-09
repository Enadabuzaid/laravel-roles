<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Roles\Actions\SeedDefaultRoles;

it('writes one roles.seeded entry when roles are created and none on re-run', function () {
    app(SeedDefaultRoles::class)->handle();

    $log = ActionLog::sole();
    expect($log->event)->toBe('roles.seeded')->and($log->module)->toBe('roles')->and($log->subject_type)->toBeNull()
        ->and($log->new_values['created'])->toContain('owner')->and($log->new_values['restored'])->toEqual([]);

    ActionLog::query()->delete();
    app(SeedDefaultRoles::class)->handle();
    expect(ActionLog::count())->toBe(0);
});

it('records restored roles', function () {
    app(SeedDefaultRoles::class)->handle();
    Role::where('name', 'owner')->first()?->delete();
    ActionLog::query()->delete();

    app(SeedDefaultRoles::class)->handle();

    $log = ActionLog::sole();
    expect($log->new_values['restored'])->toEqual(['owner'])->and($log->new_values['created'])->toEqual([]);
});
