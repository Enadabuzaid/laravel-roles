<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Concerns\HasAccess;
use Enadstack\LaravelRoles\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Tests\Support\TestUser;

it('seeds default roles', function () {
    $this->artisan('roles:seed')->assertExitCode(0);

    expect(Role::count())->toBe(4);
});

it('makes a user the owner, even when the owner role was trashed', function () {
    $user = TestUser::create(['name' => 'A', 'email' => 'boss@example.test', 'password' => 'x']);
    $this->artisan('roles:seed')->assertExitCode(0);
    Role::where('name', 'owner')->first()->delete();

    $this->artisan('roles:owner boss@example.test')->assertExitCode(0);

    expect($user->fresh()->isOwner())->toBeTrue();
});

it('fails for an unknown email', function () {
    $this->artisan('roles:owner nobody@example.test')->assertExitCode(1);
});

it('fails cleanly when the user model lacks the HasAccess trait', function () {
    config(['roles.user_model' => PlainModel::class]);

    $this->artisan('roles:owner a@example.test')->expectsOutputToContain('must use the HasAccess trait')->assertExitCode(1);
});

it('fails cleanly when the user model does not implement AccessUser', function () {
    config(['roles.user_model' => TraitOnlyUser::class]);
    TraitOnlyUser::create(['name' => 'A', 'email' => 'a@example.test', 'password' => 'x']);

    $this->artisan('roles:owner a@example.test')->expectsOutputToContain('must implement')->assertExitCode(1);
});

class PlainModel extends Model
{
    protected $table = 'users';
}

class TraitOnlyUser extends User
{
    use HasAccess;

    protected $table = 'users';

    protected $guarded = [];
}
