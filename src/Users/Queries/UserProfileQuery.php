<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Queries;

use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class UserProfileQuery
{
    /**
     * A user with its lock standing, access state, roles (with permission counts) and effective permission count loaded.
     *
     * @throws ModelNotFoundException
     */
    public static function detail(string|int $id, ?Model $actor): Model
    {
        $user = UsersQuery::base()->findOrFail($id);

        if ($actor !== null) {
            UsersQuery::annotate($actor, [$user]);
        }

        $user->setRelation('accessState', UserAccessState::query()->where('user_id', $user->getKey())->first());

        $pivot = (string) config('permission.table_names.model_has_roles', 'model_has_roles');
        $morphKey = (string) config('permission.column_names.model_morph_key', 'model_id');
        $roles = Role::query()
            ->where('guard_name', (string) config('roles.guard'))
            ->whereIn('id', DB::table($pivot)->where('model_type', $user->getMorphClass())->where($morphKey, $user->getKey())->select('role_id'))
            ->withCount('permissions')
            ->orderBy('name')
            ->get();
        $user->setRelation('roles', $roles);

        $user->setAttribute('effective_permissions_count', UserPermissionsQuery::count($user));

        return $user;
    }
}
