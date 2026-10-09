<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Core\Doctor\Checks\RoutesProtectedCheck;
use Enadstack\LaravelRoles\Core\Doctor\CheckStatus;
use Enadstack\LaravelRoles\Guardrails\Guardrail;
use Enadstack\LaravelRoles\Guardrails\Guardrails;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Users\Enums\UserStatus;
use Enadstack\LaravelRoles\Users\Models\AccessInvitation;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Enadstack\LaravelRoles\Users\Support\InvitationToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\Support\TestUser;

const ACCEPT_URL = '/api/access/invitations/accept';
const GOOD_PASSWORD = 'Str0ng-Passw0rd!x';

/**
 * @param  array<string, mixed>  $attributes
 * @return array{0: AccessInvitation, 1: string} the invitation and its plain token
 */
function pendingInvitation(string $email = 'new@example.test', ?Role $role = null, array $attributes = []): array
{
    $token = InvitationToken::make();
    $invitation = AccessInvitation::create([
        'email' => strtolower($email),
        'role_id' => $role?->id,
        'token_hash' => InvitationToken::hash($token),
        'invited_by' => makeUser('inviter-'.uniqid().'@example.test')->id,
        'expires_at' => now()->addDay(),
        ...$attributes,
    ]);

    return [$invitation, $token];
}

/**
 * @param  array<string, mixed>  $override
 * @return array<string, mixed>
 */
function acceptBody(string $token, string $email = 'new@example.test', array $override = []): array
{
    return ['token' => $token, 'email' => $email, 'name' => 'New Person', 'password' => GOOD_PASSWORD, 'password_confirmation' => GOOD_PASSWORD, ...$override];
}

it('accepts a usable invitation: user, active state, only the invited role, accepted mark and audit', function (): void {
    $editor = makeRole('editor', ['reports.view']);
    makeRole('viewer');
    [$invitation, $token] = pendingInvitation('New@Example.test', $editor);
    ActionLog::query()->delete();

    $this->postJson(ACCEPT_URL, acceptBody($token, ' NEW@example.test '))->assertOk()
        ->assertExactJson(['success' => true, 'message' => null, 'data' => ['accepted' => true], 'meta' => []]);

    $user = TestUser::where('email', 'new@example.test')->sole();
    expect($user->name)->toBe('New Person')
        ->and(Hash::check(GOOD_PASSWORD, $user->password))->toBeTrue()
        ->and($user->password)->not->toBe(GOOD_PASSWORD)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->getRoleNames()->all())->toBe(['editor']);

    $state = UserAccessState::where('user_id', $user->id)->sole();
    expect($state->status)->toBe(UserStatus::Active)->and($state->activated_at)->not->toBeNull()->and($state->invited_by)->toBe($invitation->invited_by);

    expect($invitation->fresh()->accepted_at)->not->toBeNull();

    $accepted = ActionLog::where('event', 'invitations.accepted')->sole();
    expect($accepted->module)->toBe('invitations')->and($accepted->actor_id)->toBe($user->id)->and($accepted->subject_id)->toBe($invitation->id)
        ->and($accepted->meta)->toEqual(['email' => 'new@example.test', 'role' => 'editor']);
    $activated = ActionLog::where('event', 'users.activated')->sole();
    expect($activated->module)->toBe('users')->and($activated->actor_id)->toBe($user->id)->and($activated->subject_id)->toBe($user->id)
        ->and($activated->new_values)->toEqual(['status' => 'active']);
    // The reused role Action records its own roles.assigned entry for the role change.
    expect(ActionLog::pluck('event')->sort()->values()->all())->toBe(['invitations.accepted', 'roles.assigned', 'users.activated']);
    expect(json_encode(DB::table((new ActionLog)->getTable())->get()))->not->toContain($token);
});

it('lets the accepted user sign in afterwards', function (): void {
    [, $token] = pendingInvitation('login@example.test', makeRole('editor'));

    $this->postJson(ACCEPT_URL, acceptBody($token, 'login@example.test'))->assertOk();

    expect(Auth::guard('web')->attempt(['email' => 'login@example.test', 'password' => GOOD_PASSWORD]))->toBeTrue();
    expect(TestUser::where('email', 'login@example.test')->sole()->accessStatus())->toBe(UserStatus::Active);
});

it('needs no authentication and works for a guest even with a signed-in other user', function (): void {
    [, $token] = pendingInvitation();
    $this->assertGuest();

    $this->postJson(ACCEPT_URL, acceptBody($token))->assertOk();
});

