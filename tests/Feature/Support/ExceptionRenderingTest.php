<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Support\Exceptions\AccessException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::prefix('api/access')->group(function () {
        Route::get('boom', fn () => throw new AccessException('At least one Owner must remain.', 'guardrail.last_owner', 403));
        Route::post('validate', fn (Request $r) => $r->validate(['name' => 'required']));
        Route::get('missing', fn () => throw new ModelNotFoundException);
        Route::get('forbidden', fn () => throw new AuthorizationException('No.'));
    });
    Route::get('other/boom', fn () => throw new AccessException('x', 'guardrail.last_owner', 403));
});

it('renders access exceptions in the envelope', function () {
    $this->getJson('api/access/boom')->assertStatus(403)->assertExactJson([
        'success' => false, 'message' => 'At least one Owner must remain.',
        'code' => 'guardrail.last_owner', 'errors' => [],
    ]);
});

it('renders validation errors in the envelope', function () {
    $this->postJson('api/access/validate', [])->assertStatus(422)->assertExactJson([
        'success' => false, 'message' => 'The name field is required.',
        'code' => 'validation_failed', 'errors' => ['name' => ['The name field is required.']],
    ]);
});

it('renders not-found and forbidden in the envelope', function () {
    $this->getJson('api/access/missing')->assertStatus(404)->assertJson(['success' => false, 'code' => 'not_found']);
    $this->getJson('api/access/forbidden')->assertStatus(403)->assertJson(['success' => false, 'code' => 'forbidden']);
});

it('leaves routes outside the prefix untouched', function () {
    $this->getJson('other/boom')->assertJsonMissing(['code' => 'guardrail.last_owner']);
});
