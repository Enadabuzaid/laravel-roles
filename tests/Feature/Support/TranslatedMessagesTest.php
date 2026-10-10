<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Guardrails\Violation;
use Enadstack\LaravelRoles\Users\Support\InvitationErrors;

afterEach(fn () => app()->setLocale('en'));

it('has an Arabic text for every English guardrail and error key', function (string $file): void {
    $en = require dirname(__DIR__, 3)."/resources/lang/en/{$file}.php";
    $ar = require dirname(__DIR__, 3)."/resources/lang/ar/{$file}.php";
    $keys = static function (array $a) use (&$keys): array {
        $out = [];
        foreach ($a as $k => $v) {
            $out = array_merge($out, is_array($v) ? array_map(fn ($s) => "{$k}.{$s}", $keys($v)) : [(string) $k]);
        }

        return $out;
    };

    expect($keys($ar))->toEqualCanonicalizing($keys($en));
})->with(['guardrail', 'errors', 'invitation', 'user', 'auth']);

it('renders guardrail messages in the request locale', function (string $method, string $key): void {
    app()->setLocale('en');
    $en = Violation::$method()->getMessage();
    app()->setLocale('ar');
    $ar = Violation::$method()->getMessage();

    expect($en)->toBe(trans('access::guardrail.'.$key, [], 'en'))->and($ar)->toBe(trans('access::guardrail.'.$key, [], 'ar'))->and($ar)->not->toBe($en);
})->with([
    ['lastOwner', 'last_owner'], ['selfRoleChange', 'self_role_change'], ['selfDestructive', 'self_destructive'], ['targetAboveActor', 'target_above_actor'],
    ['escalation', 'escalation'], ['highRiskConfirmRequired', 'high_risk_confirm_required'], ['ownerRoleLocked', 'owner_role_locked'],
    ['systemRole', 'system_role'], ['confirmationMismatch', 'confirmation_mismatch'],
]);

it('renders invitation errors in the request locale', function (string $method): void {
    app()->setLocale('en');
    $en = InvitationErrors::$method()->getMessage();
    app()->setLocale('ar');

    expect(InvitationErrors::$method()->getMessage())->not->toBe($en)->and(InvitationErrors::$method()->errorCode)->toStartWith('invitation.');
})->with(['invalid', 'userExists', 'pendingExists', 'notPending']);

it('answers the envelope message in Arabic and English for a guardrail, an invalid transition and an invitation', function (): void {
    makeOwner();
    $actor = usersActor(['access.users.view', 'access.users.suspend', 'access.users.update'], 'actor@example.test');
    $target = makeUser('t@example.test');
    makeState($target, 'suspended');

    foreach (['en', 'ar'] as $locale) {
        app()->setLocale($locale);
        $transition = $this->actingAs($actor)->postJson("/api/access/users/{$target->id}/unlock")->assertStatus(409)->assertJsonPath('code', 'user.invalid_transition');
        expect($transition->json('message'))->toBe(trans('access::errors.invalid_transition', [
            'status' => trans('access::errors.status.suspended', [], $locale), 'action' => trans('access::errors.action.unlocked', [], $locale),
        ], $locale));
        $gone = $this->postJson('/api/access/invitations/accept', ['token' => 'x', 'email' => 'a@b.test', 'name' => 'N', 'password' => 'Str0ng-Passw0rd!x', 'password_confirmation' => 'Str0ng-Passw0rd!x'])->assertStatus(410);
        expect($gone->json('message'))->toBe(trans('access::errors.invitation_invalid', [], $locale));
    }
    expect(trans('access::errors.invitation_invalid', [], 'en'))->not->toBe(trans('access::errors.invitation_invalid', [], 'ar'));
});
