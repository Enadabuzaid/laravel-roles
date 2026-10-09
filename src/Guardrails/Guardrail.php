<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Guardrails;

use Enadstack\LaravelRoles\Support\Exceptions\AccessException;

interface Guardrail
{
    /** @throws AccessException */
    public function check(Operation $operation): void;
}
