<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Console;

use Enadstack\LaravelRoles\Users\Enums\UserStatus;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class BackfillUsersCommand extends Command
{
    protected $signature = 'roles:backfill-users {--dry-run : Report how many rows would be created without writing}';

    protected $description = 'Create an access state row for every user that does not have one.';

    private const CHUNK = 200;

    public function handle(): int
    {
        $model = config('roles.user_model');

        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            $this->components->error('roles.user_model is not a valid Eloquent model.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('user_access_states')) {
            $this->components->error('The user_access_states table is missing — run php artisan migrate first.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $status = UserStatus::tryFrom((string) config('roles.users.default_status', 'active')) ?? UserStatus::Active;
        $key = (new $model)->getKeyName();
        $created = 0;

        $model::query()
            ->whereNotIn((new $model)->getQualifiedKeyName(), UserAccessState::query()->select('user_id'))
            ->orderBy($key)
            ->chunkById(self::CHUNK, function ($users) use ($dry, $status, $key, &$created): void {
                $now = now();
                $rows = [];

                foreach ($users as $user) {
                    $rows[] = [
                        'user_id' => $user->getAttribute($key),
                        'status' => $status->value,
                        'activated_at' => $status === UserStatus::Active ? $now : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                $created += count($rows);

                if (! $dry && $rows !== []) {
                    UserAccessState::query()->insertOrIgnore($rows);
                }
            }, $key);

        $this->components->info(($dry ? 'Would create' : 'Created')." {$created} access state row(s).");

        return self::SUCCESS;
    }
}
