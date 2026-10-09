<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Http\Controllers;

use Enadstack\LaravelRoles\Core\Actions\SyncPermissions;
use Enadstack\LaravelRoles\Core\Http\Requests\SyncPermissionsRequest;
use Enadstack\LaravelRoles\Core\Http\Resources\SyncResultResource;
use Enadstack\LaravelRoles\Core\Registry\PermissionRegistryException;
use Enadstack\LaravelRoles\Support\ApiResponse;
use Enadstack\LaravelRoles\Support\Exceptions\AccessException;
use Illuminate\Http\JsonResponse;

final class SyncPermissionsController
{
    /** The authenticated user is the audit actor (ActorContext falls back to the request user). */
    public function __invoke(SyncPermissionsRequest $request, SyncPermissions $action): JsonResponse
    {
        try {
            $result = $action->handle($request->dryRun(), $request->prune());
        } catch (PermissionRegistryException $e) {
            throw new AccessException($e->getMessage(), 'registry.invalid', 422);
        }

        return ApiResponse::success(SyncResultResource::make($result, $request->dryRun())->resolve());
    }
}
