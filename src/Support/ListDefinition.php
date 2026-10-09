<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Support;

use Closure;

final class ListDefinition
{
    /**
     * @param  array<string, string|Closure>  $filters
     * @param  array<string, string>  $sorts
     * @param  list<string>  $includes
     * @param  list<string>  $searchable
     * @param  list<string>  $translatedSearchable
     */
    public function __construct(
        public readonly array $filters = [],
        public readonly array $sorts = [],
        public readonly array $includes = [],
        public readonly array $searchable = [],
        public readonly string $defaultSort = '-id',
        public readonly array $translatedSearchable = [],
    ) {}
}
