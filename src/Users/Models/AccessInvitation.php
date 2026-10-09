<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $email
 * @property int|null $role_id
 * @property string $token_hash
 * @property int|null $invited_by
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 */
class AccessInvitation extends Model
{
    protected $table = 'access_invitations';

    protected $guarded = [];

    protected $hidden = ['token_hash'];

    /** Whether the invitation mail went out; set by the create and resend Actions, never persisted. */
    public bool $mailSent = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @param  Builder<AccessInvitation>  $query */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    /** @param  Builder<AccessInvitation>  $query */
    public function scopeAccepted(Builder $query): void
    {
        $query->whereNotNull('accepted_at');
    }

    /** @param  Builder<AccessInvitation>  $query */
    public function scopeRevoked(Builder $query): void
    {
        $query->whereNull('accepted_at')->whereNotNull('revoked_at');
    }

    /** @param  Builder<AccessInvitation>  $query */
    public function scopeExpired(Builder $query): void
    {
        $query->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '<=', now());
    }

    /** @return BelongsTo<Model, $this> the invited role; trashed roles are kept so history still reads */
    public function role(): BelongsTo
    {
        /** @var class-string<Model> $role */
        $role = (string) config('permission.models.role');

        return $this->belongsTo($role, 'role_id')->withTrashed(); // @phpstan-ignore method.notFound
    }

    /** @return BelongsTo<Model, $this> */
    public function inviter(): BelongsTo
    {
        /** @var class-string<Model> $user */
        $user = (string) config('roles.user_model');

        return $this->belongsTo($user, 'invited_by');
    }

    /** @return 'pending'|'accepted'|'revoked'|'expired' */
    public function status(): string
    {
        return match (true) {
            $this->accepted_at !== null => 'accepted',
            $this->revoked_at !== null => 'revoked',
            $this->expires_at->isPast() => 'expired',
            default => 'pending',
        };
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
