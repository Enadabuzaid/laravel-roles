<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Core\Doctor\Checks\AuditReadyCheck;
use Enadstack\LaravelRoles\Core\Doctor\CheckStatus;
use Illuminate\Support\Facades\Schema;

it('passes when migrated and immutable', function () {
    config(['action-logger.immutable' => true]);

    $result = (new AuditReadyCheck)->run();

    expect($result->status)->toBe(CheckStatus::Pass);
});

it('warns when audit entries are not immutable', function () {
    config(['action-logger.immutable' => false]);

    $result = (new AuditReadyCheck)->run();

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('ACTION_LOGGER_IMMUTABLE');
});

it('fails when the module column is missing and doctor exits non-zero', function () {
    config(['action-logger.immutable' => true]);
    Schema::dropIfExists('action_logs');
    Schema::create('action_logs', function ($table) {
        $table->id();
        $table->string('severity')->nullable();
        $table->string('request_id')->nullable();
    });

    $result = (new AuditReadyCheck)->run();

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('module')->and($result->message)->toContain('php artisan migrate');
    $this->artisan('roles:doctor')->expectsOutputToContain('audit-ready')->assertExitCode(1);
});

it('fails when the table is missing', function () {
    Schema::dropIfExists('action_logs');

    expect((new AuditReadyCheck)->run()->status)->toBe(CheckStatus::Fail);
});

it('passes when the audit module is disabled', function () {
    config(['roles.modules.audit' => false]);
    Schema::dropIfExists('action_logs');

    expect((new AuditReadyCheck)->run()->status)->toBe(CheckStatus::Pass);
});

it('is registered in the doctor checks', function () {
    expect(config('roles.doctor.checks'))->toContain(AuditReadyCheck::class);
});

it('fails when the label columns of action logger 1.2 are missing', function () {
    config(['action-logger.immutable' => true]);
    Schema::dropIfExists('action_logs');
    Schema::create('action_logs', function ($table) {
        $table->id();
        $table->string('module')->nullable();
        $table->string('severity')->nullable();
        $table->string('request_id')->nullable();
    });

    $result = (new AuditReadyCheck)->run();

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('subject_label')->and($result->message)->toContain('actor_label');
});

it('warns with the immutability hint and passes once enabled', function () {
    config(['action-logger.immutable' => false]);
    $warn = (new AuditReadyCheck)->run();
    config(['action-logger.immutable' => true]);

    expect($warn->status)->toBe(CheckStatus::Warn)->and($warn->message)->toContain('ACTION_LOGGER_IMMUTABLE=true')
        ->and((new AuditReadyCheck)->run()->status)->toBe(CheckStatus::Pass);
});
