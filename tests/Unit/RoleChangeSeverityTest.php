<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Audit\RoleChangeSeverity;
use Enadstack\LaravelRoles\Audit\Severity;

it('is critical when the owner role is added or removed', function () {
    $owner = makeRole('owner');

    expect(RoleChangeSeverity::for([$owner], []))->toBe(Severity::Critical)
        ->and(RoleChangeSeverity::for([], [$owner]))->toBe(Severity::Critical);
});

it('is warning only when a high-risk role is added', function () {
    makePermission('x.do', 'high');
    $high = makeRole('risky', ['x.do']);

    expect(RoleChangeSeverity::for([$high], []))->toBe(Severity::Warning)
        ->and(RoleChangeSeverity::for([], [$high]))->toBe(Severity::Info);
});

it('is info otherwise', function () {
    expect(RoleChangeSeverity::for([makeRole('plain')], []))->toBe(Severity::Info);
});
