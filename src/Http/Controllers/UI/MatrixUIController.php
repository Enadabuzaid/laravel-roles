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
 *
 * @package Enadstack\LaravelRoles\Http\Controllers\UI
 */
class MatrixUIController extends Controller
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
     * Display the permission matrix page.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Role::class);

        return Inertia::render('LaravelRoles/PermissionMatrix', [
            'guard' => $request->query('guard', $this->guardResolver->guard()),
            'config' => $this->getConfig(),
        ]);
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
