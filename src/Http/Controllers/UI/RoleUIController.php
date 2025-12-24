<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Http\Controllers\UI;

use Illuminate\Routing\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Contracts\GuardResolverContract;

/**
 * RoleUIController
 *
 * Inertia controller for role management pages.
 */
class RoleUIController extends Controller
{
    use AuthorizesRequests;

    protected GuardResolverContract $guardResolver;

    public function __construct(GuardResolverContract $guardResolver)
    {
        $this->guardResolver = $guardResolver;
    }

    /**
     * Display the roles management dashboard.
     */
    public function dashboard(Request $request): Response
    {
        $this->authorize('viewAny', Role::class);

        return Inertia::render('LaravelRoles/RolesManagement/Index', [
            'config' => $this->getConfig(),
        ]);
    }

    /**
     * Display the roles index page.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Role::class);

        return Inertia::render('LaravelRoles/RolesManagement/Roles/Index', [
            'config' => $this->getConfig(),
        ]);
    }

    /**
     * Display the create role page.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', Role::class);

        return Inertia::render('LaravelRoles/RolesManagement/Roles/Create', [
            'guards' => $this->guardResolver->availableGuards(),
            'permissions' => $this->getPermissionsForSelect(),
            'config' => $this->getConfig(),
        ]);
    }

    /**
     * Display the show/edit role page.
     */
    public function show(Request $request, int $id): Response
    {
        $role = Role::withTrashed()->findOrFail($id);
        $this->authorize('view', $role);

        return Inertia::render('LaravelRoles/RolesManagement/Roles/Edit', [
            'roleId' => $id,
            'guards' => $this->guardResolver->availableGuards(),
            'config' => $this->getConfig(),
        ]);
    }

    /**
     * Display the edit role page.
     */
    public function edit(Request $request, int $id): Response
    {
        $role = Role::withTrashed()->findOrFail($id);
        $this->authorize('update', $role);

        return Inertia::render('LaravelRoles/RolesManagement/Roles/Edit', [
            'roleId' => $id,
            'guards' => $this->guardResolver->availableGuards(),
            'config' => $this->getConfig(),
        ]);
    }

    protected function getPermissionsForSelect(): array
    {
        $guard = $this->guardResolver->guard();

        return Permission::where('guard_name', $guard)
            ->select(['id', 'name', 'group'])
            ->orderBy('group')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    protected function getConfig(): array
    {
        $basePrefix = config('roles.ui.prefix', config('roles.routes.prefix', 'admin/acl'));

        return [
            'prefix' => $basePrefix . '/ui', // UI routes prefix
            'apiPrefix' => $basePrefix, // API routes prefix (no /ui)
            'guard' => $this->guardResolver->guard(),
            'i18n' => config('roles.i18n.enabled', false),
            'locale' => app()->getLocale(),
            'layout' => config('roles.ui.layout', 'AppLayout'),
        ];
    }
}
