<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Actions;

use Enadstack\LaravelRoles\Audit\AuditRecorder;
use Enadstack\LaravelRoles\Audit\Severity;
use Enadstack\LaravelRoles\Guardrails\Guardrails;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType;
use Enadstack\LaravelRoles\Guardrails\OwnerCounter;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Support\Exceptions\AccessException;
use Enadstack\LaravelRoles\Users\Models\AccessInvitation;
use Enadstack\LaravelRoles\Users\Models\SavedView;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Deletes the user through the host model (soft-deleted when the model uses SoftDeletes) and cleans the access rows
 * that point at it: role and permission pivots of this model, its state row and saved views. Invitations and
 * state rows it created are kept with `invited_by` set to null. The audit entry has no subject (the row is gone)
 * and carries the id, name and email as old attributes.
 */
final class DeleteUser extends UserLifecycleAction
{
    public function handle(Model $user, ?string $confirmation): void
    {
        app(ActorContext::class)->assertKnownActor();

        try {
            $this->transaction(function () use ($user, $confirmation): void {
                $target = $this->lockTarget($user);

                app(Guardrails::class)->assert(new Operation(
                    OperationType::DeleteUser,
                    actor: app(ActorContext::class)->actor(),
                    target: $target,
                    confirmation: $confirmation,
                ));

                $old = [
                    'id' => $target->getKey(),
                    'name' => $target->getAttribute('name'),
                    'email' => $target->getAttribute('email'),
                ];
                $isOwner = app(OwnerCounter::class)->isOwner($target);

                $this->detachPivots($target);
                UserAccessState::query()->where('user_id', $target->getKey())->delete();
                SavedView::query()->where('user_id', $target->getKey())->delete();
                AccessInvitation::query()->where('invited_by', $target->getKey())->update(['invited_by' => null]);
                UserAccessState::query()->where('invited_by', $target->getKey())->update(['invited_by' => null]);

                $target->delete();
                $this->revokeAccessAfterCommit($target);

                app(AuditRecorder::class)->record('users', 'deleted', null, $old, null, ['was_owner' => $isOwner], Severity::Critical);
            });
        } catch (QueryException $e) {
            // A host foreign key still points at the user: nothing was deleted (the transaction rolled back).
            if (str_starts_with((string) $e->getCode(), '23')) {
                throw new AccessException((string) trans('access::user.in_use'), 'user.in_use', 409);
            }

            throw $e;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Only rows of this model type: another model sharing the numeric id keeps its roles. */
    private function detachPivots(Model $user): void
    {
        $morphKey = (string) config('permission.column_names.model_morph_key', 'model_id');
        $types = array_values(array_unique([$user->getMorphClass(), $user::class]));

        foreach (['model_has_roles', 'model_has_permissions'] as $pivot) {
            DB::table((string) config("permission.table_names.{$pivot}", $pivot))
                ->whereIn('model_type', $types)
                ->where($morphKey, $user->getKey())
                ->delete();
        }
    }
}
