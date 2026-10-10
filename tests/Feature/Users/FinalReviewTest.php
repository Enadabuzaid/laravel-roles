<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Core\Doctor\Checks\InvitationsRequireUsersCheck;
use Enadstack\LaravelRoles\Core\Doctor\Checks\OwnerExistsCheck;
use Enadstack\LaravelRoles\Core\Doctor\CheckStatus;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Roles\Actions\ForceDeleteRole;
use Enadstack\LaravelRoles\Roles\Actions\TrashRole;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Users\Enums\UserStatus;
use Enadstack\LaravelRoles\Users\Models\AccessInvitation;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('revokes pending invitations to a role when it is trashed, with one audit entry', function (): void {
    $role = makeRole('temp');
    $other = makeRole('other');
    $pending = AccessInvitation::create(['email' => 'a@x.test', 'role_id' => $role->id, 'token_hash' => hash('sha256', 'a'), 'expires_at' => now()->addDay()]);
    $unrelated = AccessInvitation::create(['email' => 'b@x.test', 'role_id' => $other->id, 'token_hash' => hash('sha256', 'b'), 'expires_at' => now()->addDay()]);
    ActionLog::query()->delete();

    app(ActorContext::class)->runAs(null, fn () => app(TrashRole::class)->handle($role));

    expect($pending->fresh()->revoked_at)->not->toBeNull()->and($unrelated->fresh()->revoked_at)->toBeNull();
    $log = ActionLog::where('event', 'invitations.revoked_role_removed')->sole();
    expect($log->severity)->toBe('warning')->and($log->meta)->toEqual(['role' => 'temp', 'revoked_invitations' => 1]);
});

it('takes the super role lock before the role lock when trashing and force deleting', function (): void {
    makeRole('owner', [], ['is_system' => true]);
    $role = makeRole('temp');
    $locks = static fn (array $log): array => array_values(array_filter($log, static fn (array $q): bool => str_contains(strtolower($q['query']), 'for update')));

    DB::flushQueryLog();
    DB::enableQueryLog();
    app(ActorContext::class)->runAs(null, fn () => app(TrashRole::class)->handle($role));
    $trash = $locks(DB::getQueryLog());
    DB::flushQueryLog();
    app(ActorContext::class)->runAs(null, fn () => app(ForceDeleteRole::class)->handle($role, 'temp'));
    $force = $locks(DB::getQueryLog());
    DB::disableQueryLog();

    expect($trash[0]['bindings'])->toContain('owner')->and($force[0]['bindings'])->toContain('owner');
})->skip(fn (): bool => DB::connection()->getDriverName() === 'sqlite', 'sqlite has no row locks');

it('stamps the resender as the inviter when resending', function (): void {
    $creator = usersActor(['access.invitations.create'], 'creator@example.test');
    $invitation = AccessInvitation::create(['email' => 'r@x.test', 'token_hash' => hash('sha256', 'r'), 'expires_at' => now()->addDay()]);

    $this->actingAs($creator)->postJson("/api/access/invitations/{$invitation->id}/resend")->assertOk();

    expect($invitation->fresh()->invited_by)->toBe($creator->id);
});

it('fails the owner doctor check when only inactive owners exist', function (): void {
    $owner = makeOwner();
    expect(app(OwnerExistsCheck::class)->run()->status)->toBe(CheckStatus::Pass);

    makeState($owner, 'suspended');
    $result = app(OwnerExistsCheck::class)->run();

    expect($result->status)->toBe(CheckStatus::Fail)->and($result->message)->toContain('none is active');
});

it('reactivates a suspended target of roles:owner with an audit entry, and refuses an invited one', function (): void {
    $this->artisan('roles:seed')->assertExitCode(0);
    $suspended = makeUser('boss@example.test');
    makeState($suspended, 'suspended', ['status_reason' => 'x']);
    ActionLog::query()->delete();

    $this->artisan('roles:owner boss@example.test')->expectsOutputToContain('reactivated')->assertExitCode(0);

    expect($suspended->fresh()->accessStatus())->toBe(UserStatus::Active)->and($suspended->fresh()->isOwner())->toBeTrue()
        ->and(ActionLog::where('event', 'users.reactivated')->count())->toBe(1);

    $invited = makeUser('invited@example.test');
    makeState($invited, 'invited');
    $this->artisan('roles:owner invited@example.test')->expectsOutputToContain('accept the invitation')->assertExitCode(1);
    expect($invited->fresh()->isOwner())->toBeFalse();
});

it('records password_changed_at when a password reset completes, and ignores a missing table', function (): void {
    $user = makeUser('pw@example.test');

    event(new PasswordReset($user));
    expect(UserAccessState::where('user_id', $user->id)->sole()->password_changed_at)->not->toBeNull();

    Schema::drop('user_access_states');
    event(new PasswordReset($user));
    expect(true)->toBeTrue();
});

it('flags invitations enabled without users in the doctor', function (): void {
    expect(app(InvitationsRequireUsersCheck::class)->run()->status)->toBe(CheckStatus::Pass);

    config(['roles.modules.users' => false]);
    expect(app(InvitationsRequireUsersCheck::class)->run()->status)->toBe(CheckStatus::Fail);

    config(['roles.modules.invitations' => false]);
    expect(app(InvitationsRequireUsersCheck::class)->run()->status)->toBe(CheckStatus::Pass);
});

it('audits owner reactivation and unlock as critical', function (): void {
    $owner = makeOwner();
    $second = makeOwner('second@example.test');
    $actor = $owner;

    makeState($second, 'suspended');
    $this->actingAs($actor)->postJson("/api/access/users/{$second->id}/reactivate")->assertOk();
    UserAccessState::where('user_id', $second->id)->update(['status' => 'locked', 'locked_until' => now()->addHour()]);
    $this->actingAs($actor)->postJson("/api/access/users/{$second->id}/unlock")->assertOk();

    $severities = ActionLog::whereIn('event', ['users.reactivated', 'users.unlocked'])->pluck('severity', 'event')->all();
    expect($severities)->toBe(['users.reactivated' => 'critical', 'users.unlocked' => 'critical']);
});

it('shows the owner doctor failure message for a role with no holders', function (): void {
    expect(app(OwnerExistsCheck::class)->run()->message)->toContain('roles:seed');
    expect(Role::count())->toBe(0);
});
