<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Guardrails;

final class Guardrails
{
    /** @param  list<Guardrail>  $rules */
    public function __construct(private readonly array $rules) {}

    public function assert(Operation $operation): void
    {
        foreach ($this->rules as $rule) {
            $rule->check($operation);
        }
    }
}
