<?php

declare(strict_types=1);

namespace Tests\Support;

use Enadstack\LaravelRoles\Core\Doctor\Check;
use Enadstack\LaravelRoles\Core\Doctor\CheckResult;

final class HostExtraCheck implements Check
{
    public function name(): string
    {
        return 'host-extra';
    }

    public function run(): CheckResult
    {
        return CheckResult::pass('ok');
    }
}
