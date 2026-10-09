<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $page
 * @property string $name
 * @property array<string, mixed> $filters
 * @property Carbon|null $created_at
 */
class SavedView extends Model
{
    protected $table = 'access_saved_views';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['filters' => 'array'];
    }
}
