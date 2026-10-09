<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Support\ActorContext;

it('is system when nobody is acting', function () {
    expect(app(ActorContext::class)->actor())->toBeNull()->and(app(ActorContext::class)->isSystem())->toBeTrue();
});

it('falls back to the authenticated user', function () {
    $user = makeUser();
    $this->actingAs($user);

    expect(app(ActorContext::class)->actor()?->is($user))->toBeTrue();
});

it('prefers an explicit actor and can force system', function () {
    $auth = makeUser('auth@example.test');
    $other = makeUser('other@example.test');
    $this->actingAs($auth);
    $ctx = app(ActorContext::class);

    expect($ctx->runAs($other, fn () => $ctx->actor()->is($other)))->toBeTrue()
        ->and($ctx->runAs(null, fn () => $ctx->actor()))->toBeNull()
        ->and($ctx->actor()->is($auth))->toBeTrue();
});

it('restores the previous actor even when the callback throws', function () {
    $ctx = app(ActorContext::class);
    $other = makeUser('other@example.test');

    try {
        $ctx->runAs($other, fn () => throw new RuntimeException('x'));
    } catch (RuntimeException) {
    }

    expect($ctx->actor())->toBeNull();
});
