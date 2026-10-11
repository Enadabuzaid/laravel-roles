<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Audit\Http\Controllers;

use Enadstack\LaravelRoles\Audit\Http\Requests\AuditListRequest;
use Enadstack\LaravelRoles\Audit\Http\Resources\AuditEntryDetailResource;
use Enadstack\LaravelRoles\Audit\Http\Resources\AuditEntryResource;
use Enadstack\LaravelRoles\Audit\Queries\AuditActorsQuery;
use Enadstack\LaravelRoles\Audit\Queries\AuditEntryQuery;
use Enadstack\LaravelRoles\Audit\Queries\AuditLogQuery;
use Enadstack\LaravelRoles\Audit\Queries\AuditOverviewQuery;
use Enadstack\LaravelRoles\Audit\Support\AuditFilters;
use Enadstack\LaravelRoles\Support\ApiResponse;
use Enadstack\LaravelRoles\Support\ListDefinition;
use Enadstack\LaravelRoles\Support\ListQuery;
use Illuminate\Http\JsonResponse;

final class AuditLogController
{
    public function index(AuditListRequest $request): JsonResponse
    {
        $filters = AuditFilters::from($request);
        $paging = ListQuery::fromRequest($request, new ListDefinition(defaultSort: '-id'));
        $page = AuditLogQuery::paginate($filters, $paging->perPage, $paging->page);

        return ApiResponse::paginated($page, AuditEntryResource::class, [
            'filters' => (object) AuditFilters::toArray($filters),
            'sort' => '-created_at',
        ]);
    }

    public function show(AuditListRequest $request, string $entry): JsonResponse
    {
        return ApiResponse::success(AuditEntryDetailResource::make(AuditEntryQuery::find($entry))->resolve($request));
    }

    public function overview(AuditListRequest $request): JsonResponse
    {
        return ApiResponse::success(AuditOverviewQuery::summary(AuditFilters::from($request)));
    }

    public function actors(AuditListRequest $request): JsonResponse
    {
        return ApiResponse::success(AuditActorsQuery::list(AuditFilters::from($request)));
    }
}
