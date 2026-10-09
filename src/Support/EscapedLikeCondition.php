<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Support;

use Illuminate\Contracts\Database\Query\ConditionExpression;
use Illuminate\Database\Grammar;

/**
 * A `column like ? escape '\'` condition, used where the driver has no default LIKE escape (SQLite).
 */
final class EscapedLikeCondition implements ConditionExpression
{
    public function __construct(private readonly string $column) {}

    public function getValue(Grammar $grammar): string
    {
        return $grammar->wrap($this->column)." like ? escape '\\'";
    }
}
