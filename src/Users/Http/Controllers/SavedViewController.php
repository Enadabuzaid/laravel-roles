<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Http\Controllers;

use Enadstack\LaravelRoles\Http\Support\AuthorizesAccess;
use Enadstack\LaravelRoles\Support\ApiResponse;
use Enadstack\LaravelRoles\Users\Actions\CreateSavedView;
use Enadstack\LaravelRoles\Users\Actions\DeleteSavedView;
use Enadstack\LaravelRoles\Users\Http\Requests\DeleteSavedViewRequest;
use Enadstack\LaravelRoles\Users\Http\Requests\ListSavedViewsRequest;
use Enadstack\LaravelRoles\Users\Http\Requests\StoreSavedViewRequest;
use Enadstack\LaravelRoles\Users\Http\Resources\SavedViewResource;
use Enadstack\LaravelRoles\Users\Queries\SavedViewsQuery;
use Illuminate\Http\JsonResponse;

final class SavedViewController
{
    use AuthorizesAccess;

    public function index(ListSavedViewsRequest $request): JsonResponse
    {
        $this->authorizePermission($request, 'access.users.view');

        return ApiResponse::success(SavedViewResource::collection(SavedViewsQuery::forUser((string) $request->user()?->getAuthIdentifier(), $request->page()))->resolve($request));
    }

    public function store(StoreSavedViewRequest $request, CreateSavedView $action): JsonResponse
    {
        $this->authorizePermission($request, 'access.users.view');

        $view = $action->handle((string) $request->user()?->getAuthIdentifier(), $request->page(), $request->viewName(), $request->filters());

        return ApiResponse::created(SavedViewResource::make($view)->resolve($request));
    }

    public function destroy(DeleteSavedViewRequest $request, DeleteSavedView $action): JsonResponse
    {
        $this->authorizePermission($request, 'access.users.view');

        $action->handle($request->view());

        return ApiResponse::noContent();
    }
}
