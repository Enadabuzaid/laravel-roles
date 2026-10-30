<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Core\Doctor\Checks\ConfigConsistencyCheck;
use Enadstack\LaravelRoles\Core\Doctor\CheckStatus;
use Enadstack\LaravelRoles\Http\Middleware\SetPermissionsTeam;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Roles\Actions\SeedDefaultRoles;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Support\TeamContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\TestUser;

beforeEach(function () {
    config(['permission.teams' => true, 'roles.teams' => true, 'permission.column_names.team_foreign_key' => 'tenant_id']);

    foreach (['model_has_permissions', 'model_has_roles', 'role_has_permissions', 'roles', 'permissions'] as $table) {
        Schema::dropIfExists($table);
    }
    (include dirname(__DIR__, 3).'/vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub')->up();
    (include dirname(__DIR__, 2).'/database/migrations/0000_00_00_000001_extend_roles_table.php')->up();
    app(PermissionRegistrar::class)->initializeCache();
    TeamContext::set(null);
});

it('hides a role of team A from team B and shows global roles everywhere', function () {
    Role::create(['name' => 'platform', 'guard_name' => 'web']);
    TeamContext::run(1, fn () => Role::create(['name' => 'cashier', 'guard_name' => 'web']));

    expect(TeamContext::run(1, fn () => Role::pluck('name')->sort()->values()->all()))->toBe(['cashier', 'platform'])
        ->and(TeamContext::run(2, fn () => Role::pluck('name')->all()))->toBe(['platform'])
        ->and(TeamContext::run(null, fn () => Role::pluck('name')->all()))->toBe(['platform']);
});

it('seeds the default roles per team without collisions', function () {
    $seed = fn (int $team) => TeamContext::run($team, fn () => app(ActorContext::class)->runAs(null, fn () => app(SeedDefaultRoles::class)->handle()));

    $seed(1);
    $seed(2);
    $seed(1);

    expect(Role::withoutGlobalScopes()->count())->toBe(8)
        ->and(Role::withoutGlobalScopes()->where('tenant_id', 1)->count())->toBe(4)
        ->and(TeamContext::run(2, fn () => Role::count()))->toBe(4);
});

it('sets the team from the authenticated user through the middleware', function () {
    $request = Request::create('/');
    $request->setUserResolver(fn () => new TestUser(['tenant_id' => 7]));
    $user = $request->user();
    $user->setAttribute('tenant_id', 7);

    (new SetPermissionsTeam)->handle($request, fn () => response('ok'));

    expect(TeamContext::get())->toBe(7);

    config(['roles.team_resolver' => fn () => 9]);
    (new SetPermissionsTeam)->handle($request, fn () => response('ok'));

    expect(TeamContext::get())->toBe(9);
});

it('passes the doctor config check with teams on and a team column, fails without it', function () {
    expect(app(ConfigConsistencyCheck::class)->run()->status)->toBe(CheckStatus::Pass);

    Schema::table('roles', fn ($table) => $table->dropColumn('tenant_id'));
})->skip(fn () => config('database.default') === 'sqlite', 'dropping an indexed column is not supported on sqlite');

it('keeps teams off by default', function () {
    config(['permission.teams' => false, 'roles.teams' => false]);

    expect(TeamContext::enabled())->toBeFalse()
        ->and(app(ConfigConsistencyCheck::class)->run()->status)->toBe(CheckStatus::Pass);
});

it('sends team_id on roles over the API: null for global, the id for a tenant role, no other tenant', function () {
    Role::create(['name' => 'platform', 'guard_name' => 'web']);
    TeamContext::run(2, fn () => Role::create(['name' => 'other-cashier', 'guard_name' => 'web']));
    TeamContext::set(1);
    $viewer = makeRole('viewer', ['access.roles.view']);
    makeRole('cashier');
    $user = makeUser();
    $user->assignRole($viewer);

    $rows = collect($this->actingAs($user)->getJson('/api/access/roles?per_page=50')->assertOk()->json('data'))->pluck('team_id', 'name');

    expect($rows->has('other-cashier'))->toBeFalse()
        ->and($rows['platform'])->toBeNull()
        ->and($rows['cashier'])->toBe(1)
        ->and($rows['viewer'])->toBe(1)
        ->and($rows->has('platform'))->toBeTrue();
});

it('omits team_id from role responses when teams are off', function () {
    TeamContext::set(1);
    $user = makeUser();
    $user->assignRole(makeRole('viewer', ['access.roles.view']));
    config(['permission.teams' => false, 'roles.teams' => false]);

    $row = collect($this->actingAs($user)->getJson('/api/access/roles')->assertOk()->json('data'))->firstWhere('name', 'viewer');

    expect($row)->not->toHaveKey('team_id');
});
