<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Support\Exceptions\AccessException;
use Enadstack\LaravelRoles\Users\Actions\DeactivateUser;
use Enadstack\LaravelRoles\Users\Actions\DeleteUser;
use Enadstack\LaravelRoles\Users\Actions\ForcePasswordReset;
use Enadstack\LaravelRoles\Users\Actions\ReactivateUser;
use Enadstack\LaravelRoles\Users\Actions\RecordLastLogin;
use Enadstack\LaravelRoles\Users\Actions\SuspendUser;
use Enadstack\LaravelRoles\Users\Actions\UnlockUser;
use Enadstack\LaravelRoles\Users\Actions\UserLifecycleAction;
use Enadstack\LaravelRoles\Users\Models\AccessInvitation;
use Enadstack\LaravelRoles\Users\Models\SavedView;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Enadstack\LaravelRoles\Users\Support\SignInGate;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Support\TestUser;

function asSystem(Closure $callback): mixed
{
    return app(ActorContext::class)->runAs(null, $callback);
}

it('ends database sessions of the suspended user only', function (): void {
    config(['session.driver' => 'database']);
    createSessionsTable();
    $target = makeUser('t@example.test');
    $other = makeUser('o@example.test');
    insertSession('a', $target->id);
    insertSession('b', $target->id);
    insertSession('c', $other->id);
    insertSession('d', null);

    asSystem(fn () => app(SuspendUser::class)->handle($target, 'x'));

    expect(DB::table('sessions')->pluck('id')->sort()->values()->all())->toBe(['c', 'd']);
});

it('ends sessions on deactivate and delete but keeps them on reactivate', function (): void {
    config(['session.driver' => 'database']);
    createSessionsTable();
    $a = makeUser('a@example.test');
    $b = makeUser('b@example.test');
    insertSession('a', $a->id);
    insertSession('b', $b->id);

    asSystem(fn () => app(DeactivateUser::class)->handle($a, 'x'));
    asSystem(fn () => app(DeleteUser::class)->handle($b, 'b@example.test'));
    expect(DB::table('sessions')->count())->toBe(0);

    insertSession('again', $a->id);
    asSystem(fn () => app(ReactivateUser::class)->handle($a));
    expect(DB::table('sessions')->count())->toBe(1);
});

it('does not fail the action when the sessions table is missing or the driver is not database', function (): void {
    config(['session.driver' => 'database']);
    $target = makeUser('t@example.test');

    expect(asSystem(fn () => app(SuspendUser::class)->handle($target, 'x')))->toBeTrue();

    config(['session.driver' => 'file']);
    $other = makeUser('o@example.test');
    expect(asSystem(fn () => app(SuspendUser::class)->handle($other, 'x')))->toBeTrue();
});

class PersonalToken extends Model
{
    protected $table = 'personal_access_tokens';

    protected $guarded = [];
}

class TokenOwner extends TestUser
{
    public function tokens(): MorphMany
    {
        return $this->morphMany(PersonalToken::class, 'tokenable');
    }
}

function createTokensTable(): void
{
    Schema::create('personal_access_tokens', function (Blueprint $table): void {
        $table->id();
        $table->morphs('tokenable');
        $table->string('name');
        $table->timestamps();
    });
}

function issueToken(TestUser $user, string $name = 'api'): void
{
    PersonalToken::create(['tokenable_type' => $user->getMorphClass(), 'tokenable_id' => $user->id, 'name' => $name]);
}

it('revokes the tokens of a real morphMany relation on suspend, deactivate and delete, and only that users', function (string $action): void {
    createTokensTable();
    $target = TokenOwner::create(['name' => 'T', 'email' => 't@example.test', 'password' => 'x']);
    $other = TokenOwner::create(['name' => 'O', 'email' => 'o@example.test', 'password' => 'x']);
    issueToken($target, 'a');
    issueToken($target, 'b');
    issueToken($other);

    asSystem(fn () => match ($action) {
        'suspend' => app(SuspendUser::class)->handle($target, 'x'),
        'deactivate' => app(DeactivateUser::class)->handle($target, 'x'),
        'delete' => app(DeleteUser::class)->handle($target, 't@example.test'),
        'reset' => app(ForcePasswordReset::class)->handle($target),
    });

    expect(PersonalToken::where('tokenable_id', $target->id)->count())->toBe(0)->and(PersonalToken::where('tokenable_id', $other->id)->count())->toBe(1);
})->with(['suspend', 'deactivate', 'delete', 'reset']);

