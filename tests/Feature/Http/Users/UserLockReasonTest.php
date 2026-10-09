<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType;
use Enadstack\LaravelRoles\Guardrails\Rules\TargetAboveActorRule;
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
        ->and($rows['actor@example.test']['abilities'])->toBe(['view' => true, 'update' => true, 'suspend' => false, 'delete' => false, 'assign' => false])
        ->and($rows['owner@example.test'])->toMatchArray(['is_self' => false, 'is_owner' => true, 'locked' => true, 'lock_reason' => 'owner'])
        ->and($rows['owner@example.test']['abilities'])->toBe(['view' => true, 'update' => false, 'suspend' => false, 'delete' => false, 'assign' => false])
        ->and($rows['above@example.test'])->toMatchArray(['is_owner' => false, 'locked' => true, 'lock_reason' => 'above'])
        ->and($rows['above@example.test']['abilities'])->toBe(['view' => true, 'update' => false, 'suspend' => false, 'delete' => false, 'assign' => true])
        ->and($rows['peer@example.test'])->toMatchArray(['locked' => false, 'lock_reason' => null])
        ->and($rows['peer@example.test']['abilities'])->toBe(['view' => true, 'update' => true, 'suspend' => true, 'delete' => true, 'assign' => true])
        ->and($rows['none@example.test']['lock_reason'])->toBeNull();

    $viewerOnly = usersActor(email: 'viewer@example.test');
    expect(rowsByEmail($this, $viewerOnly)['peer@example.test']['abilities'])->toBe(['view' => true, 'update' => false, 'suspend' => false, 'delete' => false, 'assign' => false]);
});

it('lets an owner act on other owners and marks only itself as locked', function (): void {
    $owner = makeOwner();
    makeOwner('second@example.test');
    makeUser('plain@example.test');

    $rows = rowsByEmail($this, $owner);

    expect($rows['owner@example.test']['lock_reason'])->toBe('self')
        ->and($rows['second@example.test'])->toMatchArray(['is_owner' => true, 'locked' => false, 'lock_reason' => null])
        ->and($rows['second@example.test']['abilities'])->toBe(['view' => true, 'update' => true, 'suspend' => true, 'delete' => true, 'assign' => true])
        ->and($rows['plain@example.test']['lock_reason'])->toBeNull();
});

it('ignores trashed roles and other guards when judging a target', function (): void {
    makeOwner();
    $actor = usersActor(['access.users.view', 'reports.view']);
    $target = makeUser('target@example.test');
    $trashed = makeRole('old', ['billing.view']);
    $target->assignRole($trashed);
    $target->assignRole(makeRole('fine', ['reports.view']));
    $trashed->delete();

    expect(rowsByEmail($this, $actor)['target@example.test']['lock_reason'])->toBeNull();
});

it('agrees with the target-above-actor guardrail for every actor and target', function (string $actorName): void {
    makeOwner();
    $users = [
        'owner' => makeOwner('owner2@example.test'),
        'low' => tap(makeUser('low@example.test'), fn ($u) => $u->assignRole(makeRole('low', ['reports.view']))),
        'high' => tap(makeUser('high@example.test'), fn ($u) => $u->assignRole(makeRole('high', ['reports.view', 'billing.view']))),
        'direct' => tap(makeUser('direct@example.test'), fn ($u) => $u->givePermissionTo(makePermission('reports.view'))),
        'none' => makeUser('none@example.test'),
        'first-owner' => TestUserFirstOwner(),
    ];
    $actor = $users[$actorName];
    $reasons = app(TargetStanding::class)->evaluate($actor, $users);
    $rule = app(TargetAboveActorRule::class);

    foreach ($users as $targetName => $target) {
        $reason = $reasons[$target->getKey()]['reason'];

        if ($reason === 'self') {
            expect($actor->is($target))->toBeTrue();

            continue;
        }

        $refused = false;
        try {
            $rule->check(new Operation(OperationType::SuspendUser, actor: $actor, target: $target));
        } catch (AccessException) {
            $refused = true;
        }

        expect($refused)->toBe(in_array($reason, ['owner', 'above'], true), "{$actorName} -> {$targetName} ({$reason})");
    }
})->with(['owner', 'low', 'high', 'direct', 'none', 'first-owner']);

function TestUserFirstOwner(): TestUser
{
    return TestUser::where('email', 'owner@example.test')->firstOrFail();
}
