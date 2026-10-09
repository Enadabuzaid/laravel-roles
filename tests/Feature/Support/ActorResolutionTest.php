<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Roles\Actions\AssignRoleToUser;
use Enadstack\LaravelRoles\Roles\Actions\RemoveRoleFromUser;
use Enadstack\LaravelRoles\Roles\Actions\SyncUserRoles;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Support\Exceptions\AccessException;

/** Simulates an HTTP request: the context is not running in console. */
function useHttpContext(): void
{
    app()->instance(ActorContext::class, new class extends ActorContext
    {
        public function runningInConsole(): bool
        {
            return false;
        }
    });
}

beforeEach(function () {
    config(['auth.guards.api' => ['driver' => 'session', 'provider' => 'users']]);
});

it('resolves the actor from the default guard the middleware selected', function () {
    $user = makeUser();
    $this->actingAs($user, 'api');

    expect(app(ActorContext::class)->actor()?->is($user))->toBeTrue();
});

it('blocks a non-web-guard user from changing their own role', function () {
    makeOwner();
    $admin = makeUser('admin@example.test');
    makeRole('manager');
    $this->actingAs($admin, 'api');

    expectViolation(fn () => $admin->assignAccessRole('manager'), 'guardrail.self_role_change');
    expect($admin->fresh()->getRoleNames()->all())->toBe([]);
});

it('evaluates escalation for a non-web-guard actor', function () {
    $actor = makeUser('actor@example.test');
    makeRole('support', ['tickets.view'])->users()->attach($actor);
    $target = makeUser('target@example.test');
    makeRole('finance', ['tickets.view', 'invoices.view']);
    $this->actingAs($actor->fresh(), 'api');

    expectViolation(fn () => $target->assignAccessRole('finance'), 'guardrail.escalation');
});

it('audits a non-web-guard user as the actor', function () {
    makeOwner();
    $actor = makeUser('actor@example.test');
    $target = makeUser('target@example.test');
    makeRole('viewer');
    makeRole('support', [])->users()->attach($actor);
    $this->actingAs($actor->fresh(), 'api');
    ActionLog::query()->delete();

    $target->assignAccessRole('viewer');

    expect(ActionLog::query()->where('event', 'roles.assigned')->sole()->actor_id)->toBe($actor->id);
});

it('refuses unauthenticated guarded writes outside console and changes nothing', function (string $operation) {
    makeOwner();
    $target = makeUser('t@example.test');
    makeRole('viewer');
    ActionLog::query()->delete();
    useHttpContext();

    $write = match ($operation) {
        'assign' => fn () => app(AssignRoleToUser::class)->handle($target, 'viewer'),
        'remove' => fn () => app(RemoveRoleFromUser::class)->handle($target, 'viewer'),
        'sync' => fn () => app(SyncUserRoles::class)->handle($target, ['viewer']),
    };

    expectViolation($write, 'guardrail.actor_required');

    expect($target->fresh()->getRoleNames()->all())->toBe([])
        ->and(ActionLog::count())->toBe(0);
})->with(['assign', 'remove', 'sync']);

it('reports actor_required with status 403', function () {
    useHttpContext();

    try {
        app(AssignRoleToUser::class)->handle(makeUser(), makeRole('viewer'));
        $this->fail('expected refusal');
    } catch (AccessException $e) {
        expect($e->status)->toBe(403)->and($e->errorCode)->toBe('guardrail.actor_required');
    }
});

it('treats console and explicit runAs(null) as system', function () {
    makeRole('viewer');
    $a = makeUser('a@example.test');
    $b = makeUser('b@example.test');

    $a->assignAccessRole('viewer');
    expect($a->fresh()->hasRole('viewer'))->toBeTrue()->and(app(ActorContext::class)->isSystem())->toBeTrue();

    useHttpContext();
    expect(app(ActorContext::class)->isSystem())->toBeFalse();
    app(ActorContext::class)->runAs(null, fn () => $b->assignAccessRole('viewer'));

    expect($b->fresh()->hasRole('viewer'))->toBeTrue();
});

it('lets an authenticated user act outside console', function () {
    makeOwner();
    $actor = makeUser('actor@example.test');
    makeRole('viewer');
    makeRole('support')->users()->attach($actor);
    $target = makeUser('t@example.test');
    $this->actingAs($actor->fresh());
    useHttpContext();

    $target->assignAccessRole('viewer');

    expect($target->fresh()->hasRole('viewer'))->toBeTrue();
});
