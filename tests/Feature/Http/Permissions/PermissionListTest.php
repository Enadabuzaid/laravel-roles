<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestUser;

afterEach(function (): void {
    Model::preventLazyLoading(false);
});

function permViewer(string ...$extra): TestUser
{
    $user = makeUser('pv'.uniqid().'@example.test');
    $user->assignRole(makeRole('pviewer'.uniqid(), ['access.permissions.view', ...$extra]));

    return $user;
}

function namesOf(mixed $test, TestUser $user, string $qs): array
{
    return collect($test->actingAs($user)->getJson('/api/access/permissions?'.$qs)->assertOk()->json('data'))->pluck('name')->all();
}

it('lists permissions in the paginated envelope with roles_count', function (): void {
    makeRole('holder', ['p.one']);
    $user = permViewer();

    $response = $this->actingAs($user)->getJson('/api/access/permissions?per_page=100')->assertOk()
        ->assertJsonPath('success', true)->assertJsonStructure(['data' => [['id', 'name', 'module', 'label', 'label_text', 'description', 'description_text', 'risk', 'source', 'deprecated_at', 'is_deprecated', 'last_synced_at', 'sort_order', 'roles_count', 'created_at', 'updated_at']], 'meta' => ['pagination']]);

    $row = collect($response->json('data'))->firstWhere('name', 'p.one');
    expect($row['roles_count'])->toBe(1)->and($row['is_deprecated'])->toBeFalse();
});

it('requires authentication and the view permission', function (): void {
    $this->getJson('/api/access/permissions')->assertUnauthorized();
    $this->actingAs(makeUser())->getJson('/api/access/permissions')->assertForbidden();
    $this->actingAs(makeUser('b@example.test'))->getJson('/api/access/permissions/overview')->assertForbidden();
    $this->actingAs(makeUser('c@example.test'))->getJson('/api/access/permissions/modules')->assertForbidden();
    $this->actingAs(makeUser('d@example.test'))->getJson('/api/access/permissions/check?user=1&permission=x')->assertForbidden();
});

it('filters by module, risk, source, status and held', function (): void {
    makePermission('m1.a', 'high')->forceFill(['module' => 'm1'])->save();
    makePermission('m1.b', 'low')->forceFill(['module' => 'm1', 'source' => 'manual'])->save();
    makePermission('m2.a', 'medium')->forceFill(['module' => 'm2', 'deprecated_at' => now()])->save();
    makeRole('r', ['m1.a']);
    $user = permViewer();

    $sorted = fn (string $qs): array => collect(namesOf($this, $user, $qs.'&per_page=100'))->filter(fn ($n) => str_starts_with($n, 'm'))->sort()->values()->all();

    expect($sorted('filter[module]=m1'))->toBe(['m1.a', 'm1.b'])
        ->and($sorted('filter[risk]=high'))->toBe(['m1.a'])
        ->and($sorted('filter[source]=manual'))->toBe(['m1.b'])
        ->and($sorted(''))->toBe(['m1.a', 'm1.b'])
        ->and($sorted('filter[status]=deprecated'))->toBe(['m2.a'])
        ->and($sorted('filter[status]=all'))->toBe(['m1.a', 'm1.b', 'm2.a'])
        ->and($sorted('filter[status]=garbage'))->toBe(['m1.a', 'm1.b'])
        ->and($sorted('filter[module]=m1&filter[held]=yes'))->toBe(['m1.a'])
        ->and($sorted('filter[module]=m1&filter[held]=no'))->toBe(['m1.b'])
        ->and($sorted('filter[risk]=nonsense'))->toBe(['m1.a', 'm1.b']);
});

it('searches by name and translated label text only', function (): void {
    makePermission('zz.alpha')->forceFill(['label' => ['en' => 'Order Manager', 'ar' => 'مدير الطلبات']])->save();
    makePermission('zz.beta')->forceFill(['label' => ['en' => 'Auditor', 'ar' => 'مدقق']])->save();
    $user = permViewer();
    $search = fn (string $term): array => namesOf($this, $user, 'search='.urlencode($term));

    expect($search('zz.alp'))->toBe(['zz.alpha'])
        ->and($search('manager'))->toBe(['zz.alpha'])
        ->and($search('مدقق'))->toBe(['zz.beta'])
        ->and($search('label'))->toBe([])
        ->and($search('%'))->toBe([]);
});

it('sorts by whitelisted columns and ignores garbage', function (): void {
    makePermission('s.b', 'high')->forceFill(['module' => 'sm', 'sort_order' => 2])->save();
    makePermission('s.a', 'low')->forceFill(['module' => 'sm', 'sort_order' => 1])->save();
    makeRole('r1', ['s.b']);
    $user = permViewer();
    $mine = fn (string $sort): array => collect(namesOf($this, $user, 'filter[module]=sm&sort='.$sort))->all();

    expect($mine('name'))->toBe(['s.a', 's.b'])->and($mine('-name'))->toBe(['s.b', 's.a'])
        ->and($mine('-roles'))->toBe(['s.b', 's.a'])->and($mine('sort_order'))->toBe(['s.a', 's.b'])
        ->and($mine('-risk'))->toHaveCount(2)
        ->and($mine('password;drop'))->toHaveCount(2);
});

it('survives garbage query input', function (): void {
    $user = permViewer();
    $this->actingAs($user)->getJson('/api/access/permissions?filter[module][]=a&filter[risk][x]=1&search[]=1&per_page=abc&page=-5&sort[]=x')->assertOk();
});

it('shows one permission and 404s unknown or non-numeric ids', function (): void {
    $permission = makePermission('show.me');
    $user = permViewer();

    $this->actingAs($user)->getJson('/api/access/permissions/'.$permission->id)->assertOk()->assertJsonPath('data.name', 'show.me');
    $this->actingAs($user)->getJson('/api/access/permissions/999999')->assertNotFound()->assertJsonPath('code', 'not_found');
    $this->actingAs($user)->getJson('/api/access/permissions/abc')->assertNotFound();
});

it('runs a constant number of queries for the list', function (): void {
    Model::preventLazyLoading();
    $user = permViewer();
    $this->actingAs($user);

    $run = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/access/permissions?per_page=200&search=a')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $run();
    foreach (range(1, 5) as $i) {
        makeRole("cr{$i}", ["c.p{$i}"]);
    }
    $small = $run();
    foreach (range(6, 150) as $i) {
        makePermission("c.p{$i}");
    }

    expect($run())->toBe($small);
});
