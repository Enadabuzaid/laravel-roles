<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Models;

use Enadstack\LaravelRoles\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property bool $is_system
 * @property bool $is_default
 */
class Role extends SpatieRole
{
    use HasTranslations;
    use SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'label' => 'array',
            'description' => 'array',
            'is_system' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return Attribute<string, never> */
    protected function labelText(): Attribute
    {
        return Attribute::get(fn (): string => $this->translated('label') ?? (string) $this->name);
    }

    /**
     * Route binding only ever resolves rows of the configured guard, trashed ones included when the route allows it.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>|\Illuminate\Database\Eloquent\Relations\Relation<*, *, *>  $query
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Database\Eloquent\Builder<static>|\Illuminate\Database\Eloquent\Relations\Relation<*, *, *>
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query, $value, $field)->where($this->qualifyColumn('guard_name'), (string) config('roles.guard'));
    }
}
