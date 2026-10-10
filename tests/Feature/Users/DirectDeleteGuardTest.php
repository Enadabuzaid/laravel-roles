<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Users\Models\AccessInvitation;
use Enadstack\LaravelRoles\Users\Models\SavedView;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Enadstack\LaravelRoles\Users\Support\LastOwnerDeletionException;
use Illuminate\Support\Facades\Route;
use Tests\Support\TestUser;

it('refuses a direct delete of the last active owner and changes nothing', function (): void {
    $owner = makeOwner();
    makeState($owner);
    SavedView::create(['user_id' => $owner->id, 'page' => 'users', 'name' => 'v', 'filters' => []]);
    ActionLog::query()->delete();

    expect(fn () => $owner->delete())->toThrow(LastOwnerDeletionException::class);

    expect(TestUser::find($owner->id))->not->toBeNull()->and($owner->fresh()->getRoleNames()->all())->toBe(['owner'])
        ->and(UserAccessState::count())->toBe(1)->and(SavedView::count())->toBe(1)->and(ActionLog::count())->toBe(0);
});

it('does not count a suspended second owner', function (): void {
    $owner = makeOwner();
    $other = makeOwner('other@example.test');
    makeState($other, 'suspended');

    expect(fn () => $owner->delete())->toThrow(LastOwnerDeletionException::class);
});

it('renders the refusal as the guardrail.last_owner envelope on API routes', function (): void {
    $owner = makeOwner();
    Route::middleware('web')->delete('/api/access/_probe-delete', fn () => $owner->delete());

    $this->deleteJson('/api/access/_probe-delete')->assertForbidden()->assertJson(['success' => false, 'code' => 'guardrail.last_owner']);
    expect(TestUser::find($owner->id))->not->toBeNull();
});

it('deletes a non-last owner and cleans up with exactly one audit entry', function (): void {
    $owner = makeOwner();
    $leaving = makeOwner('leaving@example.test');
    makeState($leaving);
    SavedView::create(['user_id' => $leaving->id, 'page' => 'users', 'name' => 'v', 'filters' => []]);
    $pending = AccessInvitation::create(['email' => 'p@x.test', 'token_hash' => hash('sha256', 'p'), 'invited_by' => $leaving->id, 'expires_at' => now()->addDay()]);
    $accepted = AccessInvitation::create(['email' => 'a@x.test', 'token_hash' => hash('sha256', 'a'), 'invited_by' => $leaving->id, 'expires_at' => now()->addDay(), 'accepted_at' => now()]);
    ActionLog::query()->delete();

    $leaving->delete();

    expect(TestUser::find($leaving->id))->toBeNull()->and(TestUser::find($owner->id))->not->toBeNull()
        ->and(UserAccessState::where('user_id', $leaving->id)->exists())->toBeFalse()->and(SavedView::count())->toBe(0)
        ->and($pending->fresh()->revoked_at)->not->toBeNull()->and($pending->fresh()->invited_by)->toBeNull()->and($accepted->fresh()->invited_by)->toBeNull()
        ->and(DB::table('model_has_roles')->where('model_id', $leaving->id)->count())->toBe(0);
    $log = ActionLog::sole();
    expect($log->event)->toBe('users.deleted')->and($log->severity)->toBe('critical')->and($log->old_values['email'])->toBe('leaving@example.test')
        ->and($log->meta['was_owner'])->toBeTrue()->and($log->meta['revoked_invitations'])->toBe(1);
});

it('deletes an ordinary user with one audit entry', function (): void {
    $user = makeUser('plain@example.test');
    makeOwner();
    ActionLog::query()->delete();

    $user->delete();

    expect(ActionLog::sole()->event)->toBe('users.deleted')->and(ActionLog::sole()->meta['was_owner'])->toBeFalse();
});

it('writes no second audit entry when the DeleteUser action deleted the user', function (): void {
    makeOwner();
    $admin = usersActor(['access.users.view', 'access.users.delete'], 'admin@example.test');
    $target = makeUser('t@example.test');
    ActionLog::query()->delete();

    $this->actingAs($admin)->deleteJson("/api/access/users/{$target->id}", ['confirmation' => 't@example.test'])->assertNoContent();

    expect(ActionLog::where('event', 'users.deleted')->count())->toBe(1);
});

it('is inactive when the users module is off', function (): void {
    config(['roles.modules.users' => false]);
    $owner = makeOwner();

    $owner->delete();

    expect(TestUser::find($owner->id))->toBeNull();
});