it('keeps tokens on reactivate and never fails the action when revoking tokens throws', function (): void {
    createTokensTable();
    $target = TokenOwner::create(['name' => 'T', 'email' => 't@example.test', 'password' => 'x']);
    makeState($target, 'suspended');
    issueToken($target);
    asSystem(fn () => app(ReactivateUser::class)->handle($target));
    expect(PersonalToken::count())->toBe(1);

    Schema::drop('personal_access_tokens');
    expect(asSystem(fn () => app(SuspendUser::class)->handle($target, 'x')))->toBeTrue();
    expect(UserAccessState::where('user_id', $target->id)->first()->status->value)->toBe('suspended');
});

it('creates the state row idempotently when another writer inserted it first', function (): void {
    $user = makeUser('t@example.test');
    $writer = new class extends UserLifecycleAction
    {
        /** @param  array<string, mixed>  $attributes */
        public function write(Model $user, array $attributes): void
        {
            DB::transaction(fn () => $this->writeState($user, null, $attributes));
        }
    };
    makeState($user, 'active', ['failed_login_count' => 3]); // inserted between our read (null) and our write

    $writer->write($user, ['status' => 'suspended', 'status_reason' => 'x']);
    $writer->write($user, ['status' => 'suspended', 'status_reason' => 'y']);

    expect(UserAccessState::where('user_id', $user->id)->count())->toBe(1)
        ->and(UserAccessState::where('user_id', $user->id)->first()->status_reason)->toBe('y');
});

it('records the last login on a row that appeared concurrently', function (): void {
    $user = makeUser('t@example.test');
    makeState($user, 'active', ['failed_login_count' => 3]);

    asSystem(fn () => app(RecordLastLogin::class)->handle($user, '203.0.113.1'));
    asSystem(fn () => app(RecordLastLogin::class)->handle($user, '203.0.113.2'));

    $state = UserAccessState::where('user_id', $user->id)->sole();
    expect($state->last_login_ip)->toBe('203.0.113.2')->and($state->failed_login_count)->toBe(0);
});

it('blocks login after suspend and allows it again after reactivate', function (): void {
    $user = TestUser::create(['name' => 'L', 'email' => 'l@example.test', 'password' => Hash::make('secret-pass')]);
    $credentials = ['email' => 'l@example.test', 'password' => 'secret-pass'];

    asSystem(fn () => app(SuspendUser::class)->handle($user, 'x'));
    expect(fn () => Auth::guard('web')->attempt($credentials))->toThrow(ValidationException::class);
    expect(Auth::guard('web')->check())->toBeFalse()->and(UserAccessState::where('user_id', $user->id)->first()->last_login_at)->toBeNull();

    asSystem(fn () => app(ReactivateUser::class)->handle($user));
    expect(Auth::guard('web')->attempt($credentials))->toBeTrue();
    expect(UserAccessState::where('user_id', $user->id)->first()->last_login_at)->not->toBeNull();
});

it('records the last login on a real attempt, creating the state row and resetting failures', function (): void {
    $user = TestUser::create(['name' => 'L', 'email' => 'l@example.test', 'password' => Hash::make('secret-pass')]);
    $other = TestUser::create(['name' => 'M', 'email' => 'm@example.test', 'password' => Hash::make('secret-pass')]);
    makeState($other, 'active', ['failed_login_count' => 4]);

    expect(Auth::guard('web')->attempt(['email' => 'l@example.test', 'password' => 'secret-pass']))->toBeTrue();
    Auth::guard('web')->logout();
    expect(Auth::guard('web')->attempt(['email' => 'm@example.test', 'password' => 'secret-pass']))->toBeTrue();

    $state = UserAccessState::where('user_id', $user->id)->first();
    expect($state->status->value)->toBe('active')->and($state->last_login_at)->not->toBeNull()
        ->and(UserAccessState::where('user_id', $other->id)->first()->failed_login_count)->toBe(0);
    expect(UserAccessState::count())->toBe(2);
});

