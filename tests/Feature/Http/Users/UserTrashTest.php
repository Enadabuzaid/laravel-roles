<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Guardrails\OwnerCounter;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Support\Exceptions\AccessException;
use Enadstack\LaravelRoles\Users\Actions\DeleteUser;
use Enadstack\LaravelRoles\Users\Enums\UserStatus;
use Enadstack\LaravelRoles\Users\Models\AccessInvitation;
use Enadstack\LaravelRoles\Users\Models\SavedView;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\Support\SoftTestUser;
use Tests\Support\TestUser;

beforeEach(fn () => useHostUserModel(SoftTestUser::class));

function trashActor(array $extra = []): TestUser
{
    return usersActor(['access.users.view', 'access.users.suspend', 'access.users.update', 'access.users.delete', ...$extra], 'admin@example.test');
}

function trashUser(TestUser $user): void
{
    $user->delete();
}

it('moves a user to the trash keeping roles and state, ending sessions with one audit entry', function (): void {
    createSessionsTable();
    config(['session.driver' => 'database']);
    $actor = trashActor(['reports.view']);
    $target = makeUser('t@example.test');
    $target->assignRole(makeRole('viewer', ['reports.view']));
    makeState($target, 'suspended', ['status_reason' => 'why']);
    insertSession('s1', $target->id);
    ActionLog::query()->delete();

    $this->actingAs($actor)->deleteJson("/api/access/users/{$target->id}", ['confirmation' => 't@example.test'])->assertNoContent();

    expect(SoftTestUser::find($target->id))->toBeNull()
        ->and(SoftTestUser::withTrashed()->find($target->id)->trashed())->toBeTrue()
        ->and(DB::table('model_has_roles')->where('model_id', $target->id)->count())->toBe(1)
        ->and(UserAccessState::where('user_id', $target->id)->value('status'))->toBe(UserStatus::Suspended)
        ->and(DB::table('sessions')->where('user_id', $target->id)->count())->toBe(0);
    $log = ActionLog::sole();
    expect($log->event)->toBe('users.trashed')->and($log->severity)->toBe('warning')->and($log->subject_id)->toBe($target->id)
        ->and($log->meta)->toEqual(['was_owner' => false]);
});

it('audits trashing an owner as critical', function (): void {
    $owner = makeOwner();
    $second = makeOwner('second@example.test');
    ActionLog::query()->delete();

    $this->actingAs($owner)->deleteJson("/api/access/users/{$second->id}", ['confirmation' => 'second@example.test'])->assertNoContent();

    $log = ActionLog::sole();
    expect($log->event)->toBe('users.trashed')->and($log->severity)->toBe('critical')->and($log->meta)->toEqual(['was_owner' => true]);
});

it('does not let a trashed user sign in and keeps them out of owner counting', function (): void {
    $owner = makeOwner();
    $second = makeOwner('second@example.test');
    $second->forceFill(['password' => Hash::make('secret-pass')])->save();
    expect(auth()->guard('web')->attempt(['email' => 'second@example.test', 'password' => 'secret-pass']))->toBeTrue();
    auth()->guard('web')->logout();
    expect(app(OwnerCounter::class)->count())->toBe(2);

    $this->actingAs($owner)->deleteJson("/api/access/users/{$second->id}", ['confirmation' => 'second@example.test'])->assertNoContent();

    expect(auth()->guard('web')->attempt(['email' => 'second@example.test', 'password' => 'secret-pass']))->toBeFalse()
        ->and(app(OwnerCounter::class)->count())->toBe(1)
        ->and(app(OwnerCounter::class)->isActiveOwner(SoftTestUser::withTrashed()->find($second->id)))->toBeFalse();
});

it('refuses trashing the remaining active owner once the other one is in the trash', function (): void {
    $owner = makeOwner();
    $second = makeOwner('second@example.test');
    $second->delete();
    ActionLog::query()->delete();

    $thrown = null;
    try {
        app(ActorContext::class)->runAs(null, fn () => app(DeleteUser::class)->handle($owner, 'owner@example.test'));
    } catch (AccessException $e) {
        $thrown = $e;
    }

    expect($thrown?->errorCode)->toBe('guardrail.last_owner')
        ->and(SoftTestUser::find($owner->id))->not->toBeNull()
        ->and(ActionLog::count())->toBe(0);
});

