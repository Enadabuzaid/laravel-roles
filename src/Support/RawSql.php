<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Support;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

/**
 * A raw SQL fragment assembled by the package from wrapped identifiers and `?` placeholders only
 * (never from request input); values are always bound.
 */
final class RawSql implements Expression
{
    public function __construct(private readonly string $sql) {}

    public function getValue(Grammar $grammar): string
    {
        return $this->sql;
    }
}
