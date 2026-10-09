<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Core\Doctor\Checks\GuardrailConfigCheck;
use Enadstack\LaravelRoles\Core\Doctor\CheckStatus;
use Enadstack\LaravelRoles\Guardrails\Rules\LastOwnerRule;
use Tests\Support\HostExtraRule;

it('passes with the default rules', function () {
    expect((new GuardrailConfigCheck)->run()->status)->toBe(CheckStatus::Pass);
});

it('fails when a configured rule is not a class implementing Guardrail', function (string $rule) {
    config(['roles.guardrails.rules' => [LastOwnerRule::class, $rule]]);

    $result = (new GuardrailConfigCheck)->run();

    expect($result->status)->toBe(CheckStatus::Fail)->and($result->message)->toContain($rule);
})->with(['Not\\A\\Class', stdClass::class]);

it('accepts a host rule that implements Guardrail', function () {
    config(['roles.guardrails.rules' => [HostExtraRule::class]]);

    expect((new GuardrailConfigCheck)->run()->status)->toBe(CheckStatus::Pass);
});

it('warns about disabled entries that are not built-in rules', function () {
    config(['roles.guardrails.disabled' => ['Typo\\Rule']]);

    $result = (new GuardrailConfigCheck)->run();

    expect($result->status)->toBe(CheckStatus::Warn)->and($result->message)->toContain('Typo\\Rule');
});

it('is part of the default doctor checks', function () {
    expect(config('roles.doctor.checks'))->toContain(GuardrailConfigCheck::class);
});