it('stores the request ip as the last login ip', function (): void {
    $user = makeUser('l@example.test');
    request()->server->set('REMOTE_ADDR', '203.0.113.9');

    event(new Login('web', $user, false));

    expect(UserAccessState::where('user_id', $user->id)->value('last_login_ip'))->toBe('203.0.113.9');
});

it('never throws into the login flow when recording fails', function (): void {
    $user = makeUser('l@example.test');
    UserAccessState::saving(static function (): never {
        throw new RuntimeException('db down');
    });
    $reported = new ArrayObject;
    $this->app->instance(ExceptionHandler::class, new class($reported) implements ExceptionHandler
    {
        public function __construct(private readonly ArrayObject $reported) {}

        public function report(Throwable $e): void
        {
            $this->reported[] = $e;
        }

        public function shouldReport(Throwable $e): bool
        {
            return true;
        }

        public function render($request, Throwable $e): never
        {
            throw $e;
        }

        public function renderForConsole($output, Throwable $e): void {}
    });

    event(new Login('web', $user, false));
    UserAccessState::flushEventListeners();

    expect($reported)->toHaveCount(1)->and($reported[0]->getMessage())->toBe('db down');
});

it('fails open when the state table is missing', function (): void {
    $user = TestUser::create(['name' => 'L', 'email' => 'l@example.test', 'password' => Hash::make('secret-pass')]);
    Schema::drop('user_access_states');
    $this->app->forgetInstance(SignInGate::class);

    expect(Auth::guard('web')->attempt(['email' => 'l@example.test', 'password' => 'secret-pass']))->toBeTrue();
});

it('refuses inactive users before any last login is recorded', function (): void {
    $user = TestUser::create(['name' => 'L', 'email' => 'l@example.test', 'password' => Hash::make('secret-pass')]);
    makeState($user, 'suspended');

    expect(fn () => Auth::guard('web')->login($user))->toThrow(ValidationException::class);

    expect(UserAccessState::where('user_id', $user->id)->first()->last_login_at)->toBeNull();
});

it('leaves no audit entry and no state change when a lifecycle transaction rolls back', function (): void {
    $target = makeUser('t@example.test');
    ActionLog::query()->delete();

    expect(fn () => DB::transaction(function () use ($target): void {
        asSystem(fn () => app(SuspendUser::class)->handle($target, 'x'));
        throw new RuntimeException('outer rollback');
    }))->toThrow(RuntimeException::class);

    expect(UserAccessState::count())->toBe(0)->and(ActionLog::count())->toBe(0);
});

it('writes nothing when a delete is refused midway', function (): void {
    $actor = makeUser('actor@example.test');
    $target = makeUser('t@example.test');
    makeState($target, 'active');
    SavedView::create(['user_id' => $target->id, 'page' => 'users', 'name' => 'v', 'filters' => []]);
    ActionLog::query()->delete();

    expect(fn () => app(ActorContext::class)->runAs($actor, fn () => app(DeleteUser::class)->handle($target, 'wrong')))->toThrow(AccessException::class);

    expect(SavedView::count())->toBe(1)->and(UserAccessState::count())->toBe(1)->and(TestUser::find($target->id))->not->toBeNull()->and(ActionLog::count())->toBe(0);
});

it('refuses without a known actor outside console', function (): void {
    $target = makeUser('t@example.test');
    $this->app->resolving(ActorContext::class, fn () => null);
    $context = new class extends ActorContext
    {
        public function runningInConsole(): bool
        {
            return false;
        }
    };
    $this->app->instance(ActorContext::class, $context);

    expect(fn () => app(SuspendUser::class)->handle($target, 'x'))->toThrow(AccessException::class, 'Authentication required.');
    expect(fn () => app(DeleteUser::class)->handle($target, 't@example.test'))->toThrow(AccessException::class);
    expect(UserAccessState::count())->toBe(0);
});

