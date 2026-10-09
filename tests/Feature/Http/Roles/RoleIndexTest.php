<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\TestUser;

function viewer(string ...$extra): TestUser
{
    $user = makeUser('viewer'.uniqid().'@example.test');
    $user->assignRole(makeRole('viewer'.uniqid(), ['access.roles.view', ...$extra]));

    return $user;
}

afterEach(function (): void {
    Model::preventLazyLoading(false);
});

it('lists roles in the paginated envelope with counts and abilities', function (): void {
    $editor = makeRole('editor', ['access.roles.view', 'access.roles.update']);
    $user = makeUser();
    $user->assignRole($editor);

    $response = $this->actingAs($user)->getJson('/api/access/roles?per_page=10')->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.pagination.per_page', 10);

    $row = collect($response->json('data'))->firstWhere('name', 'editor');

    expect($row['members_count'])->toBe(1)
        ->and($row['permissions_count'])->toBe(2)
        ->and($row['abilities'])->toBe(['update' => true, 'delete' => false, 'assign' => false]);
});

it('filters by type and status', function (): void {
    makeRole('sys', [], ['is_system' => true]);
    makeRole('cust');
    makeRole('gone')->delete();
    makeRole('def', [], ['is_default' => true]);
    $user = viewer();

    $names = fn (string $qs): array => collect($this->actingAs($user)->getJson('/api/access/roles?'.$qs)->assertOk()->json('data'))->pluck('name')->sort()->values()->all();

    expect($names('filter[type]=system'))->toBe(['sys'])
        ->and($names('filter[status]=trashed'))->toBe(['gone'])
        ->and($names('filter[status]=all&filter[default]=1'))->toBe(['def'])
        ->and($names('filter[status]=all'))->toContain('gone', 'sys')
        ->and($names(''))->not->toContain('gone');
});

it('searches by name and by English and Arabic label text', function (): void {
    makeRole('alpha', [], ['label' => ['en' => 'Order Manager', 'ar' => 'مدير الطلبات']]);
    makeRole('beta', [], ['label' => ['en' => 'Auditor', 'ar' => 'مدقق']]);
    $user = viewer();

    $search = fn (string $term): array => collect($this->actingAs($user)->getJson('/api/access/roles?search='.urlencode($term))->assertOk()->json('data'))->pluck('name')->all();

    expect($search('alpha'))->toBe(['alpha'])
        ->and($search('order manager'))->toBe(['alpha'])
        ->and($search('مدقق'))->toBe(['beta'])
        ->and($search('zzzz-none'))->toBe([]);
});

it('sorts by member count descending', function (): void {
    $small = makeRole('small');
    $big = makeRole('big');
    makeUser('m1@example.test')->assignRole($big);
    makeUser('m2@example.test')->assignRole($big);
    makeUser('m3@example.test')->assignRole($small);

    $names = collect($this->actingAs(viewer())->getJson('/api/access/roles?sort=-members')->assertOk()->json('data'))->pluck('name')->all();

    expect(array_slice($names, 0, 2))->toBe(['big', 'small']);
});

it('includes permissions on request', function (): void {
    makeRole('with-perms', ['access.roles.view']);

    $row = collect($this->actingAs(viewer())->getJson('/api/access/roles?include=permissions')->assertOk()->json('data'))->firstWhere('name', 'with-perms');

    expect($row['permissions'][0])->toHaveKeys(['id', 'name', 'label_text', 'risk', 'module']);
});

it('ignores garbage query parameters', function (): void {
    $this->actingAs(viewer())->getJson('/api/access/roles?sort=zzz&per_page=abc&filter[type]=zzz&filter[status]=zzz')->assertOk();
});

it('answers 403 without the view permission and 401 unauthenticated', function (): void {
    $this->actingAs(makeUser())->getJson('/api/access/roles')->assertForbidden()->assertJson(['success' => false]);
    auth()->forgetGuards();
    $this->getJson('/api/access/roles')->assertUnauthorized()->assertJson(['success' => false, 'code' => 'unauthenticated']);
});

it('executes the same number of queries for 5 and 120 roles', function (): void {
    Model::preventLazyLoading();
    $user = viewer();
    $this->actingAs($user);

    $run = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/access/roles?per_page=25&include=permissions')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    foreach (range(1, 5) as $i) {
        makeRole("r{$i}", ['access.roles.view']);
    }
    $run();
    $small = $run();
    foreach (range(6, 120) as $i) {
        makeRole("r{$i}", ['access.roles.view']);
    }
    $run();
    $large = $run();

    expect($large)->toBe($small);
});

it('searches only translated values, never json keys or punctuation', function (): void {
    makeRole('alpha', [], ['label' => ['en' => 'Order Manager', 'ar' => 'مدير الطلبات'], 'description' => ['en' => 'Handles refunds', 'ar' => 'يدير الاسترداد']]);
    makeRole('beta', [], ['label' => ['en' => '50%_off', 'ar' => 'مشاهد']]);
    $user = viewer();

    $search = fn (string $term): array => collect($this->actingAs($user)->getJson('/api/access/roles?search='.urlencode($term))->assertOk()->json('data'))->pluck('name')->sort()->values()->all();

    expect($search('en'))->toBe([])
        ->and($search('ar'))->toBe([])
        ->and($search('label'))->toBe([])
        ->and($search('{'))->toBe([])
        ->and($search('"'))->toBe([])
        ->and($search('الطلبات'))->toBe(['alpha'])
        ->and($search('REFUNDS'))->toBe(['alpha'])
        ->and($search('الاسترداد'))->toBe(['alpha'])
        ->and($search('%'))->toBe(['beta'])
        ->and($search('0%_'))->toBe(['beta'])
        ->and($search('_'))->toBe(['beta'])
        ->and($search('5_'))->toBe([]);
});

it('filters by custom type, ignores a garbage default flag and lists all statuses', function (): void {
    makeRole('sys', [], ['is_system' => true]);
    makeRole('cust');
    makeRole('gone')->delete();
    $user = viewer();

    $names = fn (string $qs): array => collect($this->actingAs($user)->getJson('/api/access/roles?'.$qs)->assertOk()->json('data'))->pluck('name')->sort()->values()->all();

    expect($names('filter[type]=custom'))->toContain('cust')->not->toContain('sys', 'gone')
        ->and($names('filter[default]=garbage'))->toContain('sys', 'cust')
        ->and($names('filter[status]=all'))->toContain('sys', 'cust', 'gone');
});

it('does not change default binding for host routes using {role}', function (): void {
    Route::middleware('web')->get('host/{role}', fn (Role $role) => ['name' => $role->name]);
    $live = makeRole('live');
    $gone = makeRole('gone');
    $gone->delete();

    $this->getJson("/host/{$live->id}")->assertOk()->assertJson(['name' => 'live']);
    $this->getJson("/host/{$gone->id}")->assertNotFound();
});
