<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Guardrails;

use Enadstack\LaravelRoles\Models\Role;
use Illuminate\Database\Eloquent\Model;

final class Operation
{
    /**
     * @param  list<Role>  $added
     * @param  list<Role>  $removed
     */
    public function __construct(
        public readonly OperationType $type,
        public readonly ?Model $actor = null,
        public readonly ?Model $target = null,
        public readonly array $added = [],
        public readonly array $removed = [],
        public readonly ?Role $role = null,
        public readonly ?string $newName = null,
        public readonly bool $confirmed = false,
        public readonly ?string $confirmation = null,
    ) {}

    public function actorIsTarget(): bool
    {
        return $this->actor !== null && $this->target !== null && $this->actor->is($this->target);
    }
}
