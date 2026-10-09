<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType;
use Enadstack\LaravelRoles\Users\Actions\CreateInvitation;
use Enadstack\LaravelRoles\Users\Models\AccessInvitation;
use Enadstack\LaravelRoles\Users\Notifications\AccessInvitationNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\Support\TestUser;

beforeEach(function (): void {
    Notification::fake();
});

afterEach(fn () => Model::preventLazyLoading(false));

function inviter(array $extra = []): TestUser
{
    return usersActor(['access.invitations.view', 'access.invitations.create', 'access.invitations.revoke', 'access.users.view', 'reports.view', ...$extra], 'inviter@example.test');
}

/** @return list<string> the plain tokens mailed to `$email`, oldest first */
function mailedTokens(string $email): array
{
    $tokens = [];

    foreach (Notification::sentNotifications() as $byNotifiable) {
        foreach ($byNotifiable as $byType) {
            foreach ($byType[AccessInvitationNotification::class] ?? [] as $sent) {
                $notification = $sent['notification'];
                parse_str((string) parse_url($notification->acceptUrl(), PHP_URL_QUERY), $query);
                if (($query['email'] ?? null) === $email) {
                    $tokens[] = (string) $query['token'];
                }
            }
        }
    }

    return $tokens;
}

/** @param  array<string, mixed>  $body */
function postInvitation(mixed $test, TestUser $actor, array $body)
{
    return $test->actingAs($actor)->postJson('/api/access/invitations', $body);
}

it('creates an invitation, mails the link once and never stores or returns the plain token', function (): void {
    $actor = inviter();
    makeRole('editor', ['reports.view']);
    ActionLog::query()->delete();

    $response = postInvitation($this, $actor, ['email' => '  New.Person@Example.TEST ', 'role' => 'editor'])->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.email', 'new.person@example.test')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.role.name', 'editor')
        ->assertJsonPath('data.invited_by.id', $actor->id)
        ->assertJsonPath('data.invited_by.name', 'A')
        ->assertJsonPath('meta.mail_sent', true);

    $tokens = mailedTokens('new.person@example.test');
    expect($tokens)->toHaveCount(1);
    $token = $tokens[0];
    expect(strlen($token))->toBeGreaterThanOrEqual(86)->and($token)->toMatch('/^[A-Za-z0-9_-]+$/');

    $invitation = AccessInvitation::sole();
    expect($invitation->token_hash)->toBe(hash('sha256', $token))
        ->and($invitation->expires_at->between(now()->addHours(71), now()->addHours(73)))->toBeTrue()
        ->and(array_keys($response->json('data')))->not->toContain('token', 'token_hash');

    Notification::assertSentOnDemandTimes(AccessInvitationNotification::class, 1);
    Notification::assertSentOnDemand(AccessInvitationNotification::class, function ($notification, $channels, $notifiable) use ($token): bool {
        $url = $notification->acceptUrl();

        return $channels === ['mail']
            && substr_count($url, $token) === 1
            && str_starts_with($url, url('/accept-invitation').'?token=')
            && str_contains($url, 'email=new.person%40example.test')
            && $notifiable->routes['mail'] === 'new.person@example.test';
    });

    $needle = $token;
    expect(json_encode(DB::table('access_invitations')->get()))->not->toContain($needle)
        ->and(json_encode(DB::table((new ActionLog)->getTable())->get()))->not->toContain($needle)
        ->and($response->getContent())->not->toContain($needle);
});

it('writes one info audit entry without the token', function (): void {
    $actor = inviter();
    makeRole('editor', ['reports.view']);
    ActionLog::query()->delete();

    postInvitation($this, $actor, ['email' => 'a@new.test', 'role' => 'editor'])->assertCreated();

    $log = ActionLog::sole();
    expect($log->event)->toBe('invitations.created')->and($log->module)->toBe('invitations')->and($log->severity)->toBe('info')
        ->and($log->actor_id)->toBe($actor->id)->and($log->meta)->toEqual(['email' => 'a@new.test', 'role' => 'editor'])
        ->and($log->subject_id)->toBe(AccessInvitation::sole()->id);
});

