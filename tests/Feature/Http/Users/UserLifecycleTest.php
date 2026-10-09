<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Users\Actions\SuspendUser;
use Enadstack\LaravelRoles\Users\Models\AccessInvitation;
use Enadstack\LaravelRoles\Users\Models\SavedView;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Support\TestUser;

function lifecycleActor(array $extra = []): TestUser
{
    return usersActor(['access.users.view', 'access.users.suspend', 'access.users.update', 'access.users.delete', ...$extra], 'admin@example.test');
}

function statusOfUser(TestUser $user): ?string
{
    return UserAccessState::where('user_id', $user->id)->first()?->status->value;
}

it('suspends an active user with one warning audit entry and the user in the envelope', function (): void {
    $actor = lifecycleActor();
    $target = makeUser('t@example.test');
    ActionLog::query()->delete();

    $response = $this->actingAs($actor)->postJson("/api/access/users/{$target->id}/suspend", ['reason' => 'Policy breach'])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.status', 'suspended')
        ->assertJsonPath('data.status_reason', 'Policy breach');

    expect($response->json('meta'))->toBe([])->and(statusOfUser($target))->toBe('suspended');
    $log = ActionLog::sole();
    expect($log->event)->toBe('users.suspended')->and($log->module)->toBe('users')->and($log->severity)->toBe('warning')
        ->and($log->actor_id)->toBe($actor->id)->and($log->subject_id)->toBe($target->id)
        ->and($log->meta)->toEqual(['reason' => 'Policy breach'])
        ->and($log->old_values)->toEqual(['status' => 'active'])->and($log->new_values)->toEqual(['status' => 'suspended']);
});

it('suspending an owner is critical', function (): void {
    $owner = makeOwner();
    $second = makeOwner('second@example.test');
    ActionLog::query()->delete();

    $this->actingAs($owner)->postJson("/api/access/users/{$second->id}/suspend", ['reason' => 'rotation'])->assertOk();

    expect(ActionLog::sole()->severity)->toBe('critical');
});

it('treats a repeated suspend as a no-op without writes or audit', function (): void {
    $actor = lifecycleActor();
    $target = makeUser('t@example.test');
    makeState($target, 'suspended', ['status_reason' => 'first']);
    ActionLog::query()->delete();

    $this->actingAs($actor)->postJson("/api/access/users/{$target->id}/suspend", ['reason' => 'second'])->assertOk()->assertJsonPath('data.status', 'suspended');

    expect(UserAccessState::where('user_id', $target->id)->value('status_reason'))->toBe('first')->and(ActionLog::count())->toBe(0);
});

it('reactivates suspended and deactivated users and clears the reason', function (string $from): void {
    $actor = lifecycleActor();
    $target = makeUser('t@example.test');
    makeState($target, $from, ['status_reason' => 'why']);
    ActionLog::query()->delete();

    $this->actingAs($actor)->postJson("/api/access/users/{$target->id}/reactivate")->assertOk()->assertJsonPath('data.status', 'active')->assertJsonPath('data.status_reason', null);

    $state = UserAccessState::where('user_id', $target->id)->first();
    expect($state->status->value)->toBe('active')->and($state->activated_at)->not->toBeNull();
    $log = ActionLog::sole();
    expect($log->event)->toBe('users.reactivated')->and($log->severity)->toBe('info')->and($log->old_values)->toEqual(['status' => $from]);
})->with(['suspended', 'deactivated']);

it('deactivates from active and suspended, no-ops when already deactivated', function (): void {
    $actor = lifecycleActor();
    $a = makeUser('a@example.test');
    $b = makeUser('b@example.test');
    makeState($b, 'suspended');
    ActionLog::query()->delete();

    $this->actingAs($actor)->postJson("/api/access/users/{$a->id}/deactivate", ['reason' => 'left'])->assertOk()->assertJsonPath('data.status', 'deactivated');
    $this->actingAs($actor)->postJson("/api/access/users/{$b->id}/deactivate", ['reason' => 'left'])->assertOk();
    expect(ActionLog::where('event', 'users.deactivated')->count())->toBe(2)->and(ActionLog::first()->severity)->toBe('warning');

    ActionLog::query()->delete();
    $this->actingAs($actor)->postJson("/api/access/users/{$a->id}/deactivate", ['reason' => 'again'])->assertOk();
    expect(ActionLog::count())->toBe(0);
});

