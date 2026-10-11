<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Exceptions\ImmutableLogException;
use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Audit\AuditRecorder;
use Enadstack\LaravelRoles\Audit\Severity;
use Enadstack\LaravelRoles\Support\ActorContext;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\DB;

it('writes one entry with module, dotted key, severity and actor', function () {
    $actor = makeUser('actor@example.test');
    $subject = makeUser('subject@example.test');

    app(ActorContext::class)->runAs($actor, fn () => app(AuditRecorder::class)->record(
        'roles', 'assigned', $subject, ['roles' => []], ['roles' => ['admin']], ['role' => 'admin'], Severity::Warning,
    ));

    $log = ActionLog::sole();
    expect($log->event)->toBe('roles.assigned')->and($log->module)->toBe('roles')->and($log->severity)->toBe('warning')
        ->and($log->actor_id)->toBe($actor->id)->and($log->subject_id)->toBe($subject->id)
        ->and($log->new_values)->toEqual(['roles' => ['admin']])->and($log->meta)->toEqual(['role' => 'admin']);
});

it('records a system actor and allows a null subject', function () {
    app(AuditRecorder::class)->record('permissions', 'synced', null, null, ['added' => 3]);

    $log = ActionLog::sole();
    expect($log->actor_type)->toBeNull()->and($log->subject_type)->toBeNull()->and($log->severity)->toBe('info');
});

it('writes only after the surrounding transaction commits and never on rollback', function () {
    $subject = makeUser();

    DB::transaction(function () use ($subject) {
        app(AuditRecorder::class)->record('roles', 'assigned', $subject);
        expect(ActionLog::count())->toBe(0);
    });
    expect(ActionLog::count())->toBe(1);

    ActionLog::query()->delete();
    try {
        DB::transaction(function () use ($subject) {
            app(AuditRecorder::class)->record('roles', 'assigned', $subject);
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
    }
    expect(ActionLog::count())->toBe(0);
});

it('does nothing when auditing is disabled', function () {
    config(['roles.audit.enabled' => false]);
    app(AuditRecorder::class)->record('roles', 'assigned', makeUser());
    expect(ActionLog::count())->toBe(0);

    config(['roles.audit.enabled' => true, 'roles.modules.audit' => false]);
    app(AuditRecorder::class)->record('roles', 'assigned', makeUser('b@example.test'));
    expect(ActionLog::count())->toBe(0);
});

it('never lets a failing audit write break the caller', function () {
    ActionLog::creating(fn () => throw new RuntimeException('audit store unavailable'));
    $reported = [];
    $this->app->make(ExceptionHandler::class)->reportable(function (Throwable $e) use (&$reported) {
        $reported[] = $e;
    });

    app(AuditRecorder::class)->record('roles', 'assigned', makeUser());

    expect($reported)->not->toBeEmpty()
        ->and($reported[0]->getMessage())->toBe('audit store unavailable')
        ->and(ActionLog::count())->toBe(0);
});

it('keeps entries immutable under the test configuration', function () {
    app(AuditRecorder::class)->record('roles', 'assigned', makeUser());

    expect(fn () => ActionLog::sole()->update(['event' => 'x']))->toThrow(ImmutableLogException::class);
});

it('captures the actor at record() time, not at commit time', function () {
    $actor = makeUser('actor@example.test');
    $subject = makeUser('subject@example.test');

    DB::transaction(function () use ($actor, $subject) {
        app(ActorContext::class)->runAs($actor, fn () => app(AuditRecorder::class)->record('roles', 'assigned', $subject));
    });

    expect(ActionLog::sole()->actor_id)->toBe($actor->id);
});

it('snapshots subject and actor labels, preferring the default-locale label of translated models', function () {
    $actor = makeUser('actor@example.test');
    $role = makeRole('editor', [], ['label' => ['en' => 'Editors', 'ar' => 'المحررون']]);

    app(ActorContext::class)->runAs($actor, fn () => app(AuditRecorder::class)->record('roles', 'updated', $role));

    $log = ActionLog::sole();
    expect($log->subject_label)->toBe('Editors')->and($log->actor_label)->toBe('A');
});

it('labels a deleted subject from its old values', function () {
    app(AuditRecorder::class)->record('users', 'deleted', null, ['name' => 'Gone Person', 'email' => 'gone@example.test']);
    app(AuditRecorder::class)->record('roles', 'deleted', null, ['label' => ['en' => 'Old role', 'ar' => 'x']]);

    expect(ActionLog::query()->orderBy('id')->pluck('subject_label')->all())->toBe(['Gone Person', 'Old role']);
});

it('never stores secrets passed in values or meta', function () {
    app(AuditRecorder::class)->record('users', 'updated', makeUser('s@example.test'), ['password' => 'a'], ['password' => 'b'], ['token_hash' => 'c']);

    $log = ActionLog::sole();
    expect($log->old_values)->toBe(['password' => '[redacted]'])->and($log->meta)->toBe(['token_hash' => '[redacted]']);
});
