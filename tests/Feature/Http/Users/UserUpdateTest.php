<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Tests\Support\SoftTestUser;
use Tests\Support\SoftVerifiableTestUser;
use Tests\Support\TestUser;

function editor(array $extra = []): TestUser
{
    return usersActor(['access.users.view', 'access.users.update', ...$extra], 'editor@example.test');
}

it('updates the name with one info audit entry and the user in the envelope', function (): void {
    $actor = editor();
    $target = makeUser('t@example.test');
    ActionLog::query()->delete();

    $this->actingAs($actor)->patchJson("/api/access/users/{$target->id}", ['name' => '  New Name '])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('meta.verification_sent', false);

    $log = ActionLog::sole();
    expect($log->event)->toBe('users.updated')->and($log->severity)->toBe('info')->and($log->subject_id)->toBe($target->id)
        ->and($log->old_values)->toEqual(['name' => 'A'])->and($log->new_values)->toEqual(['name' => 'New Name']);
});

it('changes the email, clears verification and audits it as a warning', function (): void {
    $actor = editor();
    $target = makeUser('t@example.test');
    $target->forceFill(['email_verified_at' => now()])->save();
    ActionLog::query()->delete();

    $this->actingAs($actor)->patchJson("/api/access/users/{$target->id}", ['email' => ' New@Example.TEST '])->assertOk()
        ->assertJsonPath('data.email', 'new@example.test')
        ->assertJsonPath('data.email_verified_at', null);

    $log = ActionLog::sole();
    expect($log->severity)->toBe('warning')
        ->and($log->old_values)->toEqual(['email' => 't@example.test'])->and($log->new_values)->toEqual(['email' => 'new@example.test']);
});

it('treats unchanged values as a no-op without audit', function (): void {
    $actor = editor();
    $target = makeUser('t@example.test');
    ActionLog::query()->delete();

    $this->actingAs($actor)->patchJson("/api/access/users/{$target->id}", ['name' => 'A', 'email' => 'T@example.test'])->assertOk();

    expect(ActionLog::count())->toBe(0);
});

it('rejects an email used by a live or trashed user in any case', function (): void {
    useHostUserModel(SoftTestUser::class);
    $actor = editor();
    $target = makeUser('t@example.test');
    makeUser('live@example.test');
    makeUser('gone@example.test')->delete();

    foreach (['LIVE@example.test', 'Gone@Example.test'] as $email) {
        $this->actingAs($actor)->patchJson("/api/access/users/{$target->id}", ['email' => $email])->assertStatus(422)
            ->assertJson(['code' => 'validation_failed'])->assertJsonStructure(['errors' => ['email']]);
    }

    expect($target->fresh()->email)->toBe('t@example.test');
});

it('validates the body', function (array $body): void {
    $actor = editor();
    $target = makeUser('t@example.test');

    $this->actingAs($actor)->patchJson("/api/access/users/{$target->id}", $body)->assertStatus(422)->assertJson(['code' => 'validation_failed']);
})->with([
    'empty' => [[]],
    'blank name' => [['name' => '   ']],
    'long name' => [['name' => str_repeat('a', 256)]],
    'bad email' => [['email' => 'nope']],
]);

it('refuses changing your own email but allows your own name', function (): void {
    $actor = editor();

    $this->actingAs($actor)->patchJson("/api/access/users/{$actor->id}", ['email' => 'other@example.test'])->assertStatus(409)
        ->assertJson(['code' => 'user.self_email_change']);
    $this->actingAs($actor)->patchJson("/api/access/users/{$actor->id}", ['name' => 'Me'])->assertOk()->assertJsonPath('data.name', 'Me');
});

it('refuses a non-owner editing an owner', function (): void {
    makeOwner();
    $owner = makeOwner('o2@example.test');
    $actor = editor();
    ActionLog::query()->delete();

    $this->actingAs($actor)->patchJson("/api/access/users/{$owner->id}", ['name' => 'X'])->assertForbidden()
        ->assertJson(['code' => 'guardrail.target_above_actor']);

    expect($owner->fresh()->name)->toBe('A')->and(ActionLog::count())->toBe(0);
});

it('answers 409 user.trashed for a trashed user', function (): void {
    useHostUserModel(SoftTestUser::class);
    $actor = editor();
    $target = makeUser('t@example.test');
    $target->delete();

    $this->actingAs($actor)->patchJson("/api/access/users/{$target->id}", ['name' => 'X'])->assertStatus(409)->assertJson(['code' => 'user.trashed']);
});

it('sends the verification notification only for MustVerifyEmail hosts', function (): void {
    Notification::fake();
    useHostUserModel(SoftVerifiableTestUser::class);
    $actor = editor();
    $target = makeUser('t@example.test');

    $this->actingAs($actor)->patchJson("/api/access/users/{$target->id}", ['email' => 'new@example.test'])->assertOk()
        ->assertJsonPath('meta.verification_sent', true);

    Notification::assertSentTo($target->fresh(), VerifyEmail::class);

    useHostUserModel(TestUser::class);
    Notification::fake();
    $plain = makeUser('p@example.test');
    $this->actingAs($actor)->patchJson("/api/access/users/{$plain->id}", ['email' => 'p2@example.test'])->assertOk()
        ->assertJsonPath('meta.verification_sent', false);
    Notification::assertNothingSent();
});

it('answers 401 and 403 in the envelope', function (): void {
    $target = makeUser('t@example.test');

    $this->patchJson("/api/access/users/{$target->id}", ['name' => 'X'])->assertUnauthorized();
    $this->actingAs(usersActor(['access.users.view']))->patchJson("/api/access/users/{$target->id}", ['name' => 'X'])
        ->assertForbidden()->assertJson(['code' => 'forbidden']);
});
