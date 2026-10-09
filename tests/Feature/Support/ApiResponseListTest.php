<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Support\ApiResponse;
use Enadstack\LaravelRoles\Support\ListDefinition;
use Enadstack\LaravelRoles\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

function listDefinition(): ListDefinition
{
    return new ListDefinition(filters: ['type' => 'is_system'], sorts: ['name' => 'name'], searchable: ['name']);
}

it('merges list meta, search and extra meta', function (): void {
    $query = ListQuery::fromRequest(Request::create('/x', 'GET', ['search' => 'ab', 'sort' => '-name', 'filter' => ['type' => 'system']]), listDefinition());
    $paginator = new LengthAwarePaginator([['id' => 1], ['id' => 2]], 2, 15, 1);

    $body = ApiResponse::list($query, $paginator, meta: ['extra' => 'yes'], message: 'ok')->getData(true);

    expect($body['data'])->toHaveCount(2)
        ->and($body['message'])->toBe('ok')
        ->and($body['meta']['pagination']['total'])->toBe(2)
        ->and($body['meta']['sort'])->toBe('-name')
        ->and($body['meta']['filters']['type'])->toBe('system')
        ->and($body['meta']['search'])->toBe('ab')
        ->and($body['meta']['extra'])->toBe('yes');
});

it('omits search from meta when absent', function (): void {
    $query = ListQuery::fromRequest(Request::create('/x'), listDefinition());

    $body = ApiResponse::list($query, new LengthAwarePaginator([], 0, 15, 1))->getData(true);

    expect($body['meta'])->not->toHaveKey('search')->toHaveKeys(['pagination', 'filters', 'sort']);
});
