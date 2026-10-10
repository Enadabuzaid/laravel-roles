<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType;
use Enadstack\LaravelRoles\Guardrails\OwnerCounter;
use Enadstack\LaravelRoles\Guardrails\Rules\TargetAboveActorRule;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Support\Exceptions\AccessException;
use Enadstack\LaravelRoles\Users\Support\TargetStanding;
use Tests\Support\TestUser;

/** @return array<string, array<string, mixed>> */
function rowsByEmail(mixed $test, mixed $actor, string $query = ''): array
{
    return collect($test->actingAs($actor)->getJson('/api/access/users?per_page=100'.$query)->assertOk()->json('data'))->keyBy('email')->all();
}

it('reports self, owner and above locks with the matching abilities', function (): void {
    makeOwner();
    $actor = usersActor(['access.users.view', 'access.users.update', 'access.users.suspend', 'access.users.delete', 'access.roles.assign', 'reports.view']);
    $peer = makeUser('peer@example.test');
    $peer->assignRole(makeRole('peer', ['reports.view']));
    $above = makeUser('above@example.test');
    $above->assignRole(makeRole('above', ['reports.view', 'billing.view']));
    makeUser('none@example.test');

    $rows = rowsByEmail($this, $actor);

    expect($rows['actor@example.test'])->toMatchArray(['is_self' => true, 'is_owner' => false, 'locked' => true, 'lock_reason' => 'self'])
        ->and($rows['actor@example.test']['abilities'])->toBe(['view' => true, 'update' => true, 'suspend' => false, 'delete' => false, 'assign' => false, 'restore' => false, 'force_delete' => false, 'edit' => true, 'verify' => true])
        ->and($rows['owner@example.test'])->toMatchArray(['is_self' => false, 'is_owner' => true, 'locked' => true, 'lock_reason' => 'owner'])
        ->and($rows['owner@example.test']['abilities'])->toBe(['view' => true, 'update' => false, 'suspend' => false, 'delete' => false, 'assign' => false, 'restore' => false, 'force_delete' => false, 'edit' => false, 'verify' => false])
        ->and($rows['above@example.test'])->toMatchArray(['is_owner' => false, 'locked' => true, 'lock_reason' => 'above'])
        ->and($rows['above@example.test']['abilities'])->toBe(['view' => true, 'update' => false, 'suspend' => false, 'delete' => false, 'assign' => false, 'restore' => false, 'force_delete' => false, 'edit' => false, 'verify' => false])
        ->and($rows['peer@example.test'])->toMatchArray(['locked' => false, 'lock_reason' => null])
        ->and($rows['peer@example.test']['abilities'])->toBe(['view' => true, 'update' => true, 'suspend' => true, 'delete' => true, 'assign' => true, 'restore' => false, 'force_delete' => false, 'edit' => true, 'verify' => true])
        ->and($rows['none@example.test']['lock_reason'])->toBeNull();

    $viewerOnly = usersActor(email: 'viewer@example.test');
    expect(rowsByEmail($this, $viewerOnly)['peer@example.test']['abilities'])->toBe(['view' => true, 'update' => false, 'suspend' => false, 'delete' => false, 'assign' => false, 'restore' => false, 'force_delete' => false, 'edit' => false, 'verify' => false]);
});

it('lets an owner act on other owners and marks only itself as locked', function (): void {
    $owner = makeOwner();
    makeOwner('second@example.test');
    makeUser('plain@example.test');

    $rows = rowsByEmail($this, $owner);

    expect($rows['owner@example.test']['lock_reason'])->toBe('self')
        ->and($rows['second@example.test'])->toMatchArray(['is_owner' => true, 'locked' => false, 'lock_reason' => null])
        ->and($rows['second@example.test']['abilities'])->toBe(['view' => true, 'update' => true, 'suspend' => true, 'delete' => true, 'assign' => true, 'restore' => false, 'force_delete' => false, 'edit' => true, 'verify' => true])
        ->and($rows['plain@example.test']['lock_reason'])->toBeNull();
});

it('ignores permissions of trashed roles when judging a target', function (): void {
    makeOwner();
    $actor = usersActor(['access.users.view', 'reports.view']);
    $target = makeUser('target@example.test');
    $trashed = makeRole('old', ['billing.view']);
    $target->assignRole($trashed);
    $target->assignRole(makeRole('fine', ['reports.view']));
    $trashed->delete();

    expect(rowsByEmail($this, $actor)['target@example.test']['lock_reason'])->toBeNull();
});

it('agrees with an independent oracle and the guardrail for every actor and target', function (string $actorName): void {
    makeOwner();
    $apiRole = Role::create(['name' => 'api-role', 'guard_name' => 'api']);
    $apiRole->givePermissionTo(Permission::findOrCreate('secret.api', 'api'));
    $trashed = makeRole('old', ['billing.view']);

    $users = [
        'owner' => makeOwner('owner2@example.test'),
        'low' => tap(makeUser('low@example.test'), fn ($u) => $u->assignRole(makeRole('low', ['reports.view']))),
        'high' => tap(makeUser('high@example.test'), fn ($u) => $u->assignRole(makeRole('high', ['reports.view', 'billing.view']))),
        'direct' => tap(makeUser('direct@example.test'), fn ($u) => $u->givePermissionTo(makePermission('reports.view'))),
        'direct-above' => tap(makeUser('directabove@example.test'), fn ($u) => $u->givePermissionTo(makePermission('billing.view'))),
        'other-guard' => tap(makeUser('api@example.test'), fn ($u) => $u->roles()->attach($apiRole)),
        'trashed' => tap(makeUser('trashed@example.test'), function ($u) use ($trashed) {
            $u->assignRole($trashed);
            $trashed->delete();
        }),
        'none' => makeUser('none@example.test'),
        'first-owner' => TestUserFirstOwner(),
    ];
    $users = array_map(fn ($u) => $u->fresh(), $users);
    $actor = $users[$actorName];
    $reasons = app(TargetStanding::class)->evaluate($actor, $users);
    $rule = app(TargetAboveActorRule::class);

    $names = fn ($u): array => $u->getAllPermissions()->where('guard_name', 'web')->pluck('name')->all();

    foreach ($users as $targetName => $target) {
        if ($actor->is($target)) {
            expect($reasons[$target->getKey()]['reason'])->toBe('self');

            continue;
        }

        // Independent oracle: the Spatie relation based subset comparison.
        $expected = ! $actor->hasRole('owner') && ($target->hasRole('owner') || array_diff($names($target), $names($actor)) !== []);

        $refused = false;
        try {
            $rule->check(new Operation(OperationType::SuspendUser, actor: $actor, target: $target));
        } catch (AccessException) {
            $refused = true;
        }

        $reason = $reasons[$target->getKey()]['reason'];

        expect($refused)->toBe($expected, "rule {$actorName} -> {$targetName}")
            ->and(in_array($reason, ['owner', 'above'], true))->toBe($expected, "standing {$actorName} -> {$targetName} ({$reason})")
            ->and(app(OwnerCounter::class)->isOwner($target))->toBe($target->hasRole('owner'));
    }
})->with(['owner', 'low', 'high', 'direct', 'direct-above', 'other-guard', 'trashed', 'none', 'first-owner']);

function TestUserFirstOwner(): TestUser
{
    return TestUser::where('email', 'owner@example.test')->firstOrFail();
}
