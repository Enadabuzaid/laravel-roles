<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Doctor;

final class CheckResult
{
    private function __construct(
        public readonly CheckStatus $status,
        public readonly string $message,
    ) {}

    public static function pass(string $message): self
    {
        return new self(CheckStatus::Pass, $message);
    }

    public static function warn(string $message): self
    {
        return new self(CheckStatus::Warn, $message);
    }

    public static function fail(string $message): self
    {
        return new self(CheckStatus::Fail, $message);
    }
}
