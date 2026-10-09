<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Models;

use Enadstack\LaravelRoles\Users\Enums\UserStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property UserStatus $status
 * @property int|null $invited_by
 * @property Carbon|null $invited_at
 * @property Carbon|null $activated_at
 * @property Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property int $failed_login_count
 * @property Carbon|null $locked_until
 * @property Carbon|null $mfa_enabled_at
 * @property string|null $mfa_method
 * @property Carbon|null $password_changed_at
 * @property string|null $status_reason
 */
class UserAccessState extends Model
{
    protected $table = 'user_access_states';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
            'invited_at' => 'datetime',
            'activated_at' => 'datetime',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'mfa_enabled_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'failed_login_count' => 'integer',
        ];
    }

    /** @return BelongsTo<Model, $this> */
    public function user(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = (string) config('roles.user_model');

        return $this->belongsTo($model, 'user_id');
    }

    /**
     * The user's state row, locked for update, created (status active) first when missing. The insert ignores a
     * concurrent insert of the same `user_id` (unique index), so two writers can never collide on creation.
     */
    public static function lockedFor(int|string $userId): self
    {
        $now = now();
        static::query()->insertOrIgnore(['user_id' => $userId, 'status' => UserStatus::Active->value, 'created_at' => $now, 'updated_at' => $now]);

        return static::query()->where('user_id', $userId)->lockForUpdate()->firstOrFail();
    }

    /** Active signs in; Locked only once `locked_until` has been reached (at exactly `locked_until` the lock is over); everything else never. */
    public function canSignInNow(): bool
    {
        return match ($this->status) {
            UserStatus::Active => true,
            UserStatus::Locked => $this->locked_until !== null && ! $this->locked_until->isFuture(),
            default => false,
        };
    }
}
