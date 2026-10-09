<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Core\Doctor\Checks\CacheWorkingCheck;
use Enadstack\LaravelRoles\Core\Doctor\Checks\ConfigConsistencyCheck;
use Enadstack\LaravelRoles\Core\Doctor\Checks\NoEmptyRolesCheck;
use Enadstack\LaravelRoles\RolesServiceProvider;
use Tests\Support\HostExtraCheck;

function mergedChecks(array $hostDoctor): array
{
    config(['roles.doctor' => $hostDoctor]);
    (new RolesServiceProvider(app()))->register();

    return config('roles.doctor.checks');
}

it('keeps every package check when the host only lists its own', function () {
    $checks = mergedChecks(['checks' => [HostExtraCheck::class]]);

    expect($checks)->toContain(ConfigConsistencyCheck::class, CacheWorkingCheck::class, HostExtraCheck::class)
        ->and(array_count_values($checks)[HostExtraCheck::class])->toBe(1);
});

it('does not duplicate checks the host repeats', function () {
    $checks = mergedChecks(['checks' => [CacheWorkingCheck::class, HostExtraCheck::class]]);

    expect(array_count_values($checks)[CacheWorkingCheck::class])->toBe(1);
});

it('lets the host disable package checks', function () {
    $checks = mergedChecks(['checks' => [], 'disabled' => [NoEmptyRolesCheck::class]]);

    expect($checks)->not->toContain(NoEmptyRolesCheck::class)->and($checks)->toContain(CacheWorkingCheck::class);
});
