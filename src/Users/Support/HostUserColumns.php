<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Support;

use Illuminate\Database\Eloquent\Model;

/** Which optional columns the host user table has; each table and column is checked once per (scoped) instance. */
final class HostUserColumns
{
    /** @var array<string, bool> */
    private array $known = [];

    public function has(Model $model, string $column): bool
    {
        $key = $model->getConnectionName().'|'.$model->getTable().'.'.$column;

        return $this->known[$key] ??= $model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), $column);
    }
}