it('answers 409 user.invalid_transition with the current status for illegal transitions', function (string $uri, string $status, array $body = []): void {
    $actor = lifecycleActor();
    $target = makeUser('t@example.test');
    if ($status !== 'active') {
        makeState($target, $status);
    }
    ActionLog::query()->delete();

    $this->actingAs($actor)->postJson("/api/access/users/{$target->id}/{$uri}", $body)->assertStatus(409)
        ->assertJson(['success' => false, 'code' => 'user.invalid_transition', 'errors' => ['status' => [$status]]]);

    expect(ActionLog::count())->toBe(0)->and(statusOfUser($target))->toBe($status === 'active' ? null : $status);
})->with([
    'reactivate active' => ['reactivate', 'active'],
    'reactivate locked' => ['reactivate', 'locked'],
    'suspend deactivated' => ['suspend', 'deactivated', ['reason' => 'x']],
    'suspend invited' => ['suspend', 'invited', ['reason' => 'x']],
    'deactivate invited' => ['deactivate', 'invited', ['reason' => 'x']],
    'unlock active' => ['unlock', 'active'],
    'unlock suspended' => ['unlock', 'suspended'],
]);

it('unlocks a locked user and resets the counters', function (): void {
    $actor = lifecycleActor();
    $target = makeUser('t@example.test');
    makeState($target, 'locked', ['locked_until' => now()->addHour(), 'failed_login_count' => 5]);
    ActionLog::query()->delete();

    $this->actingAs($actor)->postJson("/api/access/users/{$target->id}/unlock")->assertOk()->assertJsonPath('data.status', 'active');

    $state = UserAccessState::where('user_id', $target->id)->first();
    expect($state->locked_until)->toBeNull()->and($state->failed_login_count)->toBe(0);
    $log = ActionLog::sole();
    expect($log->event)->toBe('users.unlocked')->and($log->severity)->toBe('info');
});

it('requires a reason of at most 500 characters to suspend or deactivate', function (string $uri): void {
    $actor = lifecycleActor();
    $target = makeUser('t@example.test');

    $this->actingAs($actor)->postJson("/api/access/users/{$target->id}/{$uri}")->assertStatus(422)->assertJsonPath('code', 'validation_failed')->assertJsonStructure(['errors' => ['reason']]);
    $this->actingAs($actor)->postJson("/api/access/users/{$target->id}/{$uri}", ['reason' => str_repeat('a', 501)])->assertStatus(422);
    expect(UserAccessState::count())->toBe(0);
})->with(['suspend', 'deactivate']);

it('sends the reset link, audits it and never exposes a token', function (): void {
    Notification::fake();
    $actor = lifecycleActor();
    $target = makeUser('t@example.test');
    $target->forceFill(['password_changed_at' => null]);
    ActionLog::query()->delete();

    $response = $this->actingAs($actor)->postJson("/api/access/users/{$target->id}/force-password-reset")->assertOk();

    expect($response->json('meta.link_sent'))->toBeBool()->and($response->getContent())->not->toContain('token');
    $log = ActionLog::sole();
    expect($log->event)->toBe('users.password_reset_forced')->and($log->severity)->toBe('info')->and($log->subject_id)->toBe($target->id);
    expect(UserAccessState::count())->toBe(0);
});

it('reports link_sent false when mail fails without undoing the audit entry', function (): void {
    $actor = lifecycleActor();
    $target = makeUser('t@example.test');
    Password::shouldReceive('broker')->andThrow(new RuntimeException('smtp down'));
    ActionLog::query()->delete();

    $this->actingAs($actor)->postJson("/api/access/users/{$target->id}/force-password-reset")->assertOk()->assertJsonPath('meta.link_sent', false);

    expect(ActionLog::where('event', 'users.password_reset_forced')->count())->toBe(1);
});

