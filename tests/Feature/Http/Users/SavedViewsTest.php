<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Users\Models\SavedView;

/** @param  array<string, mixed>  $overrides */
function viewPayload(array $overrides = []): array
{
    return ['page' => 'users', 'name' => 'Suspended admins', 'filters' => ['status' => 'suspended', 'role' => ['admin', 'manager']], ...$overrides];
}

it('creates, lists and deletes the current users saved views', function (): void {
    $actor = usersActor();

    $created = $this->actingAs($actor)->postJson('/api/access/saved-views', viewPayload())->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.page', 'users')
        ->assertJsonPath('data.name', 'Suspended admins')
        ->assertJsonPath('data.filters', ['status' => 'suspended', 'role' => ['admin', 'manager']])
        ->json('data');

    expect(array_keys($created))->toBe(['id', 'page', 'name', 'filters', 'created_at']);

    $this->actingAs($actor)->getJson('/api/access/saved-views?page=users')->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $created['id']);

    $this->actingAs($actor)->deleteJson("/api/access/saved-views/{$created['id']}")->assertNoContent();
    expect(SavedView::count())->toBe(0);
    $this->actingAs($actor)->getJson('/api/access/saved-views')->assertOk()->assertJsonPath('data', []);
});

it('stores an empty filter set as an empty object', function (): void {
    $actor = usersActor();

    $this->actingAs($actor)->postJson('/api/access/saved-views', ['page' => 'users', 'name' => 'Everyone'])->assertCreated();
    $raw = $this->actingAs($actor)->getJson('/api/access/saved-views')->assertOk()->getContent();

    expect($raw)->toContain('"filters":{}');
});

it('filters the list by page and rejects unknown pages', function (): void {
    $actor = usersActor();
    $this->actingAs($actor)->postJson('/api/access/saved-views', viewPayload())->assertCreated();
    $this->actingAs($actor)->postJson('/api/access/saved-views', viewPayload(['page' => 'roles']))->assertCreated();

    $this->actingAs($actor)->getJson('/api/access/saved-views')->assertJsonCount(2, 'data');
    $this->actingAs($actor)->getJson('/api/access/saved-views?page=roles')->assertJsonCount(1, 'data')->assertJsonPath('data.0.page', 'roles');
    $this->actingAs($actor)->getJson('/api/access/saved-views?page=bogus')->assertStatus(422)->assertJsonPath('code', 'validation_failed')->assertJsonStructure(['errors' => ['page']]);
    $this->actingAs($actor)->getJson('/api/access/saved-views?page[]=x')->assertStatus(422);
});

it('keeps views private to their owner', function (): void {
    $alice = usersActor(email: 'alice@example.test');
    $bob = usersActor(email: 'bob@example.test');
    $view = SavedView::create(['user_id' => $alice->id, 'page' => 'users', 'name' => 'Mine', 'filters' => []]);

    $this->actingAs($bob)->getJson('/api/access/saved-views')->assertOk()->assertJsonPath('data', []);
    $this->actingAs($bob)->deleteJson("/api/access/saved-views/{$view->id}")->assertNotFound()->assertJson(['success' => false, 'code' => 'not_found']);
    $this->actingAs($bob)->deleteJson('/api/access/saved-views/999999')->assertNotFound();
    $this->actingAs($bob)->deleteJson('/api/access/saved-views/abc')->assertNotFound();
    expect(SavedView::count())->toBe(1);

    $this->actingAs($bob)->postJson('/api/access/saved-views', viewPayload(['name' => 'Mine']))->assertCreated();
    expect(SavedView::count())->toBe(2);
});

it('rejects duplicate names per user and page but allows them elsewhere', function (): void {
    $actor = usersActor();
    $other = usersActor(email: 'other@example.test');
    $this->actingAs($actor)->postJson('/api/access/saved-views', viewPayload())->assertCreated();

    $this->actingAs($actor)->postJson('/api/access/saved-views', viewPayload())->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')->assertJsonStructure(['errors' => ['name']]);
    $this->actingAs($actor)->postJson('/api/access/saved-views', viewPayload(['page' => 'roles']))->assertCreated();
    $this->actingAs($other)->postJson('/api/access/saved-views', viewPayload())->assertCreated();
});

it('limits views to 25 per user and page', function (): void {
    $actor = usersActor();
    for ($i = 1; $i <= 25; $i++) {
        SavedView::create(['user_id' => $actor->id, 'page' => 'users', 'name' => "v{$i}", 'filters' => []]);
    }

    $this->actingAs($actor)->postJson('/api/access/saved-views', viewPayload(['name' => 'one too many']))->assertStatus(422)
        ->assertJson(['success' => false, 'code' => 'saved_view.limit']);
    $this->actingAs($actor)->postJson('/api/access/saved-views', viewPayload(['page' => 'roles']))->assertCreated();

    $first = SavedView::where('name', 'v1')->firstOrFail();
    $this->actingAs($actor)->deleteJson("/api/access/saved-views/{$first->id}")->assertNoContent();
    $this->actingAs($actor)->postJson('/api/access/saved-views', viewPayload(['name' => 'now fits']))->assertCreated();
});

