<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Roles\Actions\UpdateMatrix;
use Enadstack\LaravelRoles\Roles\Queries\MatrixQuery;
use Enadstack\LaravelRoles\Support\ActorContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Bulk-seeds roles and permissions with a grant on every fourth permission.
 */
function seedMatrixData(int $roles, int $permissions, string $prefix): void
{
    $now = now();
    DB::table('permissions')->insert(array_map(fn (int $i): array => ['name' => "{$prefix}{$i}.view", 'guard_name' => 'web', 'risk' => 'low', 'module' => 'm'.($i % 7), 'created_at' => $now, 'updated_at' => $now], range(1, $permissions)));
    DB::table('roles')->insert(array_map(fn (int $i): array => ['name' => "{$prefix}role{$i}", 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now], range(1, $roles)));

    $permissionIds = DB::table('permissions')->where('name', 'like', "{$prefix}%")->pluck('id')->all();
    $roleIds = DB::table('roles')->where('name', 'like', "{$prefix}role%")->pluck('id')->all();
    $rows = [];
    foreach ($roleIds as $roleId) {
        foreach ($permissionIds as $index => $permissionId) {
            if ($index % 4 === 0) {
                $rows[] = ['role_id' => $roleId, 'permission_id' => $permissionId];
            }
        }
    }
    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table('role_has_permissions')->insert($chunk);
    }
}

function queryCount(Closure $callback): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $callback();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

afterEach(function () {
    Model::preventLazyLoading(false);
});

it('builds the matrix in exactly three queries however large it is', function () {
    $actor = makeUser('actor@example.test');
    $actor->load('roles');

    seedMatrixData(5, 20, 's');
    Model::preventLazyLoading();
    $small = queryCount(fn () => MatrixQuery::get($actor, true));

    seedMatrixData(60, 200, 'l');
    $large = queryCount(fn () => MatrixQuery::get($actor, true));

    expect($small)->toBe(3)->and($large)->toBe(3);
});

it('serves GET matrix with the same number of queries for small and large matrices', function () {
    $actor = makeUser('actor@example.test');
    $actor->assignRole(makeRole('viewer', ['access.permissions.view', 'access.roles.update']));
    $actor = $actor->fresh();

    $request = fn (): int => queryCount(fn () => $this->actingAs($actor)->getJson('/api/access/matrix')->assertOk());

    seedMatrixData(5, 20, 's');
    $request();
    $small = $request();

    seedMatrixData(60, 200, 'l');
    $large = $request();

    expect($large)->toBe($small);
});

it('grows the PUT matrix cost with changed roles only, not with the size of the permission catalogue', function () {
    seedMatrixData(4, 20, 's');
    $roles = Role::where('name', 'like', 'srole%')->orderBy('id')->pluck('id')->all();
    $permissions = DB::table('permissions')->where('name', 'like', 's%')->orderBy('id')->pluck('id')->all();

    $put = fn (array $changes): int => queryCount(fn () => app(ActorContext::class)->runAs(null, fn () => app(UpdateMatrix::class)->handle($changes)));

    $put([$roles[0] => ['grant' => [$permissions[1]]]]);
    $oneRole = $put([$roles[0] => ['revoke' => [$permissions[1]]]]);
    $twoRoles = $put([$roles[0] => ['grant' => [$permissions[1]]], $roles[1] => ['grant' => [$permissions[1]]]]);
    $threeRoles = $put([$roles[0] => ['revoke' => [$permissions[1]]], $roles[1] => ['revoke' => [$permissions[1]]], $roles[2] => ['grant' => [$permissions[1]]]]);

    expect($twoRoles - $oneRole)->toBe($threeRoles - $twoRoles)->and($twoRoles)->toBeGreaterThan($oneRole);

    seedMatrixData(60, 300, 'l');
    $bigCatalogue = $put([$roles[0] => ['grant' => [$permissions[1]]]]);
    $again = $put([$roles[0] => ['revoke' => [$permissions[1]]]]);

    expect($bigCatalogue)->toBe($oneRole)->and($again)->toBe($oneRole);
});
