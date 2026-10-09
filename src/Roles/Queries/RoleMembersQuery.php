<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Queries;

use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Support\ListDefinition;
use Enadstack\LaravelRoles\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class RoleMembersQuery
{
    public static function definition(): ListDefinition
    {
        return new ListDefinition(
            sorts: ['name' => 'name', 'email' => 'email'],
            searchable: ['name', 'email'],
            defaultSort: 'name',
        );
    }

    /** @return LengthAwarePaginator<int, Model> */
    public static function paginate(Role $role, ListQuery $query): LengthAwarePaginator
    {
        /** @var class-string<Model> $userModel */
        $userModel = (string) config('roles.user_model');
        $user = new $userModel;
        $morphKey = (string) config('permission.column_names.model_morph_key', 'model_id');

        /** @var Builder<Model> $builder */
        $builder = $userModel::query()->whereIn($user->getQualifiedKeyName(), DB::table((string) config('permission.table_names.model_has_roles'))
            ->select($morphKey)
            ->where('role_id', $role->getKey())
            ->where('model_type', $user->getMorphClass()));

        return $query->paginate($query->apply($builder));
    }
}
