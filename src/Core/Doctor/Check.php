<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Doctor;

interface Check
{
    public function name(): string;

    public function run(): CheckResult;
}
