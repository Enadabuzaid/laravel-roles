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
 */
class PermissionUIController extends Controller
{
    use AuthorizesRequests;

    protected GuardResolverContract $guardResolver;

    public function __construct(GuardResolverContract $guardResolver)
    {
        $this->guardResolver = $guardResolver;
    }

    /**
     * Display the permissions management dashboard.
     */
    public function dashboard(Request $request): Response
    {
        $this->authorize('viewAny', Permission::class);

        return Inertia::render('LaravelRoles/PermissionsManagement/Index', [
            'config' => $this->getConfig(),
        ]);
    }

    /**
     * Display the permissions index page.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Permission::class);

        return Inertia::render('LaravelRoles/PermissionsManagement/Permissions/Index', [
            'config' => $this->getConfig(),
        ]);
    }

    /**
     * Display the create permission page.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', Permission::class);

        return Inertia::render('LaravelRoles/PermissionsManagement/Permissions/Index', [
            'guards' => $this->guardResolver->availableGuards(),
            'groups' => $this->getAvailableGroups(),
            'config' => $this->getConfig(),
        ]);
    }

    /**
     * Display the show permission page.
     */
    public function show(Request $request, int $id): Response
    {
        $permission = Permission::withTrashed()->findOrFail($id);
        $this->authorize('view', $permission);

        return Inertia::render('LaravelRoles/PermissionsManagement/Permissions/Index', [
            'permissionId' => $id,
            'config' => $this->getConfig(),
        ]);
    }

    /**
     * Display the edit permission page.
     */
    public function edit(Request $request, int $id): Response
    {
        $permission = Permission::withTrashed()->findOrFail($id);
        $this->authorize('update', $permission);

        return Inertia::render('LaravelRoles/PermissionsManagement/Permissions/Index', [
            'permissionId' => $id,
            'guards' => $this->guardResolver->availableGuards(),
            'groups' => $this->getAvailableGroups(),
            'config' => $this->getConfig(),
        ]);
    }

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
