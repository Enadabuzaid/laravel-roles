<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
 */
class AccessInvitation extends Model
{
    protected $table = 'access_invitations';

    protected $guarded = [];

    protected $hidden = ['token_hash'];

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

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
