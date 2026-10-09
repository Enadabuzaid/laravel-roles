<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Models;

use Enadstack\LaravelRoles\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * @property array<string, string>|null $label
 * @property array<string, string>|null $description
 * @property string|null $module
 * @property string $risk
 * @property int $sort_order
 * @property string $source
 * @property Carbon|null $deprecated_at
 * @property Carbon|null $last_synced_at
 */
class Permission extends SpatiePermission
{
    use HasTranslations;

    public const RISK_LOW = 'low';

    public const RISK_MEDIUM = 'medium';

    public const RISK_HIGH = 'high';

    public const SOURCE_REGISTRY = 'registry';

    public const SOURCE_MANUAL = 'manual';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'label' => 'array',
            'description' => 'array',
            'deprecated_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    /** @param  Builder<static>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('deprecated_at');
    }

    /** @return Attribute<string, never> */
    protected function labelText(): Attribute
    {
        return Attribute::get(fn (): string => $this->translated('label') ?? (string) $this->name);
    }
}
