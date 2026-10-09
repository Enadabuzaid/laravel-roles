<?php

declare(strict_types=1);

namespace Tests\Support;

use Enadstack\LaravelRoles\Guardrails\Guardrail;
use Enadstack\LaravelRoles\Guardrails\Operation;

final class HostExtraRule implements Guardrail
{
    public function check(Operation $operation): void {}
}