it('uses the same number of queries however many related rows the user has', function (): void {
    $count = function (int $rows): array {
        $suspend = makeUser("s{$rows}@example.test");
        $delete = makeUser("d{$rows}@example.test");
        $role = makeRole("r{$rows}");
        for ($i = 0; $i < $rows; $i++) {
            SavedView::create(['user_id' => $delete->id, 'page' => 'users', 'name' => "v{$i}", 'filters' => []]);
            $delete->givePermissionTo(makePermission("p{$rows}.{$i}"));
            AccessInvitation::create(['email' => "i{$rows}{$i}@example.test", 'token_hash' => hash('sha256', "{$rows}{$i}"), 'invited_by' => $delete->id, 'expires_at' => now()->addDay()]);
        }
        $delete->assignRole($role);

        $measure = function (Closure $callback): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            asSystem($callback);
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        return [
            $measure(fn () => app(SuspendUser::class)->handle($suspend, 'x')),
            $measure(fn () => app(DeleteUser::class)->handle($delete, "d{$rows}@example.test")),
        ];
    };

    expect($count(25))->toBe($count(2));
});

it('locks the super role before reading anything else on postgres', function (string $action): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('FOR UPDATE row locking is asserted on pgsql only; sqlite has no row locks.');
    }

    makeOwner();
    $target = makeUser('t@example.test');
    makeState($target, $action === 'unlock' ? 'locked' : ($action === 'reactivate' ? 'suspended' : 'active'));

    DB::flushQueryLog();
    DB::enableQueryLog();
    asSystem(fn () => match ($action) {
        'suspend' => app(SuspendUser::class)->handle($target, 'x'),
        'deactivate' => app(DeactivateUser::class)->handle($target, 'x'),
        'reactivate' => app(ReactivateUser::class)->handle($target),
        'unlock' => app(UnlockUser::class)->handle($target),
        'reset' => app(ForcePasswordReset::class)->handle($target),
        'delete' => app(DeleteUser::class)->handle($target, 't@example.test'),
    });
    $queries = collect(DB::getQueryLog())->pluck('query')->map(fn (string $q): string => strtolower($q))->values();
    DB::disableQueryLog();

    $lock = $queries->search(fn (string $q): bool => str_contains($q, 'for update'));
    $firstTargetRead = $queries->search(fn (string $q): bool => str_contains($q, 'from "users"') || str_contains($q, 'user_access_states') || str_contains($q, 'model_has_roles'));

    expect($lock)->not->toBeFalse()->and($queries[$lock])->toContain('"roles"')->and($lock)->toBeLessThan($firstTargetRead);
})->with(['suspend', 'deactivate', 'reactivate', 'unlock', 'reset', 'delete']);

it('locks the super role before the active-owner count on postgres', function (string $action): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('FOR UPDATE row locking is asserted on pgsql only; sqlite has no row locks.');
    }

    makeOwner();
    $target = makeOwner('target@example.test');

    DB::flushQueryLog();
    DB::enableQueryLog();
    asSystem(fn () => match ($action) {
        'suspend' => app(SuspendUser::class)->handle($target, 'x'),
        'deactivate' => app(DeactivateUser::class)->handle($target, 'x'),
        'delete' => app(DeleteUser::class)->handle($target, 'target@example.test'),
    });
    $queries = collect(DB::getQueryLog())->pluck('query')->map(fn (string $q): string => strtolower($q))->values();
    DB::disableQueryLog();

    $lock = $queries->search(fn (string $q): bool => str_contains($q, 'for update') && str_contains($q, '"roles"'));
    $count = $queries->search(fn (string $q): bool => str_contains($q, 'count(*)') && str_contains($q, 'model_has_roles'));

    expect($lock)->not->toBeFalse()->and($count)->not->toBeFalse()->and($lock)->toBeLessThan($count);
})->with(['suspend', 'deactivate', 'delete']);
