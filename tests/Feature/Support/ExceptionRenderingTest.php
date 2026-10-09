<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Support\Exceptions\AccessException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    Route::prefix('api/access')->group(function () {
        Route::get('boom', fn () => throw new AccessException('At least one Owner must remain.', 'guardrail.last_owner', 403));
        Route::post('validate', fn (Request $r) => $r->validate(['name' => 'required']));
        Route::get('missing', fn () => throw new ModelNotFoundException);
        Route::get('forbidden', fn () => throw new AuthorizationException('No.'));
        Route::get('unauth', fn () => throw new AuthenticationException);
        Route::get('conflict', fn () => abort(409, 'Already exists.'));
        Route::get('throttled', fn () => abort(429));
        Route::get('teapot', fn () => abort(418));
        Route::get('expired', fn () => abort(419));
        Route::get('csrf', fn () => throw new TokenMismatchException('CSRF token mismatch.'));
        Route::get('only-get', fn () => 'ok');
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

it('renders unauthenticated in the envelope', function () {
    $this->getJson('api/access/unauth')->assertStatus(401)->assertJson(['success' => false, 'code' => 'unauthenticated']);
});

it('renders other http exceptions in the envelope with the exception status', function () {
    $this->getJson('api/access/conflict')->assertStatus(409)->assertJson(['success' => false, 'code' => 'conflict', 'message' => 'Already exists.']);
    $this->getJson('api/access/throttled')->assertStatus(429)->assertJson(['success' => false, 'code' => 'too_many_requests']);
    $this->getJson('api/access/teapot')->assertStatus(418)->assertJson(['success' => false, 'code' => 'http_error']);
    $this->postJson('api/access/only-get')->assertStatus(405)->assertJson(['success' => false, 'code' => 'method_not_allowed']);
});

it('keeps the headers of the original http exception on the envelope', function () {
    $this->postJson('api/access/only-get')->assertStatus(405)->assertHeader('Allow');
    expect($this->postJson('api/access/only-get')->headers->get('Allow'))->toContain('GET');

    Route::get('api/access/retry', fn () => throw new HttpException(429, 'Slow down.', null, ['Retry-After' => '42']));
    $this->getJson('api/access/retry')->assertStatus(429)->assertHeader('Retry-After', '42')->assertJson(['code' => 'too_many_requests']);
});

it('maps an expired session and a csrf mismatch to session_expired with 419', function () {
    $this->getJson('api/access/expired')->assertStatus(419)->assertJson(['success' => false, 'code' => 'session_expired']);
    $this->getJson('api/access/csrf')->assertStatus(419)->assertJson(['success' => false, 'code' => 'session_expired']);
});

it('also covers the bare prefix url', function () {
    $this->getJson('api/access')->assertStatus(404)->assertJson(['success' => false, 'code' => 'not_found']);
});

it('renders access exceptions outside the prefix as the envelope for json requests', function () {
    $this->getJson('other/boom')->assertStatus(403)->assertExactJson([
        'success' => false, 'message' => 'x', 'code' => 'guardrail.last_owner', 'errors' => [],
    ]);
});

it('renders access exceptions outside the prefix as a plain response with the status for html requests', function () {
    $this->get('other/boom')->assertStatus(403);
});

it('does not report access exceptions', function () {
    expect((new AccessException('x', 'guardrail.last_owner'))->report())->toBeTrue();

    $reported = [];
    $this->app->make(ExceptionHandler::class)->reportable(function (Throwable $e) use (&$reported) {
        $reported[] = $e;
    });
    $this->getJson('other/boom');

    expect($reported)->toBe([]);
});

it('still reports other exceptions outside the prefix untouched', function () {
    Route::get('other/plain', fn () => throw new RuntimeException('plain'));

    $this->getJson('other/plain')->assertStatus(500)->assertJsonMissing(['code' => 'guardrail.last_owner']);
});
