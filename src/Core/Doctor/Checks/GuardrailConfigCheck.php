<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Doctor\Checks;

use Enadstack\LaravelRoles\Core\Doctor\Check;
use Enadstack\LaravelRoles\Core\Doctor\CheckResult;
use Enadstack\LaravelRoles\Guardrails\Guardrail;

final class GuardrailConfigCheck implements Check
{
    public function name(): string
    {
        return 'guardrail-config';
    }

    public function run(): CheckResult
    {
        $invalid = [];

        foreach ((array) config('roles.guardrails.rules', []) as $rule) {
            if (! is_string($rule) || ! class_exists($rule) || ! is_subclass_of($rule, Guardrail::class)) {
                $invalid[] = is_string($rule) ? $rule : get_debug_type($rule);
            }
        }

        if ($invalid !== []) {
            return CheckResult::fail('Guardrail rules that are not classes implementing Guardrail: '.implode(', ', $invalid).'.');
        }

        $package = require __DIR__.'/../../../../config/roles.php';
        $known = (array) ($package['guardrails']['rules'] ?? []);
        $unknown = array_values(array_filter(
            array_map(static fn (mixed $rule): string => is_string($rule) ? $rule : get_debug_type($rule), (array) config('roles.guardrails.disabled', [])),
            static fn (string $rule): bool => ! in_array($rule, $known, true),
        ));

        if ($unknown !== []) {
            return CheckResult::warn('Disabled guardrails that are not built-in rules (no effect): '.implode(', ', $unknown).'.');
        }

        return CheckResult::pass('Guardrail rules are valid.');
    }
}
