<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Http\Support\RoutesRegistrar;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Roles\Actions\ForceDeleteRole;
use Enadstack\LaravelRoles\Roles\Actions\RemoveRoleFromUser;
use Enadstack\LaravelRoles\Roles\Actions\SyncUserRoles;
use Enadstack\LaravelRoles\Roles\Actions\TrashRole;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Users\Enums\UserStatus;
use Enadstack\LaravelRoles\Users\Models\AccessInvitation;
use Enadstack\LaravelRoles\Users\Support\InvitationSweeper;
use Enadstack\LaravelRoles\Users\Support\InvitationToken;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Support\HashedCastUser;
use Tests\Support\NonAccessUser;
use Tests\Support\TestUser;

const HARD_URL = '/api/access/invitations/accept';
const HARD_PASSWORD = 'Str0ng-Passw0rd!x';

afterEach(fn () => Carbon::setTestNow());

/** @return array{0: AccessInvitation, 1: string} */
function hardInvitation(string $email = 'new@example.test', ?Role $role = null, ?TestUser $inviter = null, array $attributes = []): array
{
    $token = InvitationToken::make();
    $invitation = AccessInvitation::create([
        'email' => $email, 'role_id' => $role?->id, 'token_hash' => InvitationToken::hash($token),
        'invited_by' => $inviter?->id, 'expires_at' => now()->addDay(), ...$attributes,
    ]);

    return [$invitation, $token];
}

/** @return array<string, mixed> */
function hardBody(string $token, string $email = 'new@example.test', array $override = []): array
{
    return ['token' => $token, 'email' => $email, 'name' => 'New', 'password' => HARD_PASSWORD, 'password_confirmation' => HARD_PASSWORD, ...$override];
}

it('accepts on a host without email_verified_at and the user can sign in', function (): void {
    Schema::table('users', fn ($table) => $table->dropColumn('email_verified_at'));
    [, $token] = hardInvitation('legacy@example.test', makeRole('viewer'));

    $this->postJson(HARD_URL, hardBody($token, 'legacy@example.test'))->assertOk();

    expect(Auth::guard('web')->attempt(['email' => 'legacy@example.test', 'password' => HARD_PASSWORD]))->toBeTrue()
        ->and(TestUser::where('email', 'legacy@example.test')->sole()->getRoleNames()->all())->toBe(['viewer']);
});

it('does not hash twice on a model with the hashed password cast', function (): void {
    config(['roles.user_model' => HashedCastUser::class, 'auth.providers.users.model' => HashedCastUser::class]);
    [, $token] = hardInvitation('cast@example.test', makeRole('viewer'));

    $this->postJson(HARD_URL, hardBody($token, 'cast@example.test'))->assertOk();

    $user = HashedCastUser::where('email', 'cast@example.test')->sole();
    expect(Hash::check(HARD_PASSWORD, $user->password))->toBeTrue()->and($user->email_verified_at)->not->toBeNull()
        ->and(Auth::guard('web')->attempt(['email' => 'cast@example.test', 'password' => HARD_PASSWORD]))->toBeTrue();
});

it('throws a clear LogicException before creating anything when the user model is not an AccessUser', function (): void {
    config(['roles.user_model' => NonAccessUser::class]);
    [$invitation, $token] = hardInvitation('mis@example.test');
    $this->withoutExceptionHandling();

    expect(fn () => $this->postJson(HARD_URL, hardBody($token, 'mis@example.test')))->toThrow(LogicException::class, 'AccessUser');

    expect(DB::table('users')->count())->toBe(0)->and($invitation->fresh()->accepted_at)->toBeNull();
});

it('caps the password length', function (): void {
    [, $token] = hardInvitation();
    $long = 'Aa1!'.str_repeat('x', 253);

    $this->postJson(HARD_URL, hardBody($token, 'new@example.test', ['password' => $long, 'password_confirmation' => $long]))->assertStatus(422);
});

it('answers the generic 410 when the inviter is suspended, deactivated or gone, and accepts after a lock or for system invitations', function (): void {
    config(['roles.users.accept_rate_limit' => 100]);
    $generic = $this->postJson(HARD_URL, hardBody('unknown-token', 'x@example.test'));

    foreach (['suspended', 'deactivated', 'invited', 'pending_verification'] as $status) {
        $inviter = makeUser("inv-{$status}@example.test");
        makeState($inviter, $status);
        [, $token] = hardInvitation("{$status}@example.test", null, $inviter);
        $response = $this->postJson(HARD_URL, hardBody($token, "{$status}@example.test"));
        expect($response->getStatusCode())->toBe(410, $status)->and($response->getContent())->toBe($generic->getContent(), $status);
    }

    $ghost = makeUser('ghost@example.test');
    [, $ghostToken] = hardInvitation('ghosted@example.test', null, $ghost);
    DB::table('users')->where('id', $ghost->id)->delete();
    $response = $this->postJson(HARD_URL, hardBody($ghostToken, 'ghosted@example.test'));
    expect($response->getStatusCode())->toBe(410)->and($response->getContent())->toBe($generic->getContent());

    $locked = makeUser('locked-inviter@example.test');
    makeState($locked, 'locked', ['locked_until' => now()->addHour()]);
    [, $lockedToken] = hardInvitation('l@example.test', null, $locked);
    $this->postJson(HARD_URL, hardBody($lockedToken, 'l@example.test'))->assertOk();

    [, $systemToken] = hardInvitation('system@example.test');
    $this->postJson(HARD_URL, hardBody($systemToken, 'system@example.test'))->assertOk();
    expect(TestUser::where('email', 'system@example.test')->exists())->toBeTrue();
});

