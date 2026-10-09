<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Queries;

use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Support\ListDefinition;
use Enadstack\LaravelRoles\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class PermissionsQuery
{
    private const RISKS = [Permission::RISK_LOW, Permission::RISK_MEDIUM, Permission::RISK_HIGH];

    private const SOURCES = [Permission::SOURCE_REGISTRY, Permission::SOURCE_MANUAL];

    public static function definition(): ListDefinition
    {
        return new ListDefinition(
            filters: [
                'module' => 'module',
                'risk' => static function (Builder $query, mixed $value): void {
                    if (is_string($value) && in_array($value, self::RISKS, true)) {
                        $query->where('risk', $value);
                    }
                },
                'source' => static function (Builder $query, mixed $value): void {
                    if (is_string($value) && in_array($value, self::SOURCES, true)) {
                        $query->where('source', $value);
                    }
                },
                // Applied in paginate(): the default (active) must hold even without the filter.
                'status' => static function (): void {},
                'held' => static function (Builder $query, mixed $value): void {
                    match ($value) {
                        'yes' => $query->whereHas('roles', static fn (Builder $roles) => $roles->where('guard_name', (string) config('roles.guard'))),
                        'no' => $query->whereDoesntHave('roles', static fn (Builder $roles) => $roles->where('guard_name', (string) config('roles.guard'))),
                        default => null,
                    };
                },
            ],
            sorts: [
                'name' => 'name',
                'module' => 'module',
                'risk' => 'risk',
                'roles' => 'roles_count',
                'updated' => 'updated_at',
                'sort_order' => 'sort_order',
            ],
            searchable: ['name'],
            defaultSort: 'sort_order',
            translatedSearchable: ['label', 'description'],
        );
    }

    /** @return LengthAwarePaginator<int, Model> */
    public static function paginate(ListQuery $query): LengthAwarePaginator
    {
        /** @var Builder<Model> $builder */
        $builder = Permission::query()->where('guard_name', (string) config('roles.guard'))->withCount(['roles as roles_count' => static fn (Builder $roles) => $roles->where('guard_name', (string) config('roles.guard'))]);

        $status = $query->filters['status'] ?? 'active';
        match ($status) {
            'all' => null,
            'deprecated' => $builder->whereNotNull('deprecated_at'),
            default => $builder->whereNull('deprecated_at'),
        };

        $query->apply($builder);

        if ($query->sort === 'sort_order') {
            $builder->reorder()->orderBy('sort_order')->orderBy('name')->orderBy($builder->getModel()->getQualifiedKeyName());
        }

        return $query->paginate($builder);
    }

    public static function detail(Permission $permission): Permission
    {
        return $permission->loadCount(['roles as roles_count' => static fn (Builder $roles) => $roles->where('guard_name', (string) config('roles.guard'))]);
    }

    /** @return Collection<int, array{key: string, label: string, permissions_count: int}> */
    public static function modules(): Collection
    {
        return Permission::query()
            ->where('guard_name', (string) config('roles.guard'))
            ->whereNull('deprecated_at')
            ->whereNotNull('module')
            ->selectRaw('module, count(*) as permissions_count')
            ->groupBy('module')
            ->orderBy('module')
            ->toBase()
            ->get()
            ->map(static fn (object $row): array => [
                'key' => (string) $row->module,
                'label' => Str::headline((string) $row->module),
                'permissions_count' => (int) $row->permissions_count,
            ]);
    }
}
