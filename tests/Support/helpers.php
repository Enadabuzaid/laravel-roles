<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Guardrails\Guardrails;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Support\Exceptions\AccessException;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Enadstack\LaravelRoles\Users\Policies\UserPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TestUser;

function makeUser(string $email = 'a@example.test'): TestUser
{
    /** @var class-string<TestUser> $class */
    $class = config('roles.user_model');

    return $class::create(['name' => 'A', 'email' => $email, 'password' => 'x']);
}

/**
 * Switches the host to another user model variant: config, auth provider, the user policy and, for soft deleting models, the `deleted_at` column.
 *
 * @param  class-string<TestUser>  $class
 */
function useHostUserModel(string $class): void
{
    if (in_array(SoftDeletes::class, class_uses_recursive($class), true) && ! Schema::hasColumn('users', 'deleted_at')) {
        Schema::table('users', fn (Blueprint $table) => $table->softDeletes());
    }

    config(['roles.user_model' => $class, 'auth.providers.users.model' => $class]);
    Gate::policy($class, UserPolicy::class);
}

/**
 * @param  list<string>  $permissions
 * @param  array<string, mixed>  $attributes
 */
function makeRole(string $name, array $permissions = [], array $attributes = []): Role
{
    $role = Role::create(['name' => $name, 'guard_name' => 'web', ...$attributes]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    if ($permissions !== []) {
        $role->givePermissionTo($permissions);
    }

    return $role;
}

function makeOwner(string $email = 'owner@example.test'): TestUser
{
    if (! Role::where('name', 'owner')->where('guard_name', 'web')->exists()) {
        makeRole('owner', [], ['is_system' => true]);
    }

    $user = makeUser($email);
    app(ActorContext::class)->runAs(null, fn () => $user->assignAccessRole('owner', confirmed: true));

    return $user;
}

function guardrailCode(Operation $operation): ?string
{
    try {
        app(Guardrails::class)->assert($operation);

        return null;
    } catch (AccessException $e) {
        expect($e->status)->toBe(403);

        return $e->errorCode;
    }
}

function makePermission(string $name, string $risk = 'low'): Permission
{
    return Permission::updateOrCreate(['name' => $name, 'guard_name' => 'web'], ['risk' => $risk]);
}

/** @param  array<string, mixed>  $attributes */
function makeState(Model $user, string $status = 'active', array $attributes = []): UserAccessState
{
    return UserAccessState::create(['user_id' => $user->getKey(), 'status' => $status, ...$attributes]);
}

/**
 * A signed-in actor holding a role with the given permissions (all users read permissions by default).
 *
 * @param  list<string>|null  $permissions
 */
function usersActor(?array $permissions = null, string $email = 'actor@example.test'): TestUser
{
    $user = makeUser($email);
    $user->assignRole(makeRole('actor-'.uniqid(), $permissions ?? ['access.users.view']));

    return $user;
}

function createSessionsTable(): void
{
    if (Schema::hasTable('sessions')) {
        DB::table('sessions')->delete();
        if (! Schema::hasColumn('sessions', 'user_id')) {
            Schema::table('sessions', fn (Blueprint $table) => $table->unsignedBigInteger('user_id')->nullable());
        }

        return;
    }

    Schema::create('sessions', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->foreignId('user_id')->nullable()->index();
        $table->string('ip_address', 45)->nullable();
        $table->text('user_agent')->nullable();
        $table->longText('payload');
        $table->integer('last_activity')->index();
    });
}

function insertSession(string $id, ?int $userId): void
{
    DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'payload' => 'x', 'last_activity' => time()]);
}

/**
 * Writes one audit row directly (bypassing the recorder) with sensible defaults.
 *
 * @param  array<string, mixed>  $attributes
 */
function makeAuditEntry(array $attributes = []): ActionLog
{
    $log = new ActionLog;
    $log->forceFill([
        'event' => 'users.updated',
        'module' => 'users',
        'severity' => 'info',
        'created_at' => now(),
        'updated_at' => now(),
        ...$attributes,
    ])->save();

    return $log;
}

/** A signed-in user holding exactly these package permissions. */
function auditActor(array $permissions = ['access.audit.view'], string $email = 'auditor@example.test'): TestUser
{
    $user = makeUser($email);
    $user->assignRole(makeRole('auditor-'.uniqid(), $permissions));

    return $user;
}

/** An owner whose own setup entries are cleared, so a test sees only the rows it writes. */
function auditOwner(string $email = 'owner@example.test'): TestUser
{
    $owner = makeOwner($email);
    ActionLog::query()->delete();

    return $owner;
}