it('revokes pending invitations when the inviter is suspended, deactivated or deleted', function (string $how): void {
    $actor = usersActor(['access.users.view', 'access.users.suspend', 'access.users.delete'], 'admin@example.test');
    $inviter = makeUser('inviter@example.test');
    [$pending] = hardInvitation('p@example.test', null, $inviter);
    [$accepted] = hardInvitation('a@example.test', null, $inviter, ['accepted_at' => now()]);
    [$other] = hardInvitation('o@example.test', null, $actor);
    ActionLog::query()->delete();

    match ($how) {
        'suspend' => $this->actingAs($actor)->postJson("/api/access/users/{$inviter->id}/suspend", ['reason' => 'x'])->assertOk(),
        'deactivate' => $this->actingAs($actor)->postJson("/api/access/users/{$inviter->id}/deactivate", ['reason' => 'x'])->assertOk(),
        'delete' => $this->actingAs($actor)->deleteJson("/api/access/users/{$inviter->id}", ['confirmation' => 'inviter@example.test'])->assertNoContent(),
    };

    expect($pending->fresh()->revoked_at)->not->toBeNull()->and($accepted->fresh()->revoked_at)->toBeNull()->and($other->fresh()->revoked_at)->toBeNull();
    $log = ActionLog::whereIn('event', ['users.suspended', 'users.deactivated', 'users.deleted'])->sole();
    expect($log->meta['revoked_invitations'])->toBe(1);
})->with(['suspend', 'deactivate', 'delete']);

it('adds no revoked_invitations key when nothing was pending', function (): void {
    $actor = usersActor(['access.users.view', 'access.users.suspend'], 'admin@example.test');
    $target = makeUser('t@example.test');
    ActionLog::query()->delete();

    $this->actingAs($actor)->postJson("/api/access/users/{$target->id}/suspend", ['reason' => 'x'])->assertOk();

    expect(ActionLog::sole()->meta)->toEqual(['reason' => 'x']);
});

/** An inviter holding two roles whose union covers `editor` (reports.view + billing.view). */
function sweepScenario(): array
{
    $inviter = makeUser('sweep@example.test');
    $a = makeRole('role-a', ['reports.view']);
    $b = makeRole('role-b', ['billing.view']);
    $inviter->assignRole($a, $b);
    $both = makeRole('both', ['reports.view', 'billing.view']);
    $onlyA = makeRole('only-a', ['reports.view']);
    [$wide] = hardInvitation('wide@example.test', $both, $inviter);
    [$narrow] = hardInvitation('narrow@example.test', $onlyA, $inviter);
    [$none] = hardInvitation('none@example.test', null, $inviter);

    return [$inviter, $b, $wide, $narrow, $none];
}

it('revokes invitations the inviter can no longer assign when they lose a role', function (string $how): void {
    [$inviter, $b, $wide, $narrow, $none] = sweepScenario();
    ActionLog::query()->delete();

    app(ActorContext::class)->runAs(null, function () use ($how, $inviter, $b): void {
        match ($how) {
            'remove' => app(RemoveRoleFromUser::class)->handle($inviter, $b),
            'sync' => app(SyncUserRoles::class)->handle($inviter, ['role-a']),
            'trash' => app(TrashRole::class)->handle($b, $b->name),
            'force' => (function () use ($b): void {
                app(TrashRole::class)->handle($b, $b->name);
                app(ForceDeleteRole::class)->handle($b, $b->name);
            })(),
        };
    });

    expect($wide->fresh()->revoked_at)->not->toBeNull()->and($narrow->fresh()->revoked_at)->toBeNull()->and($none->fresh()->revoked_at)->toBeNull();
    expect(ActionLog::where('event', 'invitations.revoked_unauthorized')->sole()->meta['revoked_invitations'])->toBe(1);
})->with(['remove', 'sync', 'trash', 'force']);

