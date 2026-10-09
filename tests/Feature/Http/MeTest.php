<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Models\Role;
use Illuminate\Support\Facades\DB;

it('describes the owner', function (): void {
    $owner = makeOwner();
    $ownerRole = Role::where('name', 'owner')->first();

    $data = $this->actingAs($owner)->getJson('/api/access/me')->assertOk()->json('data');

    expect($data['is_owner'])->toBeTrue()->and($data['role_ids'])->toBe([$ownerRole->id])
        ->and($data['permissions'])->toContain('access.roles.view', 'access.permissions.sync')
        ->and($data['abilities']['roles'])->toBe(['view' => true, 'create' => true, 'update' => true, 'delete' => true, 'assign' => true])
        ->and($data['abilities']['permissions'])->toBe(['view' => true, 'sync' => true])
        ->and($data['abilities']['matrix'])->toBe(['view' => true, 'update' => true]);
});

it('describes a limited user', function (): void {
    $user = makeUser('limited@example.test');
    $role = makeRole('limited', ['access.roles.view', 'access.permissions.view', 'reports.view']);
    $user->assignRole($role);

    $data = $this->actingAs($user)->getJson('/api/access/me')->assertOk()->json('data');

    expect($data['is_owner'])->toBeFalse()->and($data['role_ids'])->toBe([$role->id])
        ->and($data['permissions'])->toEqualCanonicalizing(['access.roles.view', 'access.permissions.view'])
        ->and($data['abilities']['roles'])->toBe(['view' => true, 'create' => false, 'update' => false, 'delete' => false, 'assign' => false])
        ->and($data['abilities']['permissions'])->toBe(['view' => true, 'sync' => false])
        ->and($data['abilities']['matrix'])->toBe(['view' => true, 'update' => false]);
});

it('answers 403 with the envelope for a user without any access permission', function (): void {
    $user = makeUser('plain@example.test');
    $user->assignRole(makeRole('reports-only', ['reports.view']));

    $this->actingAs($user)->getJson('/api/access/me')->assertForbidden()->assertJson(['success' => false, 'code' => 'forbidden']);
    $this->actingAs(makeUser('nobody@example.test'))->getJson('/api/access/me')->assertForbidden()->assertJson(['code' => 'forbidden']);
});

it('answers 401 with the envelope when unauthenticated', function (): void {
    $this->getJson('/api/access/me')->assertUnauthorized()->assertJson(['success' => false, 'code' => 'unauthenticated']);
});

it('uses a constant number of queries', function (): void {
    $user = makeUser('q@example.test');
    $user->assignRole(makeRole('q-role', ['access.roles.view']));
    $count = function () use ($user): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user->fresh())->getJson('/api/access/me')->assertOk();
        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $total;
    };

    $small = $count();
    foreach (range(1, 15) as $i) {
        $user->assignRole(makeRole("extra{$i}", ["extra.{$i}"]));
    }

    expect($count())->toBe($small);
});
