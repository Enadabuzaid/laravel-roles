<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Support\ListDefinition;
use Enadstack\LaravelRoles\Support\ListQuery;
use Illuminate\Http\Request;
use Tests\Support\TestUser;

function userDefinition(): ListDefinition
{
    return new ListDefinition(
        filters: ['name' => 'name', 'domain' => fn ($q, $v) => $q->where('email', 'like', "%@{$v}")],
        sorts: ['name' => 'name', 'created' => 'created_at'],
        includes: ['roles'],
        searchable: ['name', 'email'],
        defaultSort: '-id',
    );
}

function listFor(array $query): ListQuery
{
    return ListQuery::fromRequest(Request::create('/x', 'GET', $query), userDefinition());
}

it('applies whitelisted filters, search and sort', function () {
    seedUsers(30);

    $lq = listFor(['filter' => ['name' => 'User 7'], 'sort' => '-name']);
    $page = $lq->paginate($lq->apply(TestUser::query()));

    expect($page->total())->toBe(1)->and($page->items()[0]->name)->toBe('User 7')
        ->and($lq->meta()['sort'])->toBe('-name');

    $lq = listFor(['search' => 'user1']);
    expect($lq->apply(TestUser::query())->count())->toBe(11); // User 1, 10..19
});

it('supports closure filters and array filters', function () {
    seedUsers(5);

    $lq = listFor(['filter' => ['domain' => 'example.test']]);
    expect($lq->apply(TestUser::query())->count())->toBe(5);

    $lq = listFor(['filter' => ['name' => ['User 1', 'User 2']]]);
    expect($lq->apply(TestUser::query())->count())->toBe(2);
});

it('ignores unknown filters, sorts and includes', function () {
    seedUsers(3);

    $lq = listFor(['filter' => ['secret' => 'x'], 'sort' => 'password', 'include' => 'roles,bogus']);

    expect($lq->sort)->toBe('-id')
        ->and($lq->filters)->toBe([])
        ->and($lq->includes)->toBe(['roles'])
        ->and($lq->apply(TestUser::query())->count())->toBe(3);
});

it('clamps pagination input and never fails on garbage', function () {
    seedUsers(150);

    expect(listFor(['per_page' => 9999])->perPage)->toBe(100)
        ->and(listFor(['per_page' => 'abc'])->perPage)->toBe(15)
        ->and(listFor(['per_page' => 0])->perPage)->toBe(1)
        ->and(listFor(['page' => -3])->page)->toBe(1)
        ->and(listFor(['page' => 'x'])->page)->toBe(1)
        ->and(listFor(['search' => ['a']])->search)->toBeNull()
        ->and(listFor(['sort' => ''])->sort)->toBe('-id');
});

it('treats LIKE wildcards in search literally', function () {
    seedUsers(3);

    expect(listFor(['search' => '%'])->apply(TestUser::query())->count())->toBe(0);
});
