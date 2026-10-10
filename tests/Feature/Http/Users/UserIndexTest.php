<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TestUser;

function userNamed(string $name, ?string $createdAt = null): TestUser
{
    $user = TestUser::create(['name' => $name, 'email' => strtolower($name).'@example.test', 'password' => 'x']);

    if ($createdAt !== null) {
        DB::table('users')->where('id', $user->id)->update(['created_at' => $createdAt]);
    }

    return $user;
}

/** @return list<string> */
function listedNames(mixed $response): array
{
    return array_column($response->json('data'), 'name');
}

it('lists users in the envelope with the resource shape', function (): void {
    $actor = usersActor();
    $ann = userNamed('Ann');
    makeState($ann, 'suspended', ['status_reason' => 'Left the company', 'last_login_at' => '2026-03-01 10:00:00', 'last_login_ip' => '10.0.0.1']);
    $ann->assignRole(makeRole('team', [], ['color' => '#123456']));

    $row = collect($this->actingAs($actor)->getJson('/api/access/users?include=roles&search=Ann')->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.pagination.total', 1)
        ->json('data'))->first();

    expect($row)->toMatchArray([
        'id' => $ann->id, 'name' => 'Ann', 'email' => 'ann@example.test', 'status' => 'suspended',
        'status_reason' => 'Left the company', 'last_login_ip' => '10.0.0.1', 'mfa_enabled' => false,
        'is_self' => false, 'is_owner' => false, 'locked' => false, 'lock_reason' => null,
    ])
        ->and($row['last_login_at'])->toStartWith('2026-03-01T10:00:00')
        ->and($row['roles'])->toHaveCount(1)
        ->and($row['roles'][0])->toMatchArray(['name' => 'team', 'color' => '#123456'])
        ->and(array_keys($row['abilities']))->toBe(['view', 'update', 'suspend', 'delete', 'assign', 'restore', 'force_delete', 'edit', 'verify']);
});

it('omits roles unless included', function (): void {
    $this->actingAs(usersActor())->getJson('/api/access/users')->assertOk()->assertJsonMissingPath('data.0.roles');
});

it('requires authentication and the users view permission', function (): void {
    $this->getJson('/api/access/users')->assertUnauthorized()->assertJson(['success' => false, 'code' => 'unauthenticated']);
    $this->actingAs(makeUser('plain@example.test'))->getJson('/api/access/users')->assertForbidden()->assertJson(['success' => false, 'code' => 'forbidden']);
});

it('lists users without a state row as active', function (): void {
    $actor = usersActor();
    userNamed('Stateless');
    makeState(userNamed('Stated'), 'deactivated');

    $rows = collect($this->actingAs($actor)->getJson('/api/access/users?search=Stat')->assertOk()->json('data'))->keyBy('name');

    expect($rows['Stateless']['status'])->toBe('active')->and($rows['Stated']['status'])->toBe('deactivated');
});

it('filters by status including the implicit active, lists and ignores unknown values', function (): void {
    $actor = usersActor();
    userNamed('Plain');
    makeState(userNamed('Susp'), 'suspended');
    makeState(userNamed('Lock'), 'locked');

    $statusNames = fn (string $query): array => array_values(array_diff(listedNames($this->actingAs($actor)->getJson("/api/access/users?{$query}&sort=name")->assertOk()), ['A']));

    expect($statusNames('filter[status]=suspended'))->toBe(['Susp'])
        ->and($statusNames('filter[status][]=suspended&filter[status][]=locked'))->toBe(['Lock', 'Susp'])
        ->and($statusNames('filter[status]=active'))->toBe(['Plain'])
        ->and($statusNames('filter[status]=bogus'))->toBe(['Lock', 'Plain', 'Susp']);
});

it('filters by role id or name and by no_role', function (): void {
    $actor = usersActor();
    $a = userNamed('Ann');
    $b = userNamed('Bob');
    userNamed('Cat');
    $manager = makeRole('manager');
    $viewer = makeRole('reader');
    $a->assignRole($manager);
    $b->assignRole($viewer);

    $names = fn (string $query): array => listedNames($this->actingAs($actor)->getJson("/api/access/users?{$query}&sort=name")->assertOk());

    expect($names('filter[role]='.$manager->id))->toBe(['Ann'])
        ->and($names('filter[role]=reader'))->toBe(['Bob'])
        ->and($names('filter[role][]=manager&filter[role][]=reader'))->toBe(['Ann', 'Bob'])
        ->and($names('filter[role]=nope'))->toBe([])
        ->and($names('filter[role]=99999999999999999999'))->toBe([])
        ->and($names('filter[no_role]=yes'))->toBe(['Cat'])
        ->and($names('filter[no_role]=maybe'))->toBe(['A', 'Ann', 'Bob', 'Cat']);
});