it('validates the payload', function (array $payload, string $field): void {
    $actor = usersActor();

    $this->actingAs($actor)->postJson('/api/access/saved-views', $payload)->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')->assertJsonStructure(['errors' => [$field]]);
    expect(SavedView::count())->toBe(0);
})->with([
    'missing page' => [['name' => 'x'], 'page'],
    'unknown page' => [['page' => 'secrets', 'name' => 'x'], 'page'],
    'array page' => [['page' => ['users'], 'name' => 'x'], 'page'],
    'missing name' => [['page' => 'users'], 'name'],
    'blank name' => [['page' => 'users', 'name' => '   '], 'name'],
    'long name' => [['page' => 'users', 'name' => str_repeat('n', 81)], 'name'],
    'array name' => [['page' => 'users', 'name' => ['x']], 'name'],
    'string filters' => [['page' => 'users', 'name' => 'x', 'filters' => 'status=active'], 'filters'],
    'nested filter value' => [['page' => 'users', 'name' => 'x', 'filters' => ['a' => ['b' => ['c']]]], 'filters'],
    'null filter value' => [['page' => 'users', 'name' => 'x', 'filters' => ['a' => null]], 'filters'],
    'list filters' => [['page' => 'users', 'name' => 'x', 'filters' => ['one', 'two']], 'filters'],
    'too many keys' => [['page' => 'users', 'name' => 'x', 'filters' => array_fill_keys(array_map(fn ($i) => "k{$i}", range(1, 21)), 'v')], 'filters'],
    'too large' => [['page' => 'users', 'name' => 'x', 'filters' => ['q' => str_repeat('a', 2100)]], 'filters'],
    'array with object' => [['page' => 'users', 'name' => 'x', 'filters' => ['a' => [['b' => 1]]]], 'filters'],
]);

it('accepts the boundary payloads', function (): void {
    $actor = usersActor();

    $this->actingAs($actor)->postJson('/api/access/saved-views', ['page' => 'users', 'name' => str_repeat('n', 80), 'filters' => array_fill_keys(array_map(fn ($i) => "k{$i}", range(1, 20)), 1)])->assertCreated();
    $this->actingAs($actor)->postJson('/api/access/saved-views', ['page' => 'audit', 'name' => 'flags', 'filters' => ['a' => true, 'b' => 1.5, 'c' => ['x', 2, false]]])->assertCreated()
        ->assertJsonPath('data.filters.c', ['x', 2, false]);
});

it('only accepts pages from the configuration', function (): void {
    config(['roles.users.saved_view_pages' => ['users']]);
    $actor = usersActor();

    $this->actingAs($actor)->postJson('/api/access/saved-views', viewPayload(['page' => 'roles']))->assertStatus(422);
    $this->actingAs($actor)->postJson('/api/access/saved-views', viewPayload())->assertCreated();
});

it('requires authentication and the users view permission', function (): void {
    $this->getJson('/api/access/saved-views')->assertUnauthorized();
    $this->postJson('/api/access/saved-views', viewPayload())->assertUnauthorized();
    $this->deleteJson('/api/access/saved-views/1')->assertUnauthorized();

    $plain = makeUser('plain@example.test');
    $view = SavedView::create(['user_id' => $plain->id, 'page' => 'users', 'name' => 'x', 'filters' => []]);
    $this->actingAs($plain)->getJson('/api/access/saved-views')->assertForbidden()->assertJson(['code' => 'forbidden']);
    $this->actingAs($plain)->postJson('/api/access/saved-views', viewPayload())->assertForbidden();
    $this->actingAs($plain)->deleteJson("/api/access/saved-views/{$view->id}")->assertForbidden();
    expect(SavedView::count())->toBe(1);
});

it('rejects invalid UTF-8 with 422 and never 500', function (array $payload, string $field): void {
    $actor = usersActor();

    $this->actingAs($actor)->post('/api/access/saved-views', $payload, ['Accept' => 'application/json'])->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')->assertJsonStructure(['errors' => [$field]]);
    expect(SavedView::count())->toBe(0);
})->with([
    'name' => [['page' => 'users', 'name' => "bad\xFFname"], 'name'],
    'filter value' => [['page' => 'users', 'name' => 'x', 'filters' => ['a' => "bad\xFF"]], 'filters'],
    'nested filter value' => [['page' => 'users', 'name' => 'x', 'filters' => ['a' => ['ok', "bad\xC3\x28"]]], 'filters'],
    'filter key' => [['page' => 'users', 'name' => 'x', 'filters' => ["k\xFF" => 'v']], 'filters'],
]);

it('accepts Arabic text in names and filters', function (): void {
    $actor = usersActor();

    $this->actingAs($actor)->postJson('/api/access/saved-views', ['page' => 'users', 'name' => 'المشرفون الموقوفون', 'filters' => ['search' => 'عبدالله', 'role' => ['مدير']]])
        ->assertCreated()->assertJsonPath('data.name', 'المشرفون الموقوفون')->assertJsonPath('data.filters.search', 'عبدالله');
});