it('lists trashed users only for the trashed filter, with the trashed status', function (): void {
    $actor = trashActor();
    $live = makeUser('live@example.test');
    $gone = makeUser('gone@example.test');
    makeState($gone, 'suspended');
    $gone->delete();

    $default = $this->actingAs($actor)->getJson('/api/access/users')->assertOk()->json('data');
    expect(collect($default)->pluck('email')->all())->not->toContain('gone@example.test')
        ->and(collect($default)->pluck('status')->unique()->all())->not->toContain('trashed');

    foreach (['filter[status][]=trashed', 'filter[status][]=active&filter[status][]=trashed', 'filter[status]=trashed'] as $query) {
        $rows = $this->actingAs($actor)->getJson("/api/access/users?{$query}")->assertOk()->json('data');
        expect($rows)->toHaveCount(1)
            ->and($rows[0]['email'])->toBe('gone@example.test')
            ->and($rows[0]['status'])->toBe('trashed')
            ->and($rows[0]['deleted_at'])->not->toBeNull();
    }

    expect($live->exists)->toBeTrue();
});

it('exposes deleted_at and the restore and force_delete abilities', function (): void {
    $actor = trashActor();
    $live = makeUser('live@example.test');
    $gone = makeUser('gone@example.test');
    $gone->delete();

    $live = collect($this->actingAs($actor)->getJson('/api/access/users')->json('data'))->firstWhere('email', 'live@example.test');
    $trashed = $this->actingAs($actor)->getJson('/api/access/users?filter[status][]=trashed')->json('data.0');

    expect($live['deleted_at'])->toBeNull()
        ->and($live['abilities'])->toMatchArray(['restore' => false, 'force_delete' => false, 'edit' => true, 'verify' => true])
        ->and($trashed['abilities'])->toBe(['view' => true, 'update' => false, 'suspend' => false, 'delete' => false, 'assign' => false, 'restore' => true, 'force_delete' => true, 'edit' => false, 'verify' => false]);

    $viewer = usersActor(['access.users.view'], 'viewer@example.test');
    $row = $this->actingAs($viewer)->getJson('/api/access/users?filter[status][]=trashed')->json('data.0');
    expect($row['abilities']['restore'])->toBeFalse()->and($row['abilities']['force_delete'])->toBeFalse();
});

it('restores a user with the roles and status they had', function (): void {
    $actor = trashActor(['reports.view']);
    $target = makeUser('t@example.test');
    $target->assignRole(makeRole('viewer', ['reports.view']));
    makeState($target, 'suspended', ['status_reason' => 'why']);
    $this->actingAs($actor)->deleteJson("/api/access/users/{$target->id}", ['confirmation' => 't@example.test'])->assertNoContent();
    ActionLog::query()->delete();

    $this->actingAs($actor)->postJson("/api/access/users/{$target->id}/restore")->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.status', 'suspended')
        ->assertJsonPath('data.deleted_at', null);

    expect(SoftTestUser::find($target->id))->not->toBeNull()
        ->and(SoftTestUser::find($target->id)->hasRole('viewer'))->toBeTrue();
    $log = ActionLog::sole();
    expect($log->event)->toBe('users.restored')->and($log->severity)->toBe('info')->and($log->subject_id)->toBe($target->id);
});

it('answers 409 user.not_trashed for restoring or permanently deleting a live user', function (string $method, string $uri, array $body): void {
    $actor = trashActor();
    $target = makeUser('t@example.test');
    ActionLog::query()->delete();

    $this->actingAs($actor)->{$method}("/api/access/users/{$target->id}{$uri}", $body)->assertStatus(409)
        ->assertJson(['success' => false, 'code' => 'user.not_trashed']);

    expect(ActionLog::count())->toBe(0)->and(SoftTestUser::find($target->id))->not->toBeNull();
})->with([
    'restore' => ['postJson', '/restore', []],
    'force' => ['deleteJson', '/force', ['confirmation' => 't@example.test']],
]);