it('accepts a role id and uses the configured accept url and ttl', function (): void {
    config(['roles.users.accept_url' => 'https://app.example.test/join?src=mail', 'roles.users.invitation_ttl_hours' => 24]);
    $actor = inviter();
    $role = makeRole('editor', ['reports.view']);

    postInvitation($this, $actor, ['email' => 'id@new.test', 'role' => $role->id])->assertCreated();

    expect(AccessInvitation::sole()->expires_at->between(now()->addHours(23), now()->addHours(25)))->toBeTrue();
    Notification::assertSentOnDemand(AccessInvitationNotification::class, fn ($n): bool => str_starts_with($n->acceptUrl(), 'https://app.example.test/join?src=mail&token='));
});

it('validates the body', function (array $body): void {
    $actor = inviter();
    makeRole('editor', ['reports.view']);

    postInvitation($this, $actor, $body)->assertStatus(422)->assertJsonPath('code', 'validation_failed');
    expect(AccessInvitation::count())->toBe(0);
})->with([
    'no email' => [['role' => 'editor']],
    'bad email' => [['email' => 'nope', 'role' => 'editor']],
    'no role' => [['email' => 'a@new.test']],
    'array role' => [['email' => 'a@new.test', 'role' => ['editor']]],
]);

it('answers 404 for an unknown role', function (): void {
    postInvitation($this, inviter(), ['email' => 'a@new.test', 'role' => 'ghost'])->assertNotFound();
});

it('refuses an email that already belongs to a user', function (): void {
    $actor = inviter();
    makeRole('editor', ['reports.view']);
    makeUser('Taken@Example.test');

    postInvitation($this, $actor, ['email' => 'taken@example.TEST', 'role' => 'editor'])->assertStatus(409)->assertJsonPath('code', 'invitation.user_exists');
    expect(AccessInvitation::count())->toBe(0);
    Notification::assertNothingSent();
});

it('refuses a second pending invitation but allows one after expiry or revocation', function (): void {
    $actor = inviter();
    makeRole('editor', ['reports.view']);

    postInvitation($this, $actor, ['email' => 'dup@new.test', 'role' => 'editor'])->assertCreated();
    postInvitation($this, $actor, ['email' => 'DUP@new.test', 'role' => 'editor'])->assertStatus(409)->assertJsonPath('code', 'invitation.pending_exists');
    expect(AccessInvitation::count())->toBe(1);

    AccessInvitation::query()->update(['expires_at' => now()->subMinute()]);
    postInvitation($this, $actor, ['email' => 'dup@new.test', 'role' => 'editor'])->assertCreated();

    AccessInvitation::query()->latest('id')->first()->update(['revoked_at' => now()]);
    postInvitation($this, $actor, ['email' => 'dup@new.test', 'role' => 'editor'])->assertCreated();
    expect(AccessInvitation::count())->toBe(3);
});

it('applies the role guardrails to the invited role without assigning anything', function (): void {
    $actor = inviter();
    makeRole('super-editor', ['reports.view', 'billing.view']);
    makeRole('risky', ['reports.view']);
    makePermission('risky.perm', 'high');
    Role::findByName('risky', 'web')->givePermissionTo('risky.perm');
    $actor->assignRole(Role::findByName('risky', 'web'));
    ActionLog::query()->delete();

    postInvitation($this, $actor, ['email' => 'e@new.test', 'role' => 'super-editor'])->assertForbidden()->assertJsonPath('code', 'guardrail.escalation');
    postInvitation($this, $actor, ['email' => 'r@new.test', 'role' => 'risky'])->assertForbidden()->assertJsonPath('code', 'guardrail.high_risk_confirm_required');

    expect(AccessInvitation::count())->toBe(0)->and(ActionLog::count())->toBe(0);
    Notification::assertNothingSent();

    postInvitation($this, $actor, ['email' => 'r@new.test', 'role' => 'risky', 'confirm' => true])->assertCreated();
    expect(ActionLog::sole()->severity)->toBe('warning')
        ->and(TestUser::where('email', 'r@new.test')->exists())->toBeFalse();
});