it('filters by last login and join dates with inclusive bounds and ignores garbage', function (): void {
    $actor = usersActor();
    DB::table('users')->update(['created_at' => '2020-01-01 00:00:00']);
    $early = userNamed('Early', '2026-01-10 00:00:00');
    $late = userNamed('Late', '2026-01-20 23:59:59');
    makeState($early, 'active', ['last_login_at' => '2026-02-01 00:00:00']);
    makeState($late, 'active', ['last_login_at' => '2026-02-05 23:59:59']);

    $names = fn (string $query): array => listedNames($this->actingAs($actor)->getJson("/api/access/users?{$query}&sort=name")->assertOk());

    expect($names('filter[joined_from]=2026-01-10&filter[joined_to]=2026-01-20'))->toBe(['Early', 'Late'])
        ->and($names('filter[joined_from]=2026-01-11'))->toBe(['Late'])
        ->and($names('filter[joined_to]=2026-01-19'))->toBe(['A', 'Early'])
        ->and($names('filter[last_login_from]=2026-02-01&filter[last_login_to]=2026-02-05'))->toBe(['Early', 'Late'])
        ->and($names('filter[last_login_from]=2026-02-02'))->toBe(['Late'])
        ->and($names('filter[last_login_to]=2026-02-04'))->toBe(['Early'])
        ->and($names('filter[last_login_from]=not-a-date&filter[joined_to]=2026-13-45'))->toBe(['A', 'Early', 'Late']);
});

it('sorts by name, email, status, last login and created in both directions', function (): void {
    $actor = usersActor();
    DB::table('users')->update(['created_at' => '2019-01-01 00:00:00']);
    $a = userNamed('Ann', '2026-01-03 00:00:00');
    $b = userNamed('Bob', '2026-01-01 00:00:00');
    $c = userNamed('Cat', '2026-01-02 00:00:00');
    makeState($a, 'suspended', ['last_login_at' => '2026-05-01 00:00:00']);
    makeState($b, 'active', ['last_login_at' => '2026-05-03 00:00:00']);
    makeState($c, 'active', ['last_login_at' => '2026-05-02 00:00:00']);
    makeState($actor, 'active', ['last_login_at' => '2026-05-04 00:00:00']);

    $only = fn (string $sort): array => array_values(array_intersect(listedNames($this->actingAs($actor)->getJson("/api/access/users?sort={$sort}")->assertOk()), ['Ann', 'Bob', 'Cat']));

    expect($only('name'))->toBe(['Ann', 'Bob', 'Cat'])
        ->and($only('-name'))->toBe(['Cat', 'Bob', 'Ann'])
        ->and($only('-email'))->toBe(['Cat', 'Bob', 'Ann'])
        ->and($only('created'))->toBe(['Bob', 'Cat', 'Ann'])
        ->and($only('-created'))->toBe(['Ann', 'Cat', 'Bob'])
        ->and($only('last_login'))->toBe(['Ann', 'Cat', 'Bob'])
        ->and($only('-last_login'))->toBe(['Bob', 'Cat', 'Ann'])
        ->and($only('-status'))->toBe(['Ann', 'Bob', 'Cat'])
        ->and($only('status'))->toBe(['Bob', 'Cat', 'Ann']);
});

it('falls back to the default sort for unknown sorts and survives garbage input', function (): void {
    $actor = usersActor();
    userNamed('Zed');

    foreach ([
        'sort=password', 'sort=-;drop', 'per_page=abc', 'per_page=100000', 'page=-4', 'page=abc', 'search[]=x',
        'filter=oops', 'filter[status][a][b]=1', 'filter[role][][]=1', 'filter[mfa]=%00', 'include=roles,secrets', 'search=%25%5F',
    ] as $query) {
        $this->actingAs($actor)->getJson("/api/access/users?{$query}")->assertOk()->assertJsonPath('success', true);
    }

    $this->actingAs($actor)->getJson('/api/access/users?per_page=100000')->assertJsonPath('meta.pagination.per_page', 100);
    expect(listedNames($this->actingAs($actor)->getJson('/api/access/users?sort=password')))->toBe(['A', 'Zed']);
});

