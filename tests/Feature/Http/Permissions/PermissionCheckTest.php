<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

afterEach(function (): void {
    Model::preventLazyLoading(false);
});

function checker()
{
    $user = makeUser('ck'.uniqid().'@example.test');
    $user->assignRole(makeRole('ckr'.uniqid(), ['access.permissions.view']));

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

it('reports no access, including unknown permissions', function (): void {
    $user = makeUser('n@example.test');
    makePermission('some.thing');

    foreach (['some.thing', 'does.not.exist'] as $name) {
        $this->actingAs(checker())->getJson("/api/access/permissions/check?user={$user->id}&permission={$name}")->assertOk()
            ->assertJsonPath('data', ['allowed' => false, 'via' => null, 'roles' => []]);
    }
});

it('404s an unknown user and validates the query', function (): void {
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
