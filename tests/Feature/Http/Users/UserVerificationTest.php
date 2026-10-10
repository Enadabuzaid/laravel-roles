<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\TestUser;
use Tests\Support\VerifiableTestUser;

function verifier(): TestUser
{
    return usersActor(['access.users.view', 'access.users.update'], 'verifier@example.test');
}

it('verifies an email manually, activating a pending user, with a warning audit entry', function (): void {
    $actor = verifier();
    $target = makeUser('t@example.test');
    makeState($target, 'pending_verification');
    ActionLog::query()->delete();

    $this->actingAs($actor)->postJson("/api/access/users/{$target->id}/verify-email")->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.abilities.verify', false);

    expect($target->fresh()->email_verified_at)->not->toBeNull();
    $log = ActionLog::sole();
    expect($log->event)->toBe('users.email_verified')->and($log->severity)->toBe('warning')->and($log->subject_id)->toBe($target->id);
});

it('keeps an active unverified user active and answers 409 when already verified', function (): void {
    $actor = verifier();
    $target = makeUser('t@example.test');

    $this->actingAs($actor)->postJson("/api/access/users/{$target->id}/verify-email")->assertOk()->assertJsonPath('data.status', 'active');
    $this->actingAs($actor)->postJson("/api/access/users/{$target->id}/verify-email")->assertStatus(409)
        ->assertJson(['code' => 'user.already_verified']);
});

it('resends the verification notification and audits it', function (): void {
    Notification::fake();
    useHostUserModel(VerifiableTestUser::class);
    $actor = verifier();
    $target = makeUser('t@example.test');
    ActionLog::query()->delete();

    $this->actingAs($actor)->postJson("/api/access/users/{$target->id}/resend-verification")->assertOk()
        ->assertJsonPath('meta.verification_sent', true);

    Notification::assertSentTo($target->fresh(), VerifyEmail::class);
    $log = ActionLog::sole();
    expect($log->event)->toBe('users.verification_resent')->and($log->severity)->toBe('info');
});

it('refuses resending for verified users and unsupported hosts', function (): void {
    $actor = verifier();
    $plain = makeUser('p@example.test');

    $this->actingAs($actor)->postJson("/api/access/users/{$plain->id}/resend-verification")->assertStatus(409)
        ->assertJson(['code' => 'user.verification_unsupported']);

    useHostUserModel(VerifiableTestUser::class);
    $verified = makeUser('v@example.test');
    $verified->forceFill(['email_verified_at' => now()])->save();

    $this->actingAs($actor)->postJson("/api/access/users/{$verified->id}/resend-verification")->assertStatus(409)
        ->assertJson(['code' => 'user.already_verified']);
});

it('throttles resending to three per ten minutes per user', function (): void {
    Notification::fake();
    useHostUserModel(VerifiableTestUser::class);
    $actor = verifier();
    $target = makeUser('t@example.test');
    RateLimiter::clear("access-verify:{$target->id}");

    foreach (range(1, 3) as $_) {
        $this->actingAs($actor)->postJson("/api/access/users/{$target->id}/resend-verification")->assertOk();
    }

    $response = $this->actingAs($actor)->postJson("/api/access/users/{$target->id}/resend-verification")->assertStatus(429)
        ->assertJson(['code' => 'too_many_requests']);
    expect($response->json('errors.retry_after.0'))->toBeGreaterThan(0);
});

it('refuses a non-owner verifying an owner and answers 401 and 403 in the envelope', function (): void {
    makeOwner();
    $owner = makeOwner('o2@example.test');

    $this->actingAs(verifier())->postJson("/api/access/users/{$owner->id}/verify-email")->assertForbidden()
        ->assertJson(['code' => 'guardrail.target_above_actor']);

    $target = makeUser('t@example.test');
    foreach (['verify-email', 'resend-verification'] as $uri) {
        auth()->forgetGuards();
        $this->postJson("/api/access/users/{$target->id}/{$uri}")->assertUnauthorized();
        $this->actingAs(usersActor(['access.users.view'], 'v2@example.test'))->postJson("/api/access/users/{$target->id}/{$uri}")
            ->assertForbidden()->assertJson(['code' => 'forbidden']);
    }
});
