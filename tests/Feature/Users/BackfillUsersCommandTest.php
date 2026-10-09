<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Core\Doctor\Checks\UsersStateCheck;
use Enadstack\LaravelRoles\Core\Doctor\CheckStatus;
use Enadstack\LaravelRoles\Users\Enums\UserStatus;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function bulkInsertUsers(int $count): void
{
    $rows = [];
    for ($i = 1; $i <= $count; $i++) {
        $rows[] = ['name' => "U{$i}", 'email' => "u{$i}@example.test", 'password' => 'x', 'created_at' => now(), 'updated_at' => now()];
    }
    foreach (array_chunk($rows, 100) as $chunk) {
        DB::table('users')->insert($chunk);
    }
}

it('creates active rows only for users without one', function (): void {
    $withRow = makeUser('has@example.test');
    UserAccessState::create(['user_id' => $withRow->id, 'status' => UserStatus::Suspended, 'status_reason' => 'keep']);
    $a = makeUser('a1@example.test');
    $b = makeUser('b1@example.test');

    $this->artisan('roles:backfill-users')->expectsOutputToContain('Created 2')->assertExitCode(0);

    expect(UserAccessState::count())->toBe(3)
        ->and(UserAccessState::where('user_id', $withRow->id)->first()->status)->toBe(UserStatus::Suspended)
        ->and(UserAccessState::where('user_id', $withRow->id)->first()->status_reason)->toBe('keep')
        ->and(UserAccessState::where('user_id', $a->id)->first()->status)->toBe(UserStatus::Active)
        ->and(UserAccessState::where('user_id', $b->id)->first()->activated_at)->not->toBeNull();
});

it('writes nothing on a dry run', function (): void {
    makeUser();

    $this->artisan('roles:backfill-users --dry-run')->expectsOutputToContain('Would create 1')->assertExitCode(0);

    expect(UserAccessState::count())->toBe(0);
});

it('backfills 250 users in chunks and a second run is a cheap no-op', function (): void {
    bulkInsertUsers(250);

    $this->artisan('roles:backfill-users')->expectsOutputToContain('Created 250')->assertExitCode(0);
    expect(UserAccessState::count())->toBe(250);

    DB::enableQueryLog();
    $this->artisan('roles:backfill-users')->expectsOutputToContain('Created 0')->assertExitCode(0);
    $secondRun = count(DB::getQueryLog());
    DB::flushQueryLog();
    $this->artisan('roles:backfill-users')->assertExitCode(0);
    $third = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($secondRun)->toBeLessThanOrEqual(3)->and($third)->toBe($secondRun)
        ->and(UserAccessState::count())->toBe(250);
});

it('uses a fixed number of queries for 250 users, never one per user', function (): void {
    bulkInsertUsers(250);

    DB::enableQueryLog();
    $this->artisan('roles:backfill-users')->assertExitCode(0);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeLessThan(12);
});

it('reports a missing state table', function (): void {
    Schema::dropIfExists('user_access_states');

    $this->artisan('roles:backfill-users')->expectsOutputToContain('user_access_states table is missing')->assertExitCode(1);
});

it('doctor check passes when every user has a row, warns otherwise and fails without the table', function (): void {
    expect((new UsersStateCheck)->run()->status)->toBe(CheckStatus::Pass);

    $user = makeUser();
    $warn = (new UsersStateCheck)->run();
    expect($warn->status)->toBe(CheckStatus::Warn)->and($warn->message)->toContain('1 user')->toContain('roles:backfill-users');

    UserAccessState::create(['user_id' => $user->id, 'status' => UserStatus::Active]);
    expect((new UsersStateCheck)->run()->status)->toBe(CheckStatus::Pass);

    Schema::dropIfExists('user_access_states');
    expect((new UsersStateCheck)->run()->status)->toBe(CheckStatus::Fail);

    config(['roles.modules.users' => false]);
    expect((new UsersStateCheck)->run()->status)->toBe(CheckStatus::Pass);
});

it('registers the doctor check by default', function (): void {
    expect(config('roles.doctor.checks'))->toContain(UsersStateCheck::class);
});
