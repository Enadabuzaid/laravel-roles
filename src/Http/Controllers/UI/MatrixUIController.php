<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Http\Controllers\UI;

use Illuminate\Routing\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Contracts\GuardResolverContract;

/**
 * MatrixUIController
 *
 * Inertia controller for permission matrix page.
 */
class MatrixUIController extends Controller
{
    use AuthorizesRequests;

    protected GuardResolverContract $guardResolver;

    public function __construct(GuardResolverContract $guardResolver)
    {
        $this->guardResolver = $guardResolver;
    }

    /**
     * Display the permission matrix page.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Role::class);

        return Inertia::render('LaravelRoles/PermissionsManagement/PermissionMatrix/Index', [
            'guard' => $request->query('guard', $this->guardResolver->guard()),
            'config' => $this->getConfig(),
        ]);
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
