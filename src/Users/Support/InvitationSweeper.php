<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Support;

use Enadstack\LaravelRoles\Audit\AuditRecorder;
use Enadstack\LaravelRoles\Audit\Severity;
use Enadstack\LaravelRoles\Guardrails\OwnerCounter;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Roles\Support\RoleRisk;
use Enadstack\LaravelRoles\Users\Models\AccessInvitation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Keeps pending invitations inside their inviter's authority. Call inside the transaction of the write that
 * ended it (an inviter suspended, deactivated, deleted, or demoted). Each sweep is a fixed set of queries
 * however many invitations the inviter has.
 */
final class InvitationSweeper
{
    /** Revokes every PENDING invitation created by `$inviter`; returns how many. */
    public function revokeAllPending(Model $inviter): int
    {
        return AccessInvitation::query()->pending()->where('invited_by', $inviter->getKey())->update(['revoked_at' => now()]);
    }

    /**
     * Revokes the inviter's pending invitations whose role they can no longer assign: the Owner role once they
     * stopped being an owner, any other role with a permission they no longer hold. Owners keep everything.
     *
     * @return int how many were revoked
     */
    public function revokeUnauthorized(Model $inviter): int
    {
        $pending = AccessInvitation::query()->pending()->where('invited_by', $inviter->getKey())->whereNotNull('role_id')->get(['id', 'email', 'role_id']);

        if ($pending->isEmpty() || app(OwnerCounter::class)->isOwner($inviter)) {
            return 0;
        }

        /** @var array<int|string, true> $held */
        $held = method_exists($inviter, 'getAllPermissions')
            ? array_fill_keys($inviter->getAllPermissions()->pluck('name')->all(), true)
            : [];

        $roles = Role::query()->with('permissions:id,name')->whereIn('id', $pending->pluck('role_id')->unique()->all())->get()->keyBy('id');
        $revoke = [];

        foreach ($pending as $invitation) {
            $role = $roles->get($invitation->role_id);

            if (! $role instanceof Role) {
                continue; // a deleted role grants nothing at acceptance
            }

            if (RoleRisk::isOwnerRole($role) || $role->permissions->contains(fn (Model $permission): bool => ! isset($held[(string) $permission->getAttribute('name')]))) {
                $revoke[] = $invitation->getKey();
            }
        }

        if ($revoke === []) {
            return 0;
        }

        $count = AccessInvitation::query()->whereIn('id', $revoke)->whereNull('accepted_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);

        app(AuditRecorder::class)->record(
            'invitations',
            'revoked_unauthorized',
            $inviter,
            null,
            null,
            ['revoked_invitations' => $count, 'invitation_ids' => $revoke],
            Severity::Info,
        );

        return $count;
    }

    /**
     * After a role was trashed or deleted: sweeps every member who has pending invitations.
     *
     * @param  list<int|string>  $memberKeys
     */
    public function revokeUnauthorizedForMembers(array $memberKeys): int
    {
        if ($memberKeys === []) {
            return 0;
        }

        $inviters = AccessInvitation::query()->pending()->whereIn('invited_by', $memberKeys)->distinct()->pluck('invited_by')->all();

        if ($inviters === []) {
            return 0;
        }

        $total = 0;

        foreach (UserLookup::model()->newQuery()->whereKey($inviters)->get() as $inviter) {
            $total += $this->revokeUnauthorized($inviter);
        }

        return $total;
    }

    /**
     * Member keys (users of the configured model) of a role, read from the pivot so trashed roles work.
     *
     * @return list<int|string>
     */
    public function memberKeys(Role $role): array
    {
        $morphKey = (string) config('permission.column_names.model_morph_key', 'model_id');
        $model = UserLookup::model();

        /** @var list<int|string> $keys */
        $keys = DB::table((string) config('permission.table_names.model_has_roles'))
            ->where((string) (config('permission.column_names.role_pivot_key') ?: 'role_id'), $role->getKey())
            ->whereIn('model_type', array_values(array_unique([$model->getMorphClass(), $model::class])))
            ->pluck($morphKey)
            ->all();

        return $keys;
    }
}
