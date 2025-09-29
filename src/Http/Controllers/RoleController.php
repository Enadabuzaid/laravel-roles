<?php
// src/Http/Controllers/RoleController.php
namespace Enadstack\LaravelRoles\Http\Controllers;

use Illuminate\Routing\Controller;
use Enadstack\LaravelRoles\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index() {
        return Role::query()->latest('id')->paginate(20);
    }

    public function store(Request $request) {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'guard_name' => ['nullable','string'],
            'label' => ['nullable','array'],
            'description' => ['nullable','array'],
        ]);
        $data['guard_name'] = $data['guard_name'] ?? config('roles.guard', config('auth.defaults.guard', 'web'));
        return Role::create($data);
    }

    public function show(Role $role) { return $role; }

    public function update(Request $request, Role $role) {
        $data = $request->validate([
            'name' => ['sometimes','string','max:255'],
            'label' => ['nullable','array'],
            'description' => ['nullable','array'],
        ]);
        $role->update($data);
        return $role->refresh();
    }

    public function destroy(Role $role) {
        $role->delete();
        return response()->noContent();
    }
}