<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestUser;

afterEach(function (): void {
    Model::preventLazyLoading(false);
});

function overviewActor(): TestUser
{
    $user = makeUser('actor@example.test');
    $user->assignRole(makeRole('viewer', ['access.roles.view']));

    return $user;
}

it('returns exact aggregates for a seeded scenario', function (): void {
    makeRole('sys', ['p.one', 'p.two', 'p.three'], ['is_system' => true]);
    $custom = makeRole('cust', ['p.one']);
    makeRole('gone', ['p.one', 'p.two'])->delete();
    makeUser('a@example.test')->assignRole($custom);
    makeUser('b@example.test');
    makeUser('c@example.test');

    $this->actingAs(overviewActor())->getJson('/api/access/roles/overview')->assertOk()
        ->assertJsonPath('data', [
            'total_roles' => 4,
            'active_roles' => 3,
            'system_roles' => 1,
            'custom_roles' => 2,
            'trashed_roles' => 1,
            'members_with_role' => 2,
            'members_without_role' => 2,
            'avg_permissions_per_role' => 1.67,
        ]);
});

it('executes a constant number of queries', function (): void {
    Model::preventLazyLoading();
    $this->actingAs(overviewActor());

    $run = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/access/roles/overview')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $run();
    $small = $run();
    foreach (range(1, 30) as $i) {
        makeUser("u{$i}@example.test")->assignRole(makeRole("r{$i}", ['p.x']));
    }

    $run();

    expect($run())->toBe($small);
});