it('deletes a user, cleaning access rows and keeping invitations', function (): void {
    $owner = makeOwner();
    $actor = $owner;
    $target = makeUser('t@example.test');
    $target->assignRole(makeRole('viewer', ['reports.view']));
    $target->givePermissionTo('reports.view');
    $other = makeUser('o@example.test');
    $other->assignRole('viewer');
    makeState($target, 'active', ['invited_by' => null]);
    makeState($other, 'active', ['invited_by' => $target->id]);
    SavedView::create(['user_id' => $target->id, 'page' => 'users', 'name' => 'v', 'filters' => []]);
    SavedView::create(['user_id' => $other->id, 'page' => 'users', 'name' => 'v', 'filters' => []]);
    $invitation = AccessInvitation::create(['email' => 'new@example.test', 'token_hash' => hash('sha256', 'x'), 'invited_by' => $target->id, 'expires_at' => now()->addDay()]);
    ActionLog::query()->delete();

    $this->actingAs($actor)->deleteJson("/api/access/users/{$target->id}", ['confirmation' => ' T@Example.test '])->assertNoContent();

    expect(TestUser::find($target->id))->toBeNull()
        ->and(DB::table('model_has_roles')->where('model_id', $target->id)->count())->toBe(0)
        ->and(DB::table('model_has_permissions')->where('model_id', $target->id)->count())->toBe(0)
        ->and(DB::table('model_has_roles')->where('model_id', $other->id)->count())->toBe(1)
        ->and(UserAccessState::where('user_id', $target->id)->exists())->toBeFalse()
        ->and(UserAccessState::where('user_id', $other->id)->value('invited_by'))->toBeNull()
        ->and(SavedView::where('user_id', $target->id)->count())->toBe(0)
        ->and(SavedView::where('user_id', $other->id)->count())->toBe(1)
        ->and($invitation->fresh()->invited_by)->toBeNull();
    $log = ActionLog::sole();
    expect($log->event)->toBe('users.deleted')->and($log->severity)->toBe('critical')->and($log->subject_id)->toBeNull()
        ->and($log->old_values)->toEqual(['id' => $target->id, 'name' => 'A', 'email' => 't@example.test'])
        ->and($owner->exists)->toBeTrue();
});

it('does not touch role rows of another model type sharing the id when deleting', function (): void {
    $actor = lifecycleActor();
    $target = makeUser('t@example.test');
    $role = makeRole('viewer');
    DB::table('model_has_roles')->insert(['role_id' => $role->id, 'model_type' => 'App\\Other\\Admin', 'model_id' => $target->id]);

    $this->actingAs($actor)->deleteJson("/api/access/users/{$target->id}", ['confirmation' => 't@example.test'])->assertNoContent();

    expect(DB::table('model_has_roles')->where('model_type', 'App\\Other\\Admin')->count())->toBe(1);
});

it('refuses with stable guardrail codes, unchanged state and no audit row', function (string $case): void {
    ActionLog::query()->delete();

    [$actor, $target, $method, $uri, $body, $code] = match ($case) {
        'self suspend' => (function () {
            $a = lifecycleActor();

            return [$a, $a, 'postJson', 'suspend', ['reason' => 'x'], 'guardrail.self_destructive'];
        })(),
        'self deactivate' => (function () {
            $a = lifecycleActor();

            return [$a, $a, 'postJson', 'deactivate', ['reason' => 'x'], 'guardrail.self_destructive'];
        })(),
        'self delete' => (function () {
            $a = lifecycleActor();

            return [$a, $a, 'deleteJson', '', ['confirmation' => 'admin@example.test'], 'guardrail.self_destructive'];
        })(),
        'last owner suspend' => (function () {
            $a = makeOwner();
            $o = makeOwner('o2@example.test');
            config(['roles.guardrails.min_owners' => 2]);

            return [$a, $o, 'postJson', 'suspend', ['reason' => 'x'], 'guardrail.last_owner'];
        })(),
        'non-owner vs owner' => (function () {
            makeOwner();
            $o = makeOwner('o2@example.test');

            return [lifecycleActor(), $o, 'postJson', 'suspend', ['reason' => 'x'], 'guardrail.target_above_actor'];
        })(),
        'non-owner unlock owner' => (function () {
            makeOwner();
            $o = makeOwner('o2@example.test');

            return [lifecycleActor(), $o, 'postJson', 'unlock', [], 'guardrail.target_above_actor'];
        })(),
        'non-owner force reset owner' => (function () {
            makeOwner();
            $o = makeOwner('o2@example.test');

            return [lifecycleActor(), $o, 'postJson', 'force-password-reset', [], 'guardrail.target_above_actor'];
        })(),
        'non-owner vs more permissions' => (function () {
            $t = makeUser('rich@example.test');
            $t->assignRole(makeRole('rich', ['payroll.run']));

            return [lifecycleActor(), $t, 'postJson', 'deactivate', ['reason' => 'x'], 'guardrail.target_above_actor'];
        })(),
        'reactivate above' => (function () {
            $o = makeOwner();
            makeOwner('o2@example.test');
            makeState($o, 'suspended');

            return [lifecycleActor(), $o, 'postJson', 'reactivate', [], 'guardrail.target_above_actor'];
        })(),
        'wrong confirmation' => (function () {
            $t = makeUser('t@example.test');

            return [lifecycleActor(), $t, 'deleteJson', '', ['confirmation' => 'nope'], 'guardrail.confirmation_mismatch'];
        })(),
        'missing confirmation' => (function () {
            $t = makeUser('t@example.test');

            return [lifecycleActor(), $t, 'deleteJson', '', [], 'guardrail.confirmation_mismatch'];
        })(),
    };

    $before = [statusOfUser($target), UserAccessState::count()];
    ActionLog::query()->delete();
    $url = "/api/access/users/{$target->id}".($uri === '' ? '' : "/{$uri}");

    $this->actingAs($actor)->{$method}($url, $body)->assertForbidden()->assertJson(['success' => false, 'code' => $code]);

    expect([statusOfUser($target), UserAccessState::count()])->toBe($before)->and(TestUser::find($target->id))->not->toBeNull()->and(ActionLog::count())->toBe(0);
})->with(['self suspend', 'self deactivate', 'self delete', 'last owner suspend', 'non-owner vs owner', 'non-owner unlock owner', 'non-owner force reset owner', 'non-owner vs more permissions', 'reactivate above', 'wrong confirmation', 'missing confirmation']);

