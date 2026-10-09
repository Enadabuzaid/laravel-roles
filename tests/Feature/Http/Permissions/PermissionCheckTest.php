<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Models\Permission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

afterEach(function (): void {
    Model::preventLazyLoading(false);
});

function checker()
{
    $user = makeUser('ck'.uniqid().'@example.test');
    $user->assignRole(makeRole('ckr'.uniqid(), ['access.permissions.view', 'access.users.view']));

    return $user;
}

it('reports owner access', function (): void {
    config(['roles.super_role' => 'owner']);
    $owner = makeOwner();
    makePermission('some.thing');

    $this->actingAs(checker())->getJson("/api/access/permissions/check?user={$owner->id}&permission=some.thing")->assertOk()
        ->assertJsonPath('data', ['allowed' => true, 'via' => 'owner', 'roles' => []]);
});

it('reports role access with the granting role names', function (): void {
    $user = makeUser('r@example.test');
    $user->assignRole(makeRole('ra', ['some.thing']), makeRole('rb', ['some.thing']), makeRole('rc'));

    $this->actingAs(checker())->getJson("/api/access/permissions/check?user={$user->id}&permission=some.thing")->assertOk()
        ->assertJsonPath('data.allowed', true)->assertJsonPath('data.via', 'role')->assertJsonPath('data.roles', ['ra', 'rb']);
});

it('reports direct access', function (): void {
    $user = makeUser('d@example.test');
    $user->givePermissionTo(makePermission('some.thing'));

    $this->actingAs(checker())->getJson("/api/access/permissions/check?user={$user->id}&permission=some.thing")->assertOk()
        ->assertJsonPath('data', ['allowed' => true, 'via' => 'direct', 'roles' => []]);
});

it('reports no access', function (): void {
    $user = makeUser('n@example.test');
    makePermission('some.thing');

    $this->actingAs(checker())->getJson("/api/access/permissions/check?user={$user->id}&permission=some.thing")->assertOk()
        ->assertJsonPath('data', ['allowed' => false, 'via' => null, 'roles' => []]);
});

it('requires users view as well as permissions view', function (): void {
    $user = makeUser('n@example.test');
    makePermission('some.thing');
    $viewer = makeUser('pv@example.test');
    $viewer->assignRole(makeRole('pv-only', ['access.permissions.view']));

    $this->actingAs($viewer)->getJson("/api/access/permissions/check?user={$user->id}&permission=some.thing")
        ->assertForbidden()->assertJson(['code' => 'forbidden']);
});

it('refuses permission names that do not exist instead of evaluating them as gate abilities', function (string $name): void {
    $user = makeUser('n@example.test');
    Gate::define('needs-argument', fn ($user, $post) => $post->id === 1);
    Gate::define('always', fn () => true);

    $this->actingAs(checker())->getJson('/api/access/permissions/check?user='.$user->id.'&permission='.urlencode($name))
        ->assertStatus(422)->assertJson(['code' => 'validation_failed'])->assertJsonValidationErrorFor('permission', 'errors');
})->with(['does.not.exist', 'needs-argument', 'always', 'viewPulse']);

it('does not evaluate a permission of another guard', function (): void {
    $user = makeUser('n@example.test');
    Permission::create(['name' => 'foreign.thing', 'guard_name' => 'api']);

    $this->actingAs(checker())->getJson("/api/access/permissions/check?user={$user->id}&permission=foreign.thing")->assertStatus(422);
});

it('404s an unknown user and validates the query', function (): void {
    makePermission('a.b');
    $this->actingAs(checker())->getJson('/api/access/permissions/check?user=999999&permission=a.b')->assertNotFound()->assertJsonPath('code', 'not_found');
    $this->actingAs(checker())->getJson('/api/access/permissions/check?permission=a.b')->assertStatus(422)->assertJsonValidationErrorFor('user', 'errors');
    $this->actingAs(checker())->getJson('/api/access/permissions/check?user=1')->assertStatus(422)->assertJsonValidationErrorFor('permission', 'errors');
    $this->actingAs(checker())->getJson('/api/access/permissions/check?user[]=1&permission[]=a')->assertStatus(422);
});

it('executes a constant number of queries', function (): void {
    Model::preventLazyLoading();
    $user = makeUser('q@example.test');
    $user->assignRole(makeRole('q1', ['some.thing']));
    $this->actingAs(checker());

    $run = function () use ($user): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson("/api/access/permissions/check?user={$user->id}&permission=some.thing")->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };
    $run();
    $small = $run();
    foreach (range(2, 12) as $i) {
        $user->assignRole(makeRole("q{$i}", ['some.thing', "x.{$i}"]));
    }

    $run();

    expect($run())->toBe($small);
});
