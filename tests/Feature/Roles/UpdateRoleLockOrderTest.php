<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Roles\Actions\UpdateRole;
use Enadstack\LaravelRoles\Support\ActorContext;
use Illuminate\Support\Facades\DB;

it('locks the super role first, then the target and previous default in ascending id order', function (): void {
    makeRole('owner', [], ['is_system' => true]);
    $previous = makeRole('previous', [], ['is_default' => true]);
    $target = makeRole('target');

    DB::flushQueryLog();
    DB::enableQueryLog();
    app(ActorContext::class)->runAs(null, fn () => app(UpdateRole::class)->handle($target, ['is_default' => true]));
    $locks = array_values(array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains(strtolower($query['query']), 'for update')));
    DB::disableQueryLog();

    expect($locks)->not->toBeEmpty()
        ->and($locks[0]['bindings'])->toContain('owner')
        ->and($locks[1]['query'])->toContain('in (')->toContain('order by')
        ->and($locks[1]['bindings'])->toContain($previous->id, $target->id);
    expect($previous->fresh()->is_default)->toBeFalse()->and($target->fresh()->is_default)->toBeTrue();
})->skip(fn (): bool => DB::connection()->getDriverName() === 'sqlite', 'sqlite has no row locks');
