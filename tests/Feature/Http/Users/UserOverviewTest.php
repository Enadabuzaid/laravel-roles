<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Users\Models\AccessInvitation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestUser;

afterEach(fn () => Carbon::setTestNow());

function overviewUser(string $name, string $createdAt, ?string $status = null, array $state = [], ?string $role = null): TestUser
{
    $user = TestUser::create(['name' => $name, 'email' => strtolower($name).'@example.test', 'password' => 'x']);
    DB::table('users')->where('id', $user->id)->update(['created_at' => $createdAt]);

    if ($status !== null) {
        makeState($user, $status, $state);
    }

    if ($role !== null) {
        $user->assignRole($role);
    }

    return $user;
}

function invitation(string $email, array $attributes = []): void
{
    AccessInvitation::create(['email' => $email, 'token_hash' => hash('sha256', $email), 'expires_at' => now()->addDay(), ...$attributes]);
}

it('summarises the user base with exact numbers and weekly series', function (): void {
    Carbon::setTestNow('2026-06-30 12:00:00');
    $team = makeRole('team');
    $actor = usersActor();
    DB::table('users')->where('id', $actor->id)->update(['created_at' => '2025-01-01 00:00:00']);
    overviewUser('Ann', '2026-06-29 09:00:00', 'active', ['mfa_enabled_at' => '2026-06-29 10:00:00'], 'team');
    overviewUser('Bob', '2026-06-20 09:00:00', 'suspended');
    overviewUser('Cat', '2026-05-01 09:00:00', 'locked', [], 'team');
    overviewUser('Dan', '2026-05-06 09:00:00', 'deactivated');
    overviewUser('Eve', '2026-06-10 09:00:00');

    invitation('one@example.test');
    invitation('two@example.test');
    invitation('accepted@example.test', ['accepted_at' => now()]);
    invitation('revoked@example.test', ['revoked_at' => now()]);
    invitation('expired@example.test', ['expires_at' => now()->subMinute()]);

    $this->actingAs($actor)->getJson('/api/access/users/overview')->assertOk()
        ->assertJsonPath('success', true)
        ->assertExactJson([
            'success' => true,
            'message' => null,
            'data' => [
                'total_members' => 6,
                'active' => 3,
                'pending_invites' => 2,
                'suspended' => 1,
                'locked' => 1,
                'deactivated' => 1,
                'without_mfa' => 5,
                'without_role' => 3,
                'trashed' => 0,
                'joined_last_30_days' => 3,
                'series' => [
                    'members' => [3, 3, 3, 3, 3, 4, 5, 6],
                    'joined' => [1, 0, 0, 0, 0, 1, 1, 1],
                ],
            ],
            'meta' => [],
        ]);

    expect($team->exists)->toBeTrue();
});

it('counts the series buckets on their exact boundaries', function (): void {
    Carbon::setTestNow('2026-06-30 12:00:00');
    $actor = usersActor();
    DB::table('users')->where('id', $actor->id)->update(['created_at' => '2020-01-01 00:00:00']);
    overviewUser('OnStart', '2026-05-05 12:00:00');   // equal to the first boundary: before bucket 0
    overviewUser('JustAfter', '2026-05-05 12:00:01'); // bucket 0
    overviewUser('OnEdge', '2026-05-12 12:00:00');    // bucket 0 (upper bound inclusive)
    overviewUser('Now', '2026-06-30 12:00:00');       // last bucket
    overviewUser('Future', '2026-06-30 12:00:01');    // outside every bucket

    $series = $this->actingAs($actor)->getJson('/api/access/users/overview')->assertOk()->json('data.series');

    expect($series['joined'])->toBe([2, 0, 0, 0, 0, 0, 0, 1])
        ->and($series['members'])->toBe([4, 4, 4, 4, 4, 4, 4, 5]);
});

it('reports zeros and flat series for an empty directory of members', function (): void {
    Carbon::setTestNow('2026-06-30 12:00:00');
    $actor = usersActor();

    $data = $this->actingAs($actor)->getJson('/api/access/users/overview')->assertOk()->json('data');

    expect($data['total_members'])->toBe(1)->and($data['series']['members'])->toHaveCount(8)->and($data['series']['joined'])->toBe([0, 0, 0, 0, 0, 0, 0, 1]);
});

it('uses a constant number of queries', function (): void {
    $actor = usersActor();
    $count = function () use ($actor): int {
        $this->actingAs($actor);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/access/users/overview')->assertOk();
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    };

    $count();
    $small = $count();
    seedUsers(150);
    $large = $count();

    expect($large)->toBe($small)->and($large)->toBeLessThanOrEqual(12);
});

it('requires authentication and users view', function (): void {
    $this->getJson('/api/access/users/overview')->assertUnauthorized();
    $this->actingAs(makeUser('x@example.test'))->getJson('/api/access/users/overview')->assertForbidden();
});
