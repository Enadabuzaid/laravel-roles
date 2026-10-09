<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Roles\Actions\CreateRole;
use Enadstack\LaravelRoles\Roles\Actions\ForceDeleteRole;
use Enadstack\LaravelRoles\Roles\Actions\RestoreRole;
use Enadstack\LaravelRoles\Roles\Actions\TrashRole;
use Enadstack\LaravelRoles\Roles\Actions\UpdateRole;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Support\Exceptions\AccessException;

function systemRun(Closure $callback): mixed
{
    return app(ActorContext::class)->runAs(null, $callback);
}

it('runs the full lifecycle as the system actor with one audit entry per write', function (): void {
    $role = systemRun(fn () => app(CreateRole::class)->handle(['name' => 'life', 'label' => ['en' => 'Life']]));
    $role = systemRun(fn () => app(UpdateRole::class)->handle($role, ['color' => '#123456']));
    $role = systemRun(fn () => app(TrashRole::class)->handle($role));
    $role = systemRun(fn () => app(RestoreRole::class)->handle($role));
    systemRun(fn () => app(TrashRole::class)->handle($role));
    systemRun(fn () => app(ForceDeleteRole::class)->handle($role->refresh(), 'life'));

    expect(ActionLog::orderBy('id')->pluck('event')->all())->toBe(['roles.created', 'roles.updated', 'roles.trashed', 'roles.restored', 'roles.trashed', 'roles.deleted']);
});

it('fails closed without an actor outside console', function (): void {
    $role = makeRole('x');
    $context = Mockery::mock(ActorContext::class)->makePartial();
    $context->shouldReceive('actor')->andReturn(null);
    $context->shouldReceive('runningInConsole')->andReturn(false);
    app()->instance(ActorContext::class, $context);

    expect(fn () => app(UpdateRole::class)->handle($role, ['color' => '#111111']))->toThrow(AccessException::class);
    expect(fn () => app(TrashRole::class)->handle($role))->toThrow(AccessException::class);
});

it('maps conflicts and guardrails to access exceptions', function (): void {
    makeRole('taken')->delete();
    $live = makeRole('live');
    $sys = makeRole('sys', [], ['is_system' => true]);

    $code = function (Closure $fn): string {
        try {
            systemRun($fn);
        } catch (AccessException $e) {
            return $e->errorCode;
        }

        return 'none';
    };

    expect($code(fn () => app(CreateRole::class)->handle(['name' => 'taken', 'label' => ['en' => 'x']])))->toBe('role.name_taken_trashed');
    expect($code(fn () => app(RestoreRole::class)->handle($live)))->toBe('role.not_trashed');
    expect($code(fn () => app(ForceDeleteRole::class)->handle($live, 'live')))->toBe('role.not_trashed');
    expect($code(fn () => app(UpdateRole::class)->handle($sys, ['name' => 'renamed'])))->toBe('guardrail.system_role');
    expect($code(fn () => app(TrashRole::class)->handle($sys)))->toBe('guardrail.system_role');
    expect(ActionLog::count())->toBe(0);
});