it('searches name and email literally', function (): void {
    $actor = usersActor();
    userNamed('Per%cent');
    userNamed('Under_score');
    userNamed('Other');

    expect(listedNames($this->actingAs($actor)->getJson('/api/access/users?search=%25')))->toBe(['Per%cent'])
        ->and(listedNames($this->actingAs($actor)->getJson('/api/access/users?search=_')))->toBe(['Under_score'])
        ->and(listedNames($this->actingAs($actor)->getJson('/api/access/users?search=other@example')))->toBe(['Other']);
});

it('filters mfa by the state column when the host has no mfa column', function (): void {
    expect(Schema::hasColumn('users', 'two_factor_confirmed_at'))->toBeFalse();

    $actor = usersActor();
    makeState($actor, 'active');
    $on = userNamed('Secure');
    makeState($on, 'active', ['mfa_enabled_at' => '2026-01-01 00:00:00', 'mfa_method' => 'totp']);
    userNamed('Open');

    $names = fn (string $query): array => listedNames($this->actingAs($actor)->getJson("/api/access/users?{$query}&sort=name")->assertOk());

    expect($names('filter[mfa]=on'))->toBe(['Secure'])
        ->and($names('filter[mfa]=off'))->toBe(['A', 'Open'])
        ->and($names('filter[mfa]=whatever'))->toBe(['A', 'Open', 'Secure']);

    $row = collect($this->actingAs($actor)->getJson('/api/access/users?search=Secure')->json('data'))->first();
    expect($row['mfa_enabled'])->toBeTrue();
});

it('filters mfa by the configured host column or the state column', function (): void {
    Schema::table('users', fn ($table) => $table->timestamp('two_factor_confirmed_at')->nullable());

    $actor = usersActor();
    $host = userNamed('Host');
    DB::table('users')->where('id', $host->id)->update(['two_factor_confirmed_at' => '2026-01-01 00:00:00']);
    makeState(userNamed('State'), 'active', ['mfa_enabled_at' => '2026-01-01 00:00:00']);
    userNamed('Neither');

    $names = fn (string $query): array => listedNames($this->actingAs($actor)->getJson("/api/access/users?{$query}&sort=name")->assertOk());

    expect($names('filter[mfa]=on'))->toBe(['Host', 'State'])
        ->and($names('filter[mfa]=off'))->toBe(['A', 'Neither']);

    $rows = collect($this->actingAs($actor)->getJson('/api/access/users?filter[mfa]=on')->json('data'))->pluck('mfa_enabled', 'name');
    expect($rows->all())->toBe(['Host' => true, 'State' => true]);

    config(['roles.users.mfa_enabled_column' => null]);

    expect($names('filter[mfa]=on'))->toBe(['State']);
});

it('ignores a configured host mfa column that does not exist', function (): void {
    config(['roles.users.mfa_enabled_column' => 'no_such_column']);
    $actor = usersActor();

    $this->actingAs($actor)->getJson('/api/access/users?filter[mfa]=off')->assertOk()->assertJsonPath('meta.pagination.total', 1);
});

it('sorts users who never logged in last in both directions', function (): void {
    $actor = usersActor();
    DB::table('users')->update(['created_at' => '2019-01-01 00:00:00']);
    makeState(userNamed('Old'), 'active', ['last_login_at' => '2026-05-01 00:00:00']);
    makeState(userNamed('New'), 'active', ['last_login_at' => '2026-05-03 00:00:00']);
    makeState(userNamed('Never'), 'active');
    userNamed('Stateless');

    $order = fn (string $sort): array => array_values(array_diff(listedNames($this->actingAs($actor)->getJson("/api/access/users?sort={$sort}")->assertOk()), ['A']));

    expect($order('-last_login'))->toBe(['New', 'Old', 'Never', 'Stateless'])
        ->and($order('last_login'))->toBe(['Old', 'New', 'Never', 'Stateless']);
});
