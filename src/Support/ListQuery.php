<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $includes
     */
    private function __construct(
        private readonly ListDefinition $definition,
        public readonly ?string $search,
        public readonly array $filters,
        public readonly string $sort,
        public readonly int $page,
        public readonly int $perPage,
        public readonly array $includes,
    ) {}

    public static function fromRequest(Request $request, ListDefinition $definition): self
    {
        $filters = [];
        $raw = $request->query('filter');

        if (is_array($raw)) {
            foreach ($raw as $key => $value) {
                if (! is_string($key) || ! array_key_exists($key, $definition->filters)) {
                    continue;
                }
                if (is_array($value)) {
                    $value = array_values(array_filter($value, 'is_scalar'));
                }
                if ($value === '' || $value === [] || $value === null || (! is_array($value) && ! is_scalar($value))) {
                    continue;
                }
                $filters[$key] = $value;
            }
        }

        $search = $request->query('search');
        $search = is_string($search) && trim($search) !== '' ? trim($search) : null;

        $sort = $request->query('sort');
        $sort = is_string($sort) && array_key_exists(self::sortKey($sort), self::sortable($definition)) ? $sort : $definition->defaultSort;

        $max = max(1, (int) config('roles.pagination.max', 100));
        $default = max(1, (int) config('roles.pagination.default', 15));
        $perPage = $request->query('per_page');
        $perPage = is_numeric($perPage) ? min(max((int) $perPage, 1), $max) : $default;

        $page = $request->query('page');
        $page = is_numeric($page) ? (int) min(max((float) $page, 1.0), 1_000_000.0) : 1;

        $include = $request->query('include');
        $includes = is_string($include)
            ? array_values(array_unique(array_intersect(array_map('trim', explode(',', $include)), $definition->includes)))
            : [];

        return new self($definition, $search, $filters, $sort, $page, $perPage, $includes);
    }

    /** @param  Builder<Model>  $query
     *  @return Builder<Model> */
    public function apply(Builder $query): Builder
    {
        foreach ($this->filters as $name => $value) {
            $rule = $this->definition->filters[$name];

            if ($rule instanceof Closure) {
                $rule($query, $value);
            } elseif (is_array($value)) {
                $query->whereIn($rule, $value);
            } else {
                $query->where($rule, $value);
            }
        }

        if ($this->search !== null && $this->definition->searchable !== []) {
            $term = '%'.addcslashes($this->search, '%_\\').'%';
            $escapeManually = $query->getQuery()->getGrammar() instanceof SQLiteGrammar;
            $query->where(function (Builder $q) use ($term, $escapeManually): void {
                foreach ($this->definition->searchable as $column) {
                    if ($escapeManually) {
                        $q->orWhere(new EscapedLikeCondition($column))->addBinding($term, 'where');
                    } else {
                        $q->orWhereLike($column, $term);
                    }
                }
            });
        }

        $descending = str_starts_with($this->sort, '-');
        $column = self::sortable($this->definition)[self::sortKey($this->sort)];
        $query->orderBy($column, $descending ? 'desc' : 'asc');

        if ($column !== $query->getModel()->getKeyName()) {
            $query->orderBy($query->getModel()->getQualifiedKeyName());
        }

        if ($this->includes !== []) {
            $query->with($this->includes);
        }

        return $query;
    }

    /** @param  Builder<Model>  $query
     *  @return LengthAwarePaginator<int, Model> */
    public function paginate(Builder $query): LengthAwarePaginator
    {
        return $query->paginate($this->perPage, ['*'], 'page', $this->page);
    }

    /** @return array{filters: object, sort: string} */
    public function meta(): array
    {
        return ['filters' => (object) $this->filters, 'sort' => $this->sort];
    }

    private static function sortKey(string $sort): string
    {
        return str_starts_with($sort, '-') ? substr($sort, 1) : $sort;
    }

    /** @return array<string, string> */
    private static function sortable(ListDefinition $definition): array
    {
        $default = ltrim($definition->defaultSort, '-');

        return $definition->sorts + [$default => $default];
    }
}