it('counts only active owners for the last-owner guardrail', function (): void {
    config(['roles.guardrails.min_owners' => 2]);
    $actor = makeOwner('a@example.test');
    $target = makeOwner('b@example.test');
    makeState(makeOwner('c@example.test'), 'suspended');
    ActionLog::query()->delete();

    $this->actingAs($actor)->deleteJson("/api/access/users/{$target->id}", ['confirmation' => 'b@example.test'])
        ->assertForbidden()->assertJson(['code' => 'guardrail.last_owner']);
    $this->actingAs($actor)->postJson("/api/access/users/{$target->id}/deactivate", ['reason' => 'x'])
        ->assertForbidden()->assertJson(['code' => 'guardrail.last_owner']);

    expect(TestUser::find($target->id))->not->toBeNull()->and(ActionLog::count())->toBe(0);
});

it('allows removing an owner while another active owner remains', function (): void {
    $actor = makeOwner('a@example.test');
    $target = makeOwner('b@example.test');

    $this->actingAs($actor)->deleteJson("/api/access/users/{$target->id}", ['confirmation' => 'b@example.test'])->assertNoContent();

    $log = ActionLog::where('event', 'users.deleted')->sole();
    expect($log->meta)->toEqual(['was_owner' => true]);
});

it('answers 404 for an unknown user and rejects malformed ids', function (): void {
    $actor = lifecycleActor();

    $this->actingAs($actor)->postJson('/api/access/users/99999/reactivate')->assertNotFound()->assertJson(['success' => false, 'code' => 'not_found']);
    $this->actingAs($actor)->deleteJson('/api/access/users/99999', ['confirmation' => 'x'])->assertNotFound();
    $this->actingAs($actor)->postJson('/api/access/users/abc/suspend', ['reason' => 'x'])->assertNotFound();
});

it('answers 401 unauthenticated and 403 forbidden in the envelope', function (string $method, string $uri, string $permission): void {
    $target = makeUser('t@example.test');
    $url = "/api/access/users/{$target->id}{$uri}";

    $this->{$method}($url, [])->assertUnauthorized();

    $viewer = usersActor(['access.users.view']);
    $this->actingAs($viewer)->{$method}($url, ['reason' => 'x'])->assertForbidden()->assertJson(['success' => false, 'code' => 'forbidden']);

    $others = usersActor(array_values(array_diff(['access.users.suspend', 'access.users.update', 'access.users.delete'], [$permission])), 'others@example.test');
    $this->actingAs($others)->{$method}($url, ['reason' => 'x'])->assertForbidden();
    expect(UserAccessState::count())->toBe(0)->and(TestUser::find($target->id))->not->toBeNull();
})->with([
    ['postJson', '/suspend', 'access.users.suspend'],
    ['postJson', '/reactivate', 'access.users.suspend'],
    ['postJson', '/deactivate', 'access.users.suspend'],
    ['postJson', '/unlock', 'access.users.update'],
    ['postJson', '/force-password-reset', 'access.users.update'],
    ['deleteJson', '', 'access.users.delete'],
]);

it('works for the console system actor', function (): void {
    $target = makeUser('t@example.test');

    expect(app(ActorContext::class)->runAs(null, fn () => app(SuspendUser::class)->handle($target, 'maintenance')))->toBeTrue();
    expect(statusOfUser($target))->toBe('suspended');
    $log = ActionLog::sole();
    expect($log->actor_id)->toBeNull();
});
