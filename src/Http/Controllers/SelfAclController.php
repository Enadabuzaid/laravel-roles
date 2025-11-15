<?php

namespace Enadstack\LaravelRoles\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SelfAclController extends Controller
{
    public function roles(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        return $user->roles()->select('id','name')->get();
    }

    public function permissions(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        // return flattened permission names; frontend can map to details if needed
        return [
            'names' => $user->getPermissionNames()->values(),
        ];
    }

    public function abilities(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        return [
            'roles' => $user->roles()->pluck('name'),
            'permissions' => $user->getPermissionNames()->values(),
            'is_super_admin' => method_exists($user, 'hasRole') ? $user->hasRole('super-admin') : false,
        ];
    }
}

