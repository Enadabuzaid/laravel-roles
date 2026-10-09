<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestUser;

afterEach(fn () => Model::preventLazyLoading(false));

function usersRequestCount(mixed $test, mixed $actor, string $url): int
{
    $test->actingAs($actor);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $test->getJson($url)->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('lists users with the same number of queries for 5 and 200 users, with roles, states and locks', function (): void {
    Model::preventLazyLoading();
    makeOwner();
    $actor = usersActor(['access.users.view', 'reports.view']);
    $roles = [makeRole('a', ['reports.view']), makeRole('b', ['billing.view']), makeRole('c')];

    $populate = function (int $count) use ($roles): void {
        for ($i = 0; $i < $count; $i++) {
            $user = TestUser::create(['name' => 'U'.uniqid(), 'email' => uniqid().'@example.test', 'password' => 'x']);
            $user->assignRole($roles[$i % 3]);
            if ($i % 2 === 0) {
                makeState($user, 'suspended', ['last_login_at' => now()]);
            }
        }
    };

    $url = '/api/access/users?per_page=100&include=roles&sort=-last_login&filter[mfa]=off&search=U';

    $populate(5);
    usersRequestCount($this, $actor, $url);
    $small = usersRequestCount($this, $actor, $url);
    $populate(195);
    $large = usersRequestCount($this, $actor, $url);

    expect($large)->toBe($small)->and($large)->toBeLessThanOrEqual(12);
    $this->actingAs($actor)->getJson($url)->assertOk()->assertJsonCount(100, 'data');
});
