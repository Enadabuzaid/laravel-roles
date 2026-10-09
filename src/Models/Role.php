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
}
