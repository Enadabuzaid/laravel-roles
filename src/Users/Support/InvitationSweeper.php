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
    public const MAX_AUDITED_IDS = 100;

    /**
     * Revokes every PENDING invitation created by `$inviter`.
     *
     * @return array{count: int, ids: list<int|string>} ids capped at MAX_AUDITED_IDS for the audit meta
     */
    public function revokeAllPending(Model $inviter): array
    {
        /** @var list<int|string> $ids */
        $ids = AccessInvitation::query()->pending()->where('invited_by', $inviter->getKey())->pluck('id')->all();

        if ($ids === []) {
            return ['count' => 0, 'ids' => []];
        }

        $count = AccessInvitation::query()->whereIn('id', $ids)->whereNull('accepted_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);

        return ['count' => $count, 'ids' => array_slice($ids, 0, self::MAX_AUDITED_IDS)];
    }

    /**
     * The audit meta entries for a revoke-all sweep (empty when nothing was revoked).
     *
     * @param  array{count: int, ids: list<int|string>}  $swept
     * @return array<string, mixed>
     */
    public static function auditMeta(array $swept): array
    {
        return $swept['count'] > 0 ? ['revoked_invitations' => $swept['count'], 'invitation_ids' => $swept['ids']] : [];
    }

    /**
     * Whether `$inviter` may still hand out `$role`: owners always; the Owner role only to owners; any other role
     * only when the inviter holds every one of its permissions (the escalation rule's logic).
     *
     * @param  array<string, true>|null  $held  the inviter's permission names when already known
     */
    public function mayAssign(Model $inviter, Role $role, ?array $held = null, ?bool $isOwner = null): bool
    {
        if ($isOwner ?? app(OwnerCounter::class)->isOwner($inviter)) {
            return true;
        }

        if (RoleRisk::isOwnerRole($role)) {
            return false;
        }

        $held ??= $this->heldPermissions($inviter);
        $role->loadMissing('permissions:id,name');

        return ! $role->permissions->contains(fn (Model $permission): bool => ! isset($held[(string) $permission->getAttribute('name')]));
    }

    /** @return array<string, true> */
    public function heldPermissions(Model $inviter): array
    {
        /** @var array<string, true> $held */
        $held = method_exists($inviter, 'getAllPermissions')
            ? array_fill_keys($inviter->getAllPermissions()->pluck('name')->all(), true)
            : [];

        return $held;
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

        $held = $this->heldPermissions($inviter);
        $roles = Role::query()->with('permissions:id,name')->whereIn('id', $pending->pluck('role_id')->unique()->all())->get()->keyBy('id');
        $revoke = [];

        foreach ($pending as $invitation) {
            $role = $roles->get($invitation->role_id);

            if ($role instanceof Role && ! $this->mayAssign($inviter, $role, $held, false)) {
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
            Severity::Warning,
        );

        return $count;
    }

    /**
     * Keys of the members of `$role` who have pending invitations (a subquery on the role pivot, so the role's
     * size does not matter). Read it BEFORE the pivot rows go away.
     *
     * @return list<int|string>
     */
    public function invitersHolding(Role $role): array
    {
        $morphKey = (string) config('permission.column_names.model_morph_key', 'model_id');
        $model = UserLookup::model();
        $members = DB::table((string) config('permission.table_names.model_has_roles'))
            ->select($morphKey)
            ->where((string) (config('permission.column_names.role_pivot_key') ?: 'role_id'), $role->getKey())
            ->whereIn('model_type', array_values(array_unique([$model->getMorphClass(), $model::class])));

        /** @var list<int|string> $keys */
        $keys = AccessInvitation::query()->pending()->whereIn('invited_by', $members)->distinct()->pluck('invited_by')->all();

        return $keys;
    }

    /**
     * Sweeps each of these inviters (see invitersHolding()).
     *
     * @param  list<int|string>  $inviterKeys
     */
    public function revokeUnauthorizedFor(array $inviterKeys): int
    {
        if ($inviterKeys === []) {
            return 0;
        }

        $total = 0;

        foreach (UserLookup::model()->newQuery()->whereKey($inviterKeys)->get() as $inviter) {
            $total += $this->revokeUnauthorized($inviter);
        }

        return $total;
    }
}