it('lets only an owner invite to the owner role and only with confirmation', function (): void {
    $owner = makeOwner();
    $admin = inviter();

    postInvitation($this, $admin, ['email' => 'o@new.test', 'role' => 'owner', 'confirm' => true])->assertForbidden()->assertJsonPath('code', 'guardrail.escalation');
    postInvitation($this, $owner, ['email' => 'o@new.test', 'role' => 'owner'])->assertForbidden()->assertJsonPath('code', 'guardrail.high_risk_confirm_required');
    expect(AccessInvitation::count())->toBe(0);

    ActionLog::query()->delete();
    postInvitation($this, $owner, ['email' => 'o@new.test', 'role' => 'owner', 'confirm' => true])->assertCreated();
    expect(ActionLog::sole()->severity)->toBe('critical');
});

it('tolerates a null target in every guardrail rule', function (): void {
    $owner = makeOwner();
    $role = makeRole('editor', ['reports.view']);

    foreach ([null, $owner, usersActor(['reports.view'], 'x@example.test')] as $actor) {
        $code = guardrailCode(new Operation(
            OperationType::AssignRole,
            actor: $actor,
            added: [$role],
        ));
        expect($code)->toBeNull();
    }
});

it('sends the mail only after the transaction commits and never for a rolled-back write', function (): void {
    $actor = inviter();
    $role = makeRole('editor', ['reports.view']);
    $this->actingAs($actor);

    try {
        DB::transaction(function () use ($role): void {
            app(CreateInvitation::class)->handle('rb@new.test', $role);
            Notification::assertNothingSent();

            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    Notification::assertNothingSent();
    expect(AccessInvitation::count())->toBe(0);

    app(CreateInvitation::class)->handle('ok@new.test', $role);
    Notification::assertSentOnDemandTimes(AccessInvitationNotification::class, 1);
});

it('keeps the invitation when the mail fails and flags mail_sent false', function (): void {
    Notification::swap(new ChannelManager(app()));
    config(['mail.default' => 'array']);
    Event::listen(NotificationSending::class, fn () => throw new RuntimeException('smtp down'));
    $actor = inviter();
    makeRole('editor', ['reports.view']);

    postInvitation($this, $actor, ['email' => 'fail@new.test', 'role' => 'editor'])->assertCreated()
        ->assertJsonPath('meta.mail_sent', false)
        ->assertJsonPath('data.status', 'pending');

    expect(AccessInvitation::where('email', 'fail@new.test')->exists())->toBeTrue();
});

it('lists invitations with derived statuses, filters, search, sort and counts', function (): void {
    $actor = inviter();
    $editor = makeRole('editor', ['reports.view']);
    $make = fn (string $email, array $attributes = []) => AccessInvitation::create([
        'email' => $email, 'role_id' => $editor->id, 'token_hash' => hash('sha256', $email), 'invited_by' => $actor->id,
        'expires_at' => now()->addDay(), ...$attributes,
    ]);
    $pending = $make('pending@x.test');
    $accepted = $make('accepted@x.test', ['accepted_at' => now()]);
    $expired = $make('expired@x.test', ['expires_at' => now()->subDay()]);
    $revoked = $make('revoked@x.test', ['revoked_at' => now()]);
    $expiredAccepted = $make('both@x.test', ['expires_at' => now()->subDay(), 'accepted_at' => now()->subHour()]);

    $statuses = fn (string $query) => collect($this->actingAs($actor)->getJson("/api/access/invitations?{$query}")->assertOk()->json('data'))->pluck('status', 'email')->all();

    expect($statuses(''))->toEqual([
        'both@x.test' => 'accepted', 'revoked@x.test' => 'revoked', 'expired@x.test' => 'expired', 'accepted@x.test' => 'accepted', 'pending@x.test' => 'pending',
    ]);
    expect($statuses('filter[status]=pending'))->toBe(['pending@x.test' => 'pending'])
        ->and($statuses('filter[status]=accepted'))->toHaveCount(2)
        ->and($statuses('filter[status]=expired'))->toBe(['expired@x.test' => 'expired'])
        ->and($statuses('filter[status]=revoked'))->toBe(['revoked@x.test' => 'revoked'])
        ->and($statuses('filter[status][]=pending&filter[status][]=revoked'))->toHaveCount(2)
        ->and($statuses('filter[status]=bogus'))->toHaveCount(5)
        ->and($statuses('search=expir'))->toBe(['expired@x.test' => 'expired']);

    $sorted = collect($this->actingAs($actor)->getJson('/api/access/invitations?sort=expires')->json('data'))->pluck('email')->all();
    expect($sorted[0])->toBeIn(['expired@x.test', 'both@x.test']);

    $response = $this->actingAs($actor)->getJson('/api/access/invitations')->assertOk();
    expect($response->json('meta.counts'))->toBe(['pending' => 1, 'accepted' => 2, 'expired' => 1, 'revoked' => 1])
        ->and($response->json('meta.pagination.total'))->toBe(5)
        ->and($response->json('data.0'))->toHaveKeys(['id', 'email', 'role', 'status', 'expires_at', 'accepted_at', 'revoked_at', 'invited_by', 'created_at'])
        ->and($response->json('data.0'))->not->toHaveKeys(['token', 'token_hash'])
        ->and($response->getContent())->not->toContain(hash('sha256', 'pending@x.test'));
});

it('shows the inviter name only to viewers who may see users', function (): void {
    $inviter = inviter();
    $viewer = usersActor(['access.invitations.view'], 'viewer@example.test');
    AccessInvitation::create(['email' => 'p@x.test', 'token_hash' => hash('sha256', 'p'), 'invited_by' => $inviter->id, 'expires_at' => now()->addDay()]);

    $this->actingAs($viewer)->getJson('/api/access/invitations')->assertOk()
        ->assertJsonPath('data.0.invited_by', ['id' => $inviter->id])->assertJsonPath('data.0.role', null);
    $this->actingAs($inviter)->getJson('/api/access/invitations')->assertOk()->assertJsonPath('data.0.invited_by.name', 'A');
});

it('lists with the same number of queries for 5 and 100 invitations', function (): void {
    Model::preventLazyLoading();
    $actor = inviter();
    $roles = [makeRole('a', ['reports.view']), makeRole('b'), makeRole('c')];

    $populate = function (int $count) use ($roles, $actor): void {
        for ($i = 0; $i < $count; $i++) {
            AccessInvitation::create([
                'email' => uniqid().'@x.test', 'role_id' => $roles[$i % 3]->id, 'token_hash' => hash('sha256', uniqid()),
                'invited_by' => $i % 2 === 0 ? $actor->id : makeUser(uniqid().'@u.test')->id, 'expires_at' => now()->addDays($i % 2 === 0 ? 1 : -1),
            ]);
        }
    };
    $count = function () use ($actor): int {
        $this->actingAs($actor);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/access/invitations?per_page=100&sort=-expires&search=x.test&filter[status]=pending&filter[status]=expired')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    $populate(5);
    $count();
    $small = $count();
    $populate(95);
    $large = $count();

    expect($large)->toBe($small)->and($large)->toBeLessThanOrEqual(14);
});

it('resends with a rotated token, a fresh expiry and an audit entry, never returning the token', function (): void {
    $actor = inviter();
    makeRole('editor', ['reports.view']);
    postInvitation($this, $actor, ['email' => 'rs@new.test', 'role' => 'editor'])->assertCreated();
    $old = mailedTokens('rs@new.test')[0];
    AccessInvitation::query()->update(['expires_at' => now()->addHour()]);
    ActionLog::query()->delete();

    $id = AccessInvitation::sole()->id;
    $response = $this->actingAs($actor)->postJson("/api/access/invitations/{$id}/resend")->assertOk()
        ->assertJsonPath('data.status', 'pending')->assertJsonPath('meta.mail_sent', true);

    $tokens = mailedTokens('rs@new.test');
    expect($tokens)->toHaveCount(2)->and($tokens[1])->not->toBe($old)
        ->and(AccessInvitation::sole()->token_hash)->toBe(hash('sha256', $tokens[1]))
        ->and(AccessInvitation::sole()->expires_at->isAfter(now()->addHours(71)))->toBeTrue()
        ->and($response->getContent())->not->toContain($tokens[1])->and($response->getContent())->not->toContain($old)
        ->and(json_encode(DB::table((new ActionLog)->getTable())->get()))->not->toContain($tokens[1]);

    $log = ActionLog::sole();
    expect($log->event)->toBe('invitations.resent')->and($log->severity)->toBe('info')->and($log->meta)->toEqual(['email' => 'rs@new.test', 'role' => 'editor']);

    $this->postJson('/api/access/invitations/accept', ['token' => $old, 'email' => 'rs@new.test', 'name' => 'N', 'password' => 'Str0ng-Passw0rd!x', 'password_confirmation' => 'Str0ng-Passw0rd!x'])
        ->assertStatus(410);
    $this->postJson('/api/access/invitations/accept', ['token' => $tokens[1], 'email' => 'rs@new.test', 'name' => 'N', 'password' => 'Str0ng-Passw0rd!x', 'password_confirmation' => 'Str0ng-Passw0rd!x'])
        ->assertOk();
});

it('revives an expired invitation on resend and refuses accepted and revoked ones', function (): void {
    $actor = inviter();
    $make = fn (array $attributes) => AccessInvitation::create(['email' => uniqid().'@x.test', 'token_hash' => hash('sha256', uniqid()), 'expires_at' => now()->addDay(), ...$attributes]);

    $expired = $make(['expires_at' => now()->subDay()]);
    $this->actingAs($actor)->postJson("/api/access/invitations/{$expired->id}/resend")->assertOk()->assertJsonPath('data.status', 'pending');

    foreach (['accepted_at', 'revoked_at'] as $column) {
        $dead = $make([$column => now()]);
        ActionLog::query()->delete();
        $this->actingAs($actor)->postJson("/api/access/invitations/{$dead->id}/resend")->assertStatus(409)->assertJsonPath('code', 'invitation.not_pending');
        expect(ActionLog::count())->toBe(0);
    }
});

it('refuses a resend when the email became a user', function (): void {
    $actor = inviter();
    $invitation = AccessInvitation::create(['email' => 'late@x.test', 'token_hash' => hash('sha256', 'z'), 'expires_at' => now()->addDay()]);
    makeUser('late@x.test');

    $this->actingAs($actor)->postJson("/api/access/invitations/{$invitation->id}/resend")->assertStatus(409)->assertJsonPath('code', 'invitation.user_exists');
});

it('re-checks the role guardrails on resend', function (): void {
    $actor = inviter();
    $invitation = AccessInvitation::create(['email' => 'o@x.test', 'role_id' => makeRole('owner', [], ['is_system' => true])->id, 'token_hash' => hash('sha256', 'o'), 'expires_at' => now()->addDay()]);

    $this->actingAs($actor)->postJson("/api/access/invitations/{$invitation->id}/resend", ['confirm' => true])->assertForbidden()->assertJsonPath('code', 'guardrail.escalation');
});

it('revokes once, audits once and kills the token', function (): void {
    $actor = inviter();
    makeRole('editor', ['reports.view']);
    postInvitation($this, $actor, ['email' => 'rv@new.test', 'role' => 'editor'])->assertCreated();
    $token = mailedTokens('rv@new.test')[0];
    $id = AccessInvitation::sole()->id;
    ActionLog::query()->delete();

    $this->actingAs($actor)->deleteJson("/api/access/invitations/{$id}")->assertOk()->assertJsonPath('data.status', 'revoked');
    $log = ActionLog::sole();
    expect($log->event)->toBe('invitations.revoked')->and($log->old_values)->toEqual(['status' => 'pending'])->and($log->meta)->toEqual(['email' => 'rv@new.test', 'role' => 'editor']);

    $revokedAt = AccessInvitation::sole()->revoked_at;
    $this->travel(5)->minutes();
    $this->actingAs($actor)->deleteJson("/api/access/invitations/{$id}")->assertOk()->assertJsonPath('data.status', 'revoked');
    expect(ActionLog::count())->toBe(1)->and(AccessInvitation::sole()->revoked_at->equalTo($revokedAt))->toBeTrue();

    $this->postJson('/api/access/invitations/accept', ['token' => $token, 'email' => 'rv@new.test', 'name' => 'N', 'password' => 'Str0ng-Passw0rd!x', 'password_confirmation' => 'Str0ng-Passw0rd!x'])->assertStatus(410);
    expect(TestUser::where('email', 'rv@new.test')->exists())->toBeFalse();
});

it('does not revoke an accepted invitation', function (): void {
    $actor = inviter();
    $accepted = AccessInvitation::create(['email' => 'a@x.test', 'token_hash' => hash('sha256', 'a'), 'expires_at' => now()->addDay(), 'accepted_at' => now()]);

    $this->actingAs($actor)->deleteJson("/api/access/invitations/{$accepted->id}")->assertStatus(409)->assertJsonPath('code', 'invitation.not_pending');
    expect($accepted->fresh()->revoked_at)->toBeNull();
});

it('enforces authentication and permissions on every authenticated route', function (string $method, string $uri, string $permission): void {
    $invitation = AccessInvitation::create(['email' => 'p@x.test', 'token_hash' => hash('sha256', 'p'), 'expires_at' => now()->addDay()]);
    $uri = str_replace('{id}', (string) $invitation->id, $uri);
    $body = $method === 'POST' && $uri === '/api/access/invitations' ? ['email' => 'n@x.test', 'role' => 'editor'] : [];
    makeRole('editor');

    $this->json($method, $uri, $body)->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');

    $others = collect(['access.invitations.view', 'access.invitations.create', 'access.invitations.revoke'])->reject($permission)->all();
    $this->actingAs(usersActor([...$others, 'access.users.view'], 'denied@example.test'))->json($method, $uri, $body)->assertForbidden()->assertJsonPath('code', 'forbidden');
    $this->actingAs(usersActor([$permission], 'allowed@example.test'))->json($method, $uri, $body)->assertSuccessful();
})->with([
    'list' => ['GET', '/api/access/invitations', 'access.invitations.view'],
    'create' => ['POST', '/api/access/invitations', 'access.invitations.create'],
    'resend' => ['POST', '/api/access/invitations/{id}/resend', 'access.invitations.create'],
    'revoke' => ['DELETE', '/api/access/invitations/{id}', 'access.invitations.revoke'],
]);

it('answers 404 for unknown or malformed invitation ids', function (): void {
    $actor = inviter();

    $this->actingAs($actor)->postJson('/api/access/invitations/999/resend')->assertNotFound();
    $this->actingAs($actor)->deleteJson('/api/access/invitations/999')->assertNotFound();
    $this->actingAs($actor)->deleteJson('/api/access/invitations/abc')->assertNotFound();
});

it('sends no registration event', function (): void {
    Event::fake([Registered::class]);
    $actor = inviter();
    makeRole('editor', ['reports.view']);

    postInvitation($this, $actor, ['email' => 'e@new.test', 'role' => 'editor'])->assertCreated();
    Event::assertNotDispatched(Registered::class);
});
