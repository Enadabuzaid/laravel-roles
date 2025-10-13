<?php
// src/Http/Controllers/PermissionController.php
namespace Enadstack\LaravelRoles\Http\Controllers;

use Illuminate\Routing\Controller;
use Enadstack\LaravelRoles\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;


class PermissionController extends Controller
{
    public function index(Request $request)
    {
        $q       = trim((string) $request->query('q', ''));
        $group   = $request->query('group');
        $sort    = in_array($request->query('sort'), ['id','name','group','created_at'], true) ? $request->query('sort') : 'id';
        $dir     = strtolower($request->query('dir')) === 'asc' ? 'asc' : 'desc';
        $perPage = (int) $request->query('per_page', 20);
        $perPage = ($perPage > 0 && $perPage <= 100) ? $perPage : 20;
        $guard   = $request->query('guard', config('roles.guard', config('auth.defaults.guard')));

        $query = Permission::query()->where('guard_name', $guard);

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%");
                if (Schema::hasColumn('permissions', 'description')) {
                    $sub->orWhere('description', 'like', "%{$q}%");
                }
                if (Schema::hasColumn('permissions', 'label')) {
                    $sub->orWhere('label', 'like', "%{$q}%");
                }
                if (Schema::hasColumn('permissions', 'group')) {
                    $sub->orWhere('group', 'like', "%{$q}%");
                }
            });
        }

        if ($group && Schema::hasColumn('permissions', 'group')) {
            $query->where('group', $group);
        }

        $query->orderBy($sort, $dir);

        return $query->paginate($perPage);
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