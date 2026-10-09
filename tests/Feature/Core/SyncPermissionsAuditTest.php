<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Core\Actions\SyncPermissions;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Support\Exceptions\AccessException;

beforeEach(function () {
    config(['roles.permissions_file' => __DIR__.'/../../Support/registry/valid.php']);
});

it('writes one permissions.synced entry on a changing sync, none on a re-run', function () {
    app(SyncPermissions::class)->handle();

    $log = ActionLog::sole();
    expect($log->event)->toBe('permissions.synced')->and($log->module)->toBe('permissions')
        ->and($log->subject_type)->toBeNull()->and($log->severity)->toBe('info')
        ->and($log->new_values['counts']['added'])->toBeGreaterThan(0)
        ->and($log->new_values['added'])->not->toBeEmpty();

    ActionLog::query()->delete();
    app(SyncPermissions::class)->handle();
    expect(ActionLog::count())->toBe(0);
});

it('writes nothing on a dry run', function () {
    app(SyncPermissions::class)->handle(dryRun: true);

    expect(ActionLog::count())->toBe(0);
});

it('uses warning severity when a permission was deprecated', function () {
    app(SyncPermissions::class)->handle();
    ActionLog::query()->delete();
    Permission::query()->first()?->update(['deprecated_at' => null]);
    Permission::create(['name' => 'old.thing', 'guard_name' => 'web', 'source' => Permission::SOURCE_REGISTRY]);

    app(SyncPermissions::class)->handle();

    $log = ActionLog::sole();
    expect($log->severity)->toBe('warning')->and($log->new_values['deprecated'])->toContain('old.thing');
});

it('caps listed names at 200 per list', function () {
    app(SyncPermissions::class)->handle();
    ActionLog::query()->delete();
    foreach (range(1, 250) as $i) {
        Permission::create(['name' => "stale.p{$i}", 'guard_name' => 'web', 'source' => Permission::SOURCE_REGISTRY]);
    }

    app(SyncPermissions::class)->handle();

    $log = ActionLog::sole();
    expect($log->new_values['counts']['deprecated'])->toBe(250)->and($log->new_values['deprecated'])->toHaveCount(200);
});

it('fails closed without an actor outside console and writes nothing', function () {
    $context = Mockery::mock(ActorContext::class)->makePartial();
    $context->shouldReceive('actor')->andReturn(null);
    $context->shouldReceive('runningInConsole')->andReturn(false);
    app()->instance(ActorContext::class, $context);

    expect(fn () => app(SyncPermissions::class)->handle())->toThrow(AccessException::class);
    expect(Permission::count())->toBe(0)->and(ActionLog::count())->toBe(0);
});

it('allows the system actor explicitly and the console', function () {
    app(ActorContext::class)->runAs(null, fn () => app(SyncPermissions::class)->handle());

    expect(Permission::count())->toBeGreaterThan(0);
});
