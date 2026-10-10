<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Console;

use Enadstack\LaravelRoles\Concerns\HasAccess;
use Enadstack\LaravelRoles\Contracts\AccessUser;
use Enadstack\LaravelRoles\Roles\Actions\SeedDefaultRoles;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Users\Actions\ReactivateUser;
use Enadstack\LaravelRoles\Users\Enums\UserStatus;
use Enadstack\LaravelRoles\Users\Support\SignInGate;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class OwnerCommand extends Command
{
    protected $signature = 'roles:owner {email : Email of the user to make owner}';

    protected $description = 'Make a user the owner (super role).';

    public function handle(SeedDefaultRoles $seed): int
    {
        $model = config('roles.user_model');

        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            $this->components->error('roles.user_model is not a valid Eloquent model.');

            return self::FAILURE;
        }

        if (! in_array(HasAccess::class, class_uses_recursive($model), true)) {
            $this->components->error("{$model} must use the HasAccess trait.");

            return self::FAILURE;
        }

        if (! is_subclass_of($model, AccessUser::class)) {
            $this->components->error("{$model} must implement ".AccessUser::class.'.');

            return self::FAILURE;
        }

        $user = $model::query()->where('email', (string) (is_string($email = $this->argument('email')) ? $email : ''))->first();

        if (! $user instanceof AccessUser || ! method_exists($user, 'assignAccessRole')) {
            $this->components->error('No user found with that email.');

            return self::FAILURE;
        }

        $super = (string) config('roles.super_role');
        $role = app(ActorContext::class)->runAs(null, fn () => $seed->handle(only: [$super])->get($super));

        if ($role === null) {
            $this->components->error("No default role template for [{$super}] in roles.default_roles.");

            return self::FAILURE;
        }

        if (! $this->makeSignable($user)) {
            return self::FAILURE;
        }

        app(ActorContext::class)->runAs(null, fn () => $user->assignAccessRole($role, confirmed: true));
        $this->components->info("{$user->getAttribute('email')} is now [{$super}].");

        return self::SUCCESS;
    }

    /**
     * A suspended or deactivated target is reactivated (audited as the system actor) so the new owner can actually
     * sign in; an invited or pending-verification target is refused with a clear message.
     */
    private function makeSignable(Model $user): bool
    {
        if (! app(SignInGate::class)->enabled()) {
            return true;
        }

        $status = $user instanceof AccessUser && method_exists($user, 'accessStatus') ? $user->accessStatus() : UserStatus::Active;

        if (in_array($status, [UserStatus::Invited, UserStatus::PendingVerification], true)) {
            $this->components->error("{$user->getAttribute('email')} is {$status->value}: they must accept the invitation or verify the account first.");

            return false;
        }

        if (in_array($status, [UserStatus::Suspended, UserStatus::Deactivated], true)) {
            app(ActorContext::class)->runAs(null, fn () => app(ReactivateUser::class)->handle($user));
            $this->components->warn("{$user->getAttribute('email')} was {$status->value} and has been reactivated.");
        }

        return true;
    }
}
