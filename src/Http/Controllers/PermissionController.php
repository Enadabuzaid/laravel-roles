<?php
// src/Http/Controllers/PermissionController.php
namespace Enadstack\LaravelRoles\Http\Controllers;

use Illuminate\Routing\Controller;
use Enadstack\LaravelRoles\Models\Permission;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index() {
        return Permission::query()->latest('id')->paginate(20);
    }

    public function store(Request $request) {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'guard_name' => ['nullable','string'],
            'group' => ['nullable','string','max:255'],
            'label' => ['nullable','array'],
            'description' => ['nullable','array'],
            'group_label' => ['nullable','array'],
        ]);
        $data['guard_name'] = $data['guard_name'] ?? config('roles.guard', config('auth.defaults.guard', 'web'));
        return Permission::create($data);
    }

    public function show(Permission $permission) { return $permission; }

    public function update(Request $request, Permission $permission) {
        $data = $request->validate([
            'name' => ['sometimes','string','max:255'],
            'group' => ['nullable','string','max:255'],
            'label' => ['nullable','array'],
            'description' => ['nullable','array'],
            'group_label' => ['nullable','array'],
        ]);
        $permission->update($data);
        return $permission->refresh();
    }

    public function destroy(Permission $permission) {
        $permission->delete();
        return response()->noContent();
    }

    // helpful for UI: return groups with their permissions
    public function groups() {
        return Permission::query()
            ->select(['group', 'group_label', 'name', 'label'])
            ->orderBy('group')
            ->orderBy('name')
            ->get()
            ->groupBy('group')
            ->map(fn($items) => [
                'label' => optional($items->first())->group_label,
                'permissions' => $items->map(fn($p) => ['name' => $p->name, 'label' => $p->label])->values()
            ]);
    }
}