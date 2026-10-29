<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Models\Scopes;

use Enadstack\LaravelRoles\Support\TeamContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * With teams on, roles are the current team's plus global ones (team key null).
 * Without a current team only global roles are visible.
 */
/** @implements Scope<Model> */
final class TeamScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! TeamContext::enabled()) {
            return;
        }

        $column = $model->qualifyColumn(TeamContext::key());
        $teamId = TeamContext::get();

        $builder->where(function (Builder $query) use ($column, $teamId): void {
            $query->whereNull($column);

            if ($teamId !== null) {
                $query->orWhere($column, $teamId);
            }
        });
    }
}
