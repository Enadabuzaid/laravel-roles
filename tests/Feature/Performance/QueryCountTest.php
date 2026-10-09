<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Support\ListDefinition;
use Enadstack\LaravelRoles\Support\ListQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

function countQueries(Closure $callback): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $callback();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('lists roles with a fixed query count regardless of data size', function () {
    Model::preventLazyLoading();

    $list = function (): void {
        $lq = ListQuery::fromRequest(Request::create('/x', 'GET', ['per_page' => 25]), new ListDefinition(sorts: ['name' => 'name'], searchable: ['name']));
        $lq->paginate($lq->apply(Role::query()->withCount('permissions')))->each(fn ($r) => $r->label_text);
    };

    foreach (range(1, 5) as $i) {
        Role::create(['name' => "r{$i}", 'guard_name' => 'web']);
    }
    $small = countQueries($list);

    foreach (range(6, 60) as $i) {
        Role::create(['name' => "r{$i}", 'guard_name' => 'web']);
    }
    $large = countQueries($list);

    expect($large)->toBe($small);
});

it('lists permissions with a fixed query count regardless of data size', function () {
    Model::preventLazyLoading();

    $list = function (): void {
        $lq = ListQuery::fromRequest(Request::create('/x', 'GET', ['per_page' => 25]), new ListDefinition(searchable: ['name']));
        $lq->paginate($lq->apply(Permission::query()))->each(fn ($p) => $p->label_text);
    };

    foreach (range(1, 5) as $i) {
        Permission::create(['name' => "p{$i}.view", 'guard_name' => 'web']);
    }
    $small = countQueries($list);
    foreach (range(6, 80) as $i) {
        Permission::create(['name' => "p{$i}.view", 'guard_name' => 'web']);
    }
    $large = countQueries($list);

    expect($large)->toBe($small);
});
