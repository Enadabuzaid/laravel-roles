<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Roles\Actions\AssignRoleToUser;
use Enadstack\LaravelRoles\Support\ActorContext;
use Illuminate\Support\Facades\DB;

it('runs a guarded assignment in a constant number of queries regardless of role size', function () {
    makeOwner();
    $actor = makeUser('actor@example.test');
    $names = array_map(fn (int $i): string => "p{$i}.view", range(1, 60));
    makeRole('holder', $names)->users()->attach($actor);
    $actor = $actor->fresh();

    $small = makeRole('small', array_slice($names, 0, 5));
    $large = makeRole('large', $names);

    $run = function (string $email, $role) use ($actor): int {
        $target = makeUser($email);
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(ActorContext::class)->runAs($actor, fn () => app(AssignRoleToUser::class)->handle($target, $role));
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $run('warm@example.test', makeRole('warmup'));

    $smallCount = $run('t1@example.test', $small);
    $largeCount = $run('t2@example.test', $large);

    expect($largeCount)->toBe($smallCount);
});
