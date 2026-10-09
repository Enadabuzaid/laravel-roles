<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Guardrails\Guardrail;
use Enadstack\LaravelRoles\Guardrails\Guardrails;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType as T;
use Enadstack\LaravelRoles\Guardrails\Rules\LastOwnerRule;
use Enadstack\LaravelRoles\Guardrails\Rules\SystemRoleRule;
use Enadstack\LaravelRoles\Guardrails\Violation;
use Illuminate\Support\Facades\DB;

function rebindGuardrails(array $rules): void
{
    config(['roles.guardrails.rules' => $rules]);
    app()->forgetInstance(Guardrails::class);
}

it('runs only the configured rules', function () {
    rebindGuardrails([SystemRoleRule::class, LastOwnerRule::class]);
    $owner = makeOwner();

    expect(guardrailCode(new Operation(T::SuspendUser, target: $owner)))->toBe('guardrail.last_owner');

    rebindGuardrails([SystemRoleRule::class]);
    expect(guardrailCode(new Operation(T::SuspendUser, target: $owner)))->toBeNull();
});

it('honours custom guardrail classes in config', function () {
    rebindGuardrails([CustomDenyRule::class]);

    expect(guardrailCode(new Operation(T::AssignRole, target: makeUser())))->toBe('guardrail.system_role');
});

it('uses a fixed query count regardless of permission count', function () {
    $count = function (int $permissions): int {
        $names = array_map(fn (int $i): string => "p{$permissions}.n{$i}", range(1, $permissions));
        foreach ($names as $name) {
            makePermission($name);
        }
        $role = makeRole("role-{$permissions}", $names);
        $actor = makeUser("actor{$permissions}@example.test");
        $actor->givePermissionTo($names);
        $target = makeUser("target{$permissions}@example.test");
        $actor = $actor->fresh();

        DB::flushQueryLog();
        DB::enableQueryLog();
        guardrailCode(new Operation(T::AssignRole, actor: $actor, target: $target, added: [$role->fresh()]));
        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $total;
    };

    expect($count(60))->toBe($count(5));
});

final class CustomDenyRule implements Guardrail
{
    public function check(Operation $operation): void
    {
        throw Violation::systemRole();
    }
}
