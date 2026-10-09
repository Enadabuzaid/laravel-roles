<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Actions;

use Enadstack\LaravelRoles\Audit\AuditRecorder;
use Enadstack\LaravelRoles\Audit\Severity;
use Enadstack\LaravelRoles\Contracts\AccessUser;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Roles\Actions\AssignRoleToUser;
use Enadstack\LaravelRoles\Support\Action;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Users\Enums\UserStatus;
use Enadstack\LaravelRoles\Users\Models\AccessInvitation;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Enadstack\LaravelRoles\Users\Support\InvitationErrors;
use Enadstack\LaravelRoles\Users\Support\InvitationToken;
use Enadstack\LaravelRoles\Users\Support\UserLookup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;
use SensitiveParameter;

/**
 * Public acceptance: turns a usable invitation into an active user holding exactly the invited role.
 *
 * One transaction, the invitation row locked first. Every way a token can be unusable (unknown, used,
 * revoked, expired, wrong email) throws the same 410. An existing account is never touched (409). The
 * state row is Active BEFORE the role is assigned and before anything could log the user in. The role goes
 * through AssignRoleToUser as the system actor with the confirmation the inviter already gave.
 */
final class AcceptInvitation extends Action
{
    /** @param  array{name: string, password: string}  $profile */
    public function handle(#[SensitiveParameter] string $token, string $email, #[SensitiveParameter] array $profile): Model
    {
        try {
            return $this->transaction(fn (): Model => $this->accept($token, $email, $profile));
        } catch (UniqueConstraintViolationException) {
            throw InvitationErrors::userExists();
        }
    }

    /** @param  array{name: string, password: string}  $profile */
    private function accept(string $token, string $email, array $profile): Model
    {
        $invitation = AccessInvitation::query()->where('token_hash', InvitationToken::hash($token))->lockForUpdate()->first();
        $email = UserLookup::normalize($email);

        if ($invitation === null || ! InvitationToken::matches($invitation->token_hash, $token) || ! $invitation->isUsable() || $invitation->email !== $email) {
            throw InvitationErrors::invalid();
        }

        if (UserLookup::emailTaken($email)) {
            throw InvitationErrors::userExists();
        }

        $user = UserLookup::model();
        $user->forceFill([
            'name' => $profile['name'],
            'email' => $email,
            'password' => Hash::make($profile['password']),
            'email_verified_at' => now(),
        ])->save();

        UserAccessState::query()->create([
            'user_id' => $user->getKey(),
            'status' => UserStatus::Active,
            'activated_at' => now(),
            'invited_by' => $invitation->invited_by,
            'invited_at' => $invitation->created_at,
        ]);

        $role = $invitation->role_id === null ? null : Role::query()->where('guard_name', (string) config('roles.guard'))->find($invitation->role_id);
        $actors = app(ActorContext::class);

        if ($role !== null && $user instanceof AccessUser) {
            $actors->runAs(null, static fn () => app(AssignRoleToUser::class)->handle($user, $role, true));
        }

        $invitation->update(['accepted_at' => now()]);

        $actors->runAs($user, function () use ($invitation, $user, $role): void {
            $audit = app(AuditRecorder::class);
            $audit->record(
                'invitations',
                'accepted',
                $invitation,
                ['status' => 'pending'],
                ['status' => 'accepted'],
                ['email' => $invitation->email, 'role' => $role?->name],
                Severity::Info,
            );
            $audit->record(
                'users',
                'activated',
                $user,
                null,
                ['status' => UserStatus::Active->value],
                ['via' => 'invitation', 'invitation_id' => $invitation->getKey(), 'role' => $role?->name],
                Severity::Info,
            );
        });

        return $user;
    }
}