it('keeps owners invitations and revokes owner-role invitations when ownership is lost', function (): void {
    $ownerRole = makeRole('owner', [], ['is_system' => true]);
    $first = makeOwner('first@example.test');
    $second = makeOwner('second@example.test');
    [$ownerInvite] = hardInvitation('o@example.test', $ownerRole, $second);
    [$firstInvite] = hardInvitation('f@example.test', $ownerRole, $first);

    app(ActorContext::class)->runAs(null, fn () => app(RemoveRoleFromUser::class)->handle($second, $ownerRole));

    expect($ownerInvite->fresh()->revoked_at)->not->toBeNull()->and($firstInvite->fresh()->revoked_at)->toBeNull();
});

it('sweeps with a fixed number of queries however many invitations exist', function (): void {
    $count = function (int $n): int {
        $inviter = makeUser(uniqid().'@example.test');
        $role = makeRole('r'.uniqid(), ['reports.view']);
        for ($i = 0; $i < $n; $i++) {
            hardInvitation(uniqid().'@x.test', $role, $inviter);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(InvitationSweeper::class)->revokeUnauthorized($inviter);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    $small = $count(2);
    expect($count(40))->toBe($small);
});

it('uses one consistent expiry boundary for status, usability, scopes, accept and resend', function (): void {
    Carbon::setTestNow('2030-01-01 12:00:00');
    [$invitation, $token] = hardInvitation('edge@example.test', null, null, ['expires_at' => '2030-01-01 12:00:00']);
    $invitation = $invitation->fresh();

    expect($invitation->status())->toBe('expired')->and($invitation->isUsable())->toBeFalse()
        ->and(AccessInvitation::pending()->count())->toBe(0)->and(AccessInvitation::expired()->count())->toBe(1);
    $this->postJson(HARD_URL, hardBody($token, 'edge@example.test'))->assertStatus(410);

    $actor = usersActor(['access.invitations.create'], 'creator@example.test');
    $this->actingAs($actor)->postJson("/api/access/invitations/{$invitation->id}/resend")->assertOk()->assertJsonPath('data.status', 'pending');

    Carbon::setTestNow('2030-01-01 12:00:00');
    $invitation->update(['expires_at' => '2030-01-01 12:00:01']);
    expect($invitation->fresh()->status())->toBe('pending')->and($invitation->fresh()->isUsable())->toBeTrue()
        ->and(AccessInvitation::pending()->count())->toBe(1)->and(AccessInvitation::expired()->count())->toBe(0);
});

it('refuses to resend while another pending invitation exists for the email', function (): void {
    $actor = usersActor(['access.invitations.create'], 'creator@example.test');
    [$old] = hardInvitation('dup@example.test', null, null, ['expires_at' => now()->subDay()]);
    hardInvitation('dup@example.test');

    $this->actingAs($actor)->postJson("/api/access/invitations/{$old->id}/resend")->assertStatus(409)->assertJsonPath('code', 'invitation.pending_exists');
    expect($old->fresh()->expires_at->isPast())->toBeTrue();
});

it('throttles the accept route with the named limiter per IP and the configured limit', function (): void {
    config(['roles.users.accept_rate_limit' => 2]);

    foreach ([1, 2] as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => '10.1.1.1'])->postJson(HARD_URL, hardBody("t{$i}"))->assertStatus(410);
    }
    $this->withServerVariables(['REMOTE_ADDR' => '10.1.1.1'])->postJson(HARD_URL, hardBody('t3'))->assertStatus(429)->assertJsonPath('code', 'too_many_requests');
    $this->withServerVariables(['REMOTE_ADDR' => '10.2.2.2'])->postJson(HARD_URL, hardBody('t4'))->assertStatus(410);

    expect(Route::getRoutes()->getByName('access.invitations.accept')->gatherMiddleware())->toContain('throttle:access-invitation-accept');
});

it('serves the accept route without a user under the default and the web authenticated stacks', function (array $stack): void {
    config(['roles.routes.middleware' => $stack]);
    app('router')->setRoutes(new RouteCollection);
    RoutesRegistrar::registerPublic('invitations', dirname(__DIR__, 4).'/src/Users/public-routes.php');
    app('router')->getRoutes()->refreshNameLookups();
    [, $token] = hardInvitation('stack@example.test', makeRole('viewer'));

    $middleware = app('router')->getRoutes()->getByName('access.invitations.accept')->gatherMiddleware();
    expect($middleware)->not->toContain('auth')->and($middleware)->not->toContain('auth:sanctum');

    $this->postJson(HARD_URL, hardBody($token, 'stack@example.test'))->assertOk()->assertJsonPath('data.accepted', true);
})->with([
    'api + sanctum' => [['api', 'auth:sanctum']],
    'web + auth' => [['web', 'auth']],
]);

it('keeps the user state active after acceptance for both statuses of the helper', function (): void {
    [, $token] = hardInvitation('act@example.test');
    $this->postJson(HARD_URL, hardBody($token, 'act@example.test'))->assertOk();

    expect(TestUser::where('email', 'act@example.test')->sole()->accessStatus())->toBe(UserStatus::Active);
});
