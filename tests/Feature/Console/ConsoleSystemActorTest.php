<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;

it('runs roles:seed and roles:owner as the system actor even when someone is logged in', function () {
    $loggedIn = makeUser('someone@example.test');
    $boss = makeUser('boss@example.test');
    $this->actingAs($loggedIn);

    $this->artisan('roles:seed')->assertExitCode(0);
    $this->artisan('roles:owner boss@example.test')->assertExitCode(0);

    expect($boss->fresh()->isOwner())->toBeTrue()
        ->and(ActionLog::query()->where('event', 'roles.assigned')->sole()->actor_type)->toBeNull()
        ->and(ActionLog::query()->where('event', 'roles.seeded')->sole()->actor_type)->toBeNull();
});

it('runs roles:sync as the system actor', function () {
    config(['roles.permissions_file' => __DIR__.'/../../Support/registry/valid.php']);
    $this->actingAs(makeUser('someone@example.test'));

    $this->artisan('roles:sync')->assertExitCode(0);

    expect(ActionLog::query()->where('event', 'permissions.synced')->sole()->actor_type)->toBeNull();
});
