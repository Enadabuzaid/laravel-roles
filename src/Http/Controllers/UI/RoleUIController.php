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
 *
 * @package Enadstack\LaravelRoles\Http\Controllers\UI
 */
class RoleUIController extends Controller
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
     * Display the roles index page.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Role::class);

        return Inertia::render('LaravelRoles/RolesIndex', [
            'config' => $this->getConfig(),
        ]);
    }

    /**
     * Display the create role page.
     *
     * @param Request $request
     * @return Response
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', Role::class);

        return Inertia::render('LaravelRoles/RoleCreate', [
            'guards' => $this->guardResolver->availableGuards(),
            'permissions' => $this->getPermissionsForSelect(),
            'config' => $this->getConfig(),
        ]);
    }

    /**
     * Display the show role page.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function show(Request $request, int $id): Response
    {
        $role = Role::withTrashed()->findOrFail($id);
        $this->authorize('view', $role);

        return Inertia::render('LaravelRoles/RoleShow', [
            'roleId' => $id,
            'config' => $this->getConfig(),
        ]);
    }

    /**
     * Display the edit role page.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function edit(Request $request, int $id): Response
    {
        $role = Role::withTrashed()->findOrFail($id);
        $this->authorize('update', $role);

        return Inertia::render('LaravelRoles/RoleEdit', [
            'roleId' => $id,
            'guards' => $this->guardResolver->availableGuards(),
            'config' => $this->getConfig(),
        ]);
    }

    /**
     * Get permissions for select input.
     *
     * @return array
     */
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