it('answers every unusable token with the identical 410', function (): void {
    [, $expiredToken] = pendingInvitation('expired@example.test', null, ['expires_at' => now()->subMinute()]);
    [, $revokedToken] = pendingInvitation('revoked@example.test', null, ['revoked_at' => now()]);
    [, $usedToken] = pendingInvitation('used@example.test', null, ['accepted_at' => now()]);
    [, $wrongEmailToken] = pendingInvitation('right@example.test');

    $responses = [
        'unknown' => $this->postJson(ACCEPT_URL, acceptBody('x'.InvitationToken::make(), 'unknown@example.test')),
        'expired' => $this->postJson(ACCEPT_URL, acceptBody($expiredToken, 'expired@example.test')),
        'revoked' => $this->postJson(ACCEPT_URL, acceptBody($revokedToken, 'revoked@example.test')),
        'used' => $this->postJson(ACCEPT_URL, acceptBody($usedToken, 'used@example.test')),
        'wrong email' => $this->postJson(ACCEPT_URL, acceptBody($wrongEmailToken, 'wrong@example.test')),
    ];

    $first = $responses['unknown'];
    $first->assertStatus(410)->assertJson(['success' => false, 'code' => 'invitation.invalid']);

    foreach ($responses as $name => $response) {
        expect($response->getStatusCode())->toBe(410, $name)->and($response->getContent())->toBe($first->getContent(), $name);
    }

    expect(TestUser::whereIn('email', ['unknown@example.test', 'expired@example.test', 'revoked@example.test', 'used@example.test', 'wrong@example.test', 'right@example.test'])->count())->toBe(0);
});

it('cannot reuse a token', function (): void {
    [, $token] = pendingInvitation();

    $this->postJson(ACCEPT_URL, acceptBody($token))->assertOk();
    $this->postJson(ACCEPT_URL, acceptBody($token))->assertStatus(410)->assertJsonPath('code', 'invitation.invalid');
    expect(TestUser::where('email', 'new@example.test')->count())->toBe(1);
});

it('never takes over an existing account', function (): void {
    $existing = TestUser::create(['name' => 'Original', 'email' => 'new@example.test', 'password' => Hash::make('original-pass')]);
    [$invitation, $token] = pendingInvitation('new@example.test', makeRole('editor'));
    ActionLog::query()->delete();

    $this->postJson(ACCEPT_URL, acceptBody($token))->assertStatus(409)->assertJsonPath('code', 'invitation.user_exists');

    $fresh = $existing->fresh();
    expect($fresh->name)->toBe('Original')->and(Hash::check('original-pass', $fresh->password))->toBeTrue()
        ->and($fresh->email_verified_at)->toBeNull()->and($fresh->getRoleNames()->all())->toBe([])
        ->and(UserAccessState::where('user_id', $existing->id)->exists())->toBeFalse()
        ->and(TestUser::count())->toBe(2) // the existing user and the inviter
        ->and($invitation->fresh()->accepted_at)->toBeNull()
        ->and(ActionLog::count())->toBe(0);
});

it('validates the profile without consuming the invitation', function (array $override): void {
    [$invitation, $token] = pendingInvitation();

    $this->postJson(ACCEPT_URL, acceptBody($token, 'new@example.test', $override))->assertStatus(422)->assertJsonPath('code', 'validation_failed');

    expect($invitation->fresh()->accepted_at)->toBeNull()->and(TestUser::where('email', 'new@example.test')->exists())->toBeFalse();
})->with([
    'short password' => [['password' => 'Ab1!', 'password_confirmation' => 'Ab1!']],
    'mismatched confirmation' => [['password_confirmation' => 'different-Passw0rd!']],
    'no confirmation' => [['password_confirmation' => null]],
    'no password' => [['password' => null, 'password_confirmation' => null]],
    'no name' => [['name' => '']],
    'no token' => [['token' => null]],
    'bad email' => [['email' => 'nope']],
    'array token' => [['token' => ['a']]],
]);

it('throttles to six attempts per minute with the envelope', function (): void {
    for ($i = 0; $i < 6; $i++) {
        $this->postJson(ACCEPT_URL, acceptBody('bad-token-'.$i, 'x@example.test'))->assertStatus(410);
    }

    $this->postJson(ACCEPT_URL, acceptBody('bad-token-7', 'x@example.test'))->assertStatus(429)
        ->assertJsonPath('success', false)->assertJsonPath('code', 'too_many_requests')->assertHeader('Retry-After');
});

it('ignores role, roles and is_admin in the body and assigns only the invited role', function (): void {
    makeRole('owner', [], ['is_system' => true]);
    makeRole('admin', ['access.roles.assign']);
    $viewer = makeRole('viewer');
    [, $token] = pendingInvitation('hostile@example.test', $viewer);

    $this->postJson(ACCEPT_URL, acceptBody($token, 'hostile@example.test', ['role' => 'owner', 'roles' => ['owner', 'admin'], 'is_admin' => true, 'status' => 'suspended', 'invited_by' => 1]))->assertOk();

    $user = TestUser::where('email', 'hostile@example.test')->sole();
    expect($user->getRoleNames()->all())->toBe(['viewer'])->and($user->isAccessActive())->toBeTrue()
        ->and($user->getAttributes())->not->toHaveKey('is_admin');
});

