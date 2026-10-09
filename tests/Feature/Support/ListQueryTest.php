<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Support\ListDefinition;
use Enadstack\LaravelRoles\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

it('clamps an absurd page number and still paginates', function () {
    seedUsers(3);

    $lq = listFor(['page' => '99999999999999999999']);

    expect($lq->page)->toBe(1_000_000)
        ->and($lq->paginate($lq->apply(TestUser::query()))->items())->toBe([]);
});

it('matches LIKE wildcards literally and still searches normally', function () {
    seedUsers(3);
    DB::table('users')->insert([
        ['name' => '100% Real', 'email' => 'pct@example.test', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'snake_case', 'email' => 'under@example.test', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $names = fn (string $term) => listFor(['search' => $term])->apply(TestUser::query())->pluck('name')->all();

    expect($names('%'))->toBe(['100% Real'])
        ->and($names('_'))->toBe(['snake_case'])
        ->and($names('User'))->toHaveCount(3);
});

it('accepts a single leading dash for sort and trims and de-duplicates includes', function () {
    expect(listFor(['sort' => '--name'])->sort)->toBe('-id')
        ->and(listFor(['include' => 'roles, roles'])->includes)->toBe(['roles']);

    $def = new ListDefinition(includes: ['roles', 'permissions']);
    $lq = ListQuery::fromRequest(Request::create('/x', 'GET', ['include' => 'roles, permissions']), $def);
    expect($lq->includes)->toBe(['roles', 'permissions']);
});

it('searches jsonb label columns in both locales on every driver', function (string $modelClass, string $table) {
    $rows = [
        ['name' => 'alpha', 'label' => ['en' => 'Order Manager', 'ar' => 'مدير الطلبات']],
        ['name' => 'beta', 'label' => ['en' => 'Viewer', 'ar' => 'مشاهد']],
    ];
    foreach ($rows as $row) {
        DB::table($table)->insert([
            'name' => $row['name'],
            'guard_name' => 'web',
            'label' => json_encode($row['label'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $definition = new ListDefinition(searchable: ['name', 'label']);
    $search = fn (string $term): array => ($lq = ListQuery::fromRequest(Request::create('/x', 'GET', ['search' => $term]), $definition))
        ->apply($modelClass::query())->orderBy('name')->pluck('name')->all();

    expect($search('order manager'))->toBe(['alpha'])
        ->and($search('مدير'))->toBe(['alpha'])
        ->and($search('مشاهد'))->toBe(['beta'])
        ->and($search('viewer'))->toBe(['beta'])
        ->and($search('nothing-here'))->toBe([])
        ->and($search('%'))->toBe([]);
})->with([
    'roles' => [Role::class, 'roles'],
    'permissions' => [Permission::class, 'permissions'],
]);
