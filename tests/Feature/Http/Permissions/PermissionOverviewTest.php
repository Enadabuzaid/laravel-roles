<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Models\Permission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

afterEach(function (): void {
    Model::preventLazyLoading(false);
});

function overviewViewer()
{
    $user = makeUser('ov@example.test');
    $user->assignRole(makeRole('ovrole', ['access.permissions.view']));

    return $user;
}

it('returns exact aggregates for a seeded scenario', function (): void {
    config(['roles.super_role' => 'owner']);
    Permission::query()->delete();
    DB::table('role_has_permissions')->delete();
    $viewer = overviewViewer();

    foreach ([['a.one', 'a', 'high'], ['a.two', 'a', 'low'], ['b.one', 'b', 'low'], ['b.two', 'b', 'high']] as [$name, $module, $risk]) {
        makePermission($name, $risk)->forceFill(['module' => $module])->save();
    }
    makePermission('b.manual')->forceFill(['module' => 'b', 'source' => 'manual'])->save();
    makePermission('old.one', 'high')->forceFill(['module' => 'old', 'deprecated_at' => now()])->save();

    makeRole('owner')->givePermissionTo(['a.one', 'a.two']);
    makeRole('staff')->givePermissionTo(['a.one', 'b.one']);
    makeRole('gone')->givePermissionTo(['b.two'])->delete();

    $data = $this->actingAs($viewer)->getJson('/api/access/permissions/overview')->assertOk()->json('data');
    unset($data['owner_only']);

    expect($data)->toBe([
        'total' => 7, 'active' => 6, 'deprecated' => 1, 'high_risk' => 2, 'manual' => 1, 'modules_count' => 2,
        'unused' => 2, 'total_grants' => 5, 'avg_per_role' => 1.67,
        'by_module' => [['module' => 'a', 'count' => 2, 'high_risk' => 1], ['module' => 'b', 'count' => 3, 'high_risk' => 1]],
    ]);
});

it('lists up to five active permissions held by no non-owner role', function (): void {
    config(['roles.super_role' => 'owner']);
    Permission::query()->delete();
    $viewer = overviewViewer();
    makeRole('owner', ['x.1', 'x.2']);
    makeRole('staff', ['x.1']);
    foreach (range(3, 9) as $i) {
        makePermission("x.{$i}");
    }

    $owned = $this->actingAs($viewer)->getJson('/api/access/permissions/overview')->assertOk()->json('data.owner_only');

    expect($owned)->toHaveCount(5)->and(collect($owned)->pluck('name'))->not->toContain('x.1')
        ->and($owned[0])->toHaveKeys(['id', 'name', 'label_text', 'module', 'risk']);
});

it('executes a constant number of queries for overview and modules', function (): void {
    Model::preventLazyLoading();
    $this->actingAs(overviewViewer());

    foreach (['/api/access/permissions/overview', '/api/access/permissions/modules'] as $url) {
        $run = function () use ($url): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson($url)->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };
        $run();
        $small = $run();
        foreach (range(1, 150) as $i) {
            makePermission("q{$i}.p{$i}")->forceFill(['module' => "q{$i}"])->save();
        }

        $run();

        expect($run())->toBe($small)->and($small)->toBeLessThanOrEqual(6);
        Permission::where('name', 'like', 'q%')->delete();
    }
});

it('lists modules with counts', function (): void {
    Permission::query()->delete();
    makePermission('a.1')->forceFill(['module' => 'user_mgmt'])->save();
    makePermission('a.2')->forceFill(['module' => 'user_mgmt'])->save();
    makePermission('b.1')->forceFill(['module' => 'audit', 'deprecated_at' => now()])->save();
    makePermission('c.1')->forceFill(['module' => 'audit'])->save();

    $this->actingAs(overviewViewer())->getJson('/api/access/permissions/modules')->assertOk()
        ->assertJsonPath('data', [
            ['key' => 'audit', 'label' => 'Audit', 'permissions_count' => 1],
            ['key' => 'user_mgmt', 'label' => 'User Mgmt', 'permissions_count' => 2],
        ]);
});