it('creates the user without a role when the invited role has been deleted since', function (): void {
    $role = makeRole('temp');
    [, $token] = pendingInvitation('norole@example.test', $role);
    $role->delete();

    $this->postJson(ACCEPT_URL, acceptBody($token, 'norole@example.test'))->assertOk();

    expect(TestUser::where('email', 'norole@example.test')->sole()->getRoleNames()->all())->toBe([]);
});

it('grants the owner role when the inviter vetted it, with a critical role audit', function (): void {
    $owner = makeRole('owner', [], ['is_system' => true]);
    [, $token] = pendingInvitation('boss@example.test', $owner);
    ActionLog::query()->delete();

    $this->postJson(ACCEPT_URL, acceptBody($token, 'boss@example.test'))->assertOk();

    expect(TestUser::where('email', 'boss@example.test')->sole()->getRoleNames()->all())->toBe(['owner'])
        ->and(ActionLog::where('event', 'roles.assigned')->sole()->severity)->toBe('critical');
});

it('compares the token timing-safely through the hash', function (): void {
    $token = InvitationToken::make();

    expect(InvitationToken::matches(InvitationToken::hash($token), $token))->toBeTrue()
        ->and(InvitationToken::matches(InvitationToken::hash($token), $token.'x'))->toBeFalse()
        ->and(InvitationToken::matches(strtoupper(InvitationToken::hash($token)), $token))->toBeFalse()
        ->and(strlen(InvitationToken::hash($token)))->toBe(64)
        ->and(InvitationToken::make())->not->toBe(InvitationToken::make());
    expect(file_get_contents(dirname(__DIR__, 4).'/src/Users/Support/InvitationToken.php'))->toContain('hash_equals(');
});

it('rolls everything back when the role assignment fails', function (): void {
    [$invitation, $token] = pendingInvitation('atomic@example.test', makeRole('editor'));
    app()->instance(Guardrails::class, new Guardrails([
        new class implements Guardrail
        {
            public function check(Operation $operation): void
            {
                throw new RuntimeException('boom');
            }
        },
    ]));
    ActionLog::query()->delete();

    $this->withoutExceptionHandling();
    expect(fn () => $this->postJson(ACCEPT_URL, acceptBody($token, 'atomic@example.test')))->toThrow(RuntimeException::class);

    expect(TestUser::where('email', 'atomic@example.test')->exists())->toBeFalse()
        ->and(UserAccessState::count())->toBe(0)->and($invitation->fresh()->accepted_at)->toBeNull()->and(ActionLog::count())->toBe(0);
});

it('registers the accept route as the single public route with the marker and throttle, and the doctor allows only it', function (): void {
    $route = Route::getRoutes()->getByName('access.invitations.accept');
    $middleware = $route->gatherMiddleware();

    expect($middleware)->toContain('access:public')->and($middleware)->toContain('throttle:access-invitation-accept')
        ->and($middleware)->not->toContain('auth')->and($middleware)->not->toContain('auth:sanctum')
        ->and($middleware)->toContain('web');

    $publicRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r): bool => str_starts_with($r->uri(), 'api/access') && ! collect($r->gatherMiddleware())->contains(fn ($m): bool => is_string($m) && (str_starts_with($m, 'auth') || str_starts_with($m, 'can:') || $m === 'access:any')))
        ->map(fn ($r) => $r->getName())->values()->all();
    expect($publicRoutes)->toBe(['access.invitations.accept']);

    expect(app(RoutesProtectedCheck::class)->run()->status)->toBe(CheckStatus::Pass);
});

it('fails the doctor for any other route carrying the public marker', function (): void {
    Route::prefix('api/access')->middleware('access:public')->get('sneaky', fn () => 'x')->name('access.sneaky');

    $result = app(RoutesProtectedCheck::class)->run();

    expect($result->status)->toBe(CheckStatus::Fail)->and($result->message)->toContain('api/access/sneaky')->and($result->message)->not->toContain('invitations/accept');
});

it('lets access:public pass without a user and rejects unknown arguments', function (): void {
    Route::prefix('api/access')->middleware(['web', 'access:public'])->get('probe-public', fn () => 'ok');
    Route::prefix('api/access')->middleware(['web', 'access:bogus'])->get('probe-bogus', fn () => 'ok');

    $this->get('/api/access/probe-public')->assertOk();
    $this->withoutExceptionHandling();
    expect(fn () => $this->get('/api/access/probe-bogus'))->toThrow(InvalidArgumentException::class);
});