it('requires the exact email to permanently delete and cleans every access row', function (): void {
    $actor = trashActor(['reports.view']);
    $target = makeUser('t@example.test');
    $target->assignRole(makeRole('viewer', ['reports.view']));
    $target->givePermissionTo('reports.view');
    $other = makeUser('o@example.test');
    makeState($target);
    makeState($other, 'active', ['invited_by' => $target->id]);
    SavedView::create(['user_id' => $target->id, 'page' => 'users', 'name' => 'v', 'filters' => []]);
    SavedView::create(['user_id' => $other->id, 'page' => 'users', 'name' => 'v', 'filters' => []]);
    $invitation = AccessInvitation::create(['email' => 'new@example.test', 'token_hash' => hash('sha256', 'x'), 'invited_by' => $target->id, 'expires_at' => now()->addDay()]);
    $target->delete();
    ActionLog::query()->delete();

    $this->actingAs($actor)->deleteJson("/api/access/users/{$target->id}/force", ['confirmation' => 'nope'])->assertForbidden()
        ->assertJson(['code' => 'guardrail.confirmation_mismatch']);
    $this->actingAs($actor)->deleteJson("/api/access/users/{$target->id}/force")->assertForbidden()
        ->assertJson(['code' => 'guardrail.confirmation_mismatch']);
    expect(SoftTestUser::withTrashed()->find($target->id))->not->toBeNull()->and(ActionLog::count())->toBe(0);

    $this->actingAs($actor)->deleteJson("/api/access/users/{$target->id}/force", ['confirmation' => ' T@Example.test '])->assertNoContent();

    expect(SoftTestUser::withTrashed()->find($target->id))->toBeNull()
        ->and(DB::table('model_has_roles')->where('model_id', $target->id)->count())->toBe(0)
        ->and(DB::table('model_has_permissions')->where('model_id', $target->id)->count())->toBe(0)
        ->and(UserAccessState::where('user_id', $target->id)->exists())->toBeFalse()
        ->and(UserAccessState::where('user_id', $other->id)->value('invited_by'))->toBeNull()
        ->and(SavedView::where('user_id', $target->id)->count())->toBe(0)
        ->and(SavedView::where('user_id', $other->id)->count())->toBe(1)
        ->and($invitation->fresh()->invited_by)->toBeNull();
    $log = ActionLog::sole();
    expect($log->event)->toBe('users.force_deleted')->and($log->severity)->toBe('critical')->and($log->subject_id)->toBeNull()
        ->and($log->old_values)->toEqual(['id' => $target->id, 'name' => 'A', 'email' => 't@example.test']);
});

it('refuses live-only actions on a trashed user with 409 user.trashed', function (string $method, string $uri, array $body): void {
    $actor = trashActor(['access.roles.assign']);
    $role = makeRole('viewer', ['reports.view']);
    $target = makeUser('t@example.test');
    $target->delete();
    ActionLog::query()->delete();

    $uri = str_replace('{role}', (string) $role->id, $uri);
    $response = $this->actingAs($actor)->{$method}("/api/access/users/{$target->id}{$uri}", $body === ['role'] ? ['role' => $role->id] : $body);

    $response->assertStatus(409)->assertJson(['success' => false, 'code' => 'user.trashed']);
    expect(ActionLog::count())->toBe(0);
})->with([
    'suspend' => ['postJson', '/suspend', ['reason' => 'x']],
    'reactivate' => ['postJson', '/reactivate', []],
    'deactivate' => ['postJson', '/deactivate', ['reason' => 'x']],
    'unlock' => ['postJson', '/unlock', []],
    'force reset' => ['postJson', '/force-password-reset', []],
    'delete' => ['deleteJson', '', ['confirmation' => 't@example.test']],
    'assign role' => ['postJson', '/roles', ['role']],
    'remove role' => ['deleteJson', '/roles/{role}', []],
    'sync roles' => ['putJson', '/roles', ['roles' => []]],
]);

it('answers 404 for an unknown id on the trash routes', function (): void {
    $actor = trashActor();

    $this->actingAs($actor)->postJson('/api/access/users/99999/restore')->assertNotFound();
    $this->actingAs($actor)->deleteJson('/api/access/users/99999/force', ['confirmation' => 'x'])->assertNotFound();
});

it('refuses a non-owner restoring or permanently deleting a trashed owner', function (string $method, string $uri, array $body): void {
    makeOwner();
    $owner = makeOwner('o2@example.test');
    $actor = trashActor();
    $owner->delete();
    ActionLog::query()->delete();

    $this->actingAs($actor)->{$method}("/api/access/users/{$owner->id}{$uri}", $body)->assertForbidden()
        ->assertJson(['code' => 'guardrail.target_above_actor']);

    expect(SoftTestUser::withTrashed()->find($owner->id)->trashed())->toBeTrue()->and(ActionLog::count())->toBe(0);
})->with([
    'restore' => ['postJson', '/restore', []],
    'force' => ['deleteJson', '/force', ['confirmation' => 'o2@example.test']],
]);

