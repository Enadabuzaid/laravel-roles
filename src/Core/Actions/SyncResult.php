<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Actions;

final class SyncResult
{
    /**
     * @param  list<string>  $added
     * @param  list<string>  $updated
     * @param  list<string>  $restored
     * @param  list<string>  $deprecated
     * @param  list<string>  $pruned
     */
    public function __construct(
        public readonly array $added = [],
        public readonly array $updated = [],
        public readonly array $restored = [],
        public readonly array $deprecated = [],
        public readonly array $pruned = [],
    ) {}

    public function hasChanges(): bool
    {
        return $this->added !== [] || $this->updated !== [] || $this->restored !== []
            || $this->deprecated !== [] || $this->pruned !== [];
    }
}
