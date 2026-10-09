<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Queries;

use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Support\ListDefinition;
use Enadstack\LaravelRoles\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Pagination\LengthAwarePaginator;

final class RolesQuery
{
    public static function definition(): ListDefinition
    {
        return new ListDefinition(
            filters: [
                'type' => static function (Builder $query, mixed $value): void {
                    if ($value === 'system' || $value === 'custom') {
                        $query->where('is_system', $value === 'system');
                    }
                },
                'status' => static function (Builder $query, mixed $value): void {
                    match ($value) {
                        'trashed' => $query->withoutGlobalScope(SoftDeletingScope::class)->whereNotNull('deleted_at'),
                        'all' => $query->withoutGlobalScope(SoftDeletingScope::class),
                        default => null,
                    };
                },
                'default' => static function (Builder $query, mixed $value): void {
                    $flag = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($flag !== null) {
                        $query->where('is_default', $flag);
                    }
                },
            ],
            sorts: [
                'name' => 'name',
                'members' => 'members_count',
                'permissions' => 'permissions_count',
                'updated' => 'updated_at',
                'sort_order' => 'sort_order',
            ],
            includes: ['permissions'],
            searchable: ['name'],
            defaultSort: 'sort_order',
            translatedSearchable: ['label', 'description'],
        );
    }

    /** @return LengthAwarePaginator<int, Model> */
    public static function paginate(ListQuery $query): LengthAwarePaginator
    {
        /** @var Builder<Model> $builder */
        $builder = Role::query()->withCount(['users as members_count', 'permissions as permissions_count']);
        $query->apply($builder);

        if ($query->sort === 'sort_order') {
            $builder->reorder()->orderBy('sort_order')->orderBy('name')->orderBy($builder->getModel()->getQualifiedKeyName());
        }

        return $query->paginate($builder);
    }

    public static function detail(Role $role): Role
    {
        return $role->loadCount(['users as members_count', 'permissions as permissions_count'])->load('permissions');
    }
}