it('answers 409 user.email_taken when the address was taken while the user was in the trash', function (): void {
    $actor = trashActor();
    // A host whose unique index ignores trashed rows (a partial index) can end up with the address twice.
    Schema::table('users', fn (Blueprint $table) => $table->dropUnique('users_email_unique'));
    $target = makeUser('t@example.test');
    $target->delete();
    makeUser('t@example.test');
    ActionLog::query()->delete();

    $this->actingAs($actor)->postJson("/api/access/users/{$target->id}/restore")->assertStatus(409)->assertJson(['code' => 'user.email_taken']);

    expect(SoftTestUser::withTrashed()->find($target->id)->trashed())->toBeTrue()->and(ActionLog::count())->toBe(0);
});

it('answers 401 and 403 in the envelope on the trash routes', function (string $method, string $uri): void {
    $target = makeUser('t@example.test');
    $target->delete();
    $url = "/api/access/users/{$target->id}{$uri}";

    $this->{$method}($url, [])->assertUnauthorized();
    $this->actingAs(usersActor(['access.users.view', 'access.users.update', 'access.users.suspend']))->{$method}($url, ['confirmation' => 't@example.test'])
        ->assertForbidden()->assertJson(['success' => false, 'code' => 'forbidden']);

    expect(SoftTestUser::withTrashed()->find($target->id)->trashed())->toBeTrue();
})->with([['postJson', '/restore'], ['deleteJson', '/force']]);

it('reports the trashed count in the overview, excluding trashed users from the other counts', function (): void {
    $actor = usersActor(['access.users.view']);
    makeUser('a@example.test');
    makeUser('b@example.test')->delete();
    makeUser('c@example.test')->delete();

    $data = $this->actingAs($actor)->getJson('/api/access/users/overview')->assertOk()->json('data');

    expect($data['trashed'])->toBe(2)->and($data['total_members'])->toBe(2);
});

it('keeps state rows and writes one trashed audit entry when the host soft deletes directly', function (): void {
    $target = makeUser('t@example.test');
    makeState($target, 'active');
    SavedView::create(['user_id' => $target->id, 'page' => 'users', 'name' => 'v', 'filters' => []]);
    ActionLog::query()->delete();

    $target->delete();

    expect(UserAccessState::where('user_id', $target->id)->exists())->toBeTrue()
        ->and(SavedView::where('user_id', $target->id)->count())->toBe(1);
    $log = ActionLog::sole();
    expect($log->event)->toBe('users.trashed')->and($log->subject_id)->toBe($target->id);

    ActionLog::query()->delete();
    $target->forceDelete();

    expect(UserAccessState::where('user_id', $target->id)->exists())->toBeFalse()
        ->and(SavedView::where('user_id', $target->id)->count())->toBe(0)
        ->and(ActionLog::sole()->event)->toBe('users.deleted');
});

it('has no trash endpoints, trashed filter or overview count on a host without soft deletes', function (): void {
    useHostUserModel(TestUser::class);
    $actor = trashActor();
    $target = makeUser('t@example.test');

    $this->actingAs($actor)->postJson("/api/access/users/{$target->id}/restore")->assertNotFound();
    $this->actingAs($actor)->deleteJson("/api/access/users/{$target->id}/force", ['confirmation' => 't@example.test'])->assertNotFound();
    $rows = $this->actingAs($actor)->getJson('/api/access/users?filter[status][]=trashed')->assertOk()->json('data');
    expect($rows)->toHaveCount(2)
        ->and($this->actingAs($actor)->getJson('/api/access/users/overview')->json('data.trashed'))->toBe(0)
        ->and($rows[0])->toHaveKey('deleted_at', null);

    $this->actingAs($actor)->deleteJson("/api/access/users/{$target->id}", ['confirmation' => 't@example.test'])->assertNoContent();
    expect(TestUser::find($target->id))->toBeNull()
        ->and(ActionLog::where('event', 'users.deleted')->count())->toBe(1);
});
