<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Support\ApiResponse;
use Illuminate\Pagination\LengthAwarePaginator;

it('builds the success envelope', function () {
    $response = ApiResponse::success(['a' => 1], 'Done');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toBe([
            'success' => true, 'message' => 'Done', 'data' => ['a' => 1], 'meta' => [],
        ]);
});

it('serializes empty meta and errors as objects', function () {
    expect(ApiResponse::success()->getContent())->toContain('"meta":{}')
        ->and(ApiResponse::error('Nope', 'x.y', 409)->getContent())->toContain('"errors":{}');
});

it('builds created, no-content and error responses', function () {
    expect(ApiResponse::created(['id' => 1], 'Made')->getStatusCode())->toBe(201)
        ->and(ApiResponse::noContent()->getStatusCode())->toBe(204);

    $error = ApiResponse::error('At least one Owner must remain.', 'guardrail.last_owner', 403);

    expect($error->getStatusCode())->toBe(403)
        ->and($error->getData(true))->toBe([
            'success' => false,
            'message' => 'At least one Owner must remain.',
            'code' => 'guardrail.last_owner',
            'errors' => [],
        ]);
});

it('builds the paginated envelope', function () {
    $paginator = new LengthAwarePaginator([['id' => 1], ['id' => 2]], 5, 2, 1);

    $data = ApiResponse::paginated($paginator, null, ['sort' => '-id'])->getData(true);

    expect($data['success'])->toBeTrue()
        ->and($data['message'])->toBeNull()
        ->and($data['data'])->toBe([['id' => 1], ['id' => 2]])
        ->and($data['meta']['pagination'])->toBe(['current_page' => 1, 'per_page' => 2, 'total' => 5, 'last_page' => 3])
        ->and($data['meta']['sort'])->toBe('-id');
});

it('returns an empty page beyond the last page without failing', function () {
    $paginator = new LengthAwarePaginator([], 5, 2, 9);

    expect(ApiResponse::paginated($paginator)->getData(true)['data'])->toBe([]);
});
