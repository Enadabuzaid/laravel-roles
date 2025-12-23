<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Http\Controllers\UI;

use Illuminate\Routing\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Contracts\GuardResolverContract;

/**
 * PermissionUIController
 *
 * Inertia controller for permission management pages.
 *
 * @package Enadstack\LaravelRoles\Http\Controllers\UI
 */
class PermissionUIController extends Controller
{
    use AuthorizesRequests;

    /**
     * Guard resolver instance.
     *
     * @var GuardResolverContract
     */
    protected GuardResolverContract $guardResolver;

    /**
     * Create a new controller instance.
     *
     * @param GuardResolverContract $guardResolver
     */
    public function __construct(GuardResolverContract $guardResolver)
    {
        $this->guardResolver = $guardResolver;
    }

    /**
     * Display the permissions index page.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Permission::class);

        return Inertia::render('LaravelRoles/PermissionsIndex', [
            'config' => $this->getConfig(),
        ]);
    }

    /**
     * Display the create permission page.
     *
     * @param Request $request
     * @return Response
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', Permission::class);

        return Inertia::render('LaravelRoles/PermissionCreate', [
            'guards' => $this->guardResolver->availableGuards(),
            'groups' => $this->getAvailableGroups(),
            'config' => $this->getConfig(),
        ]);
    }

    /**
     * Display the show permission page.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function show(Request $request, int $id): Response
    {
        $permission = Permission::withTrashed()->findOrFail($id);
        $this->authorize('view', $permission);

        return Inertia::render('LaravelRoles/PermissionShow', [
            'permissionId' => $id,
            'config' => $this->getConfig(),
        ]);
    }

    /**
     * Display the edit permission page.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function edit(Request $request, int $id): Response
    {
        $permission = Permission::withTrashed()->findOrFail($id);
        $this->authorize('update', $permission);

        return Inertia::render('LaravelRoles/PermissionEdit', [
            'permissionId' => $id,
            'guards' => $this->guardResolver->availableGuards(),
            'groups' => $this->getAvailableGroups(),
            'config' => $this->getConfig(),
        ]);
    }

    /**
     * Get available permission groups.
     *
     * @return array
     */
    protected function getAvailableGroups(): array
    {
        $guard = $this->guardResolver->guard();

        return Permission::where('guard_name', $guard)
            ->whereNotNull('group')
            ->distinct()
            ->pluck('group')
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Get UI configuration.
     *
     * @return array
     */
    protected function getConfig(): array
    {
        return [
            'prefix' => config('roles.ui.prefix', config('roles.routes.prefix', 'admin/acl')),
            'guard' => $this->guardResolver->guard(),
            'i18n' => config('roles.i18n.enabled', false),
            'locale' => app()->getLocale(),
        ];
    }
}
