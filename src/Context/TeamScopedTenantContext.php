<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Context;

/**
 * TeamScopedTenantContext
 *
 * Tenant context for Spatie's team-scoped mode.
 * Uses a single database with team_id foreign key for scoping.
 *
 * @package Enadstack\LaravelRoles\Context
 */
class TeamScopedTenantContext extends AbstractTenantContext
{
    /**
     * {@inheritdoc}
     */
    public function mode(): string
    {
        return 'team_scoped';
    }

    /**
     * {@inheritdoc}
     */
    public function tenantId(): int|string|null
    {
        // Return explicitly set tenant ID first
        if ($this->isExplicitlySet) {
            return $this->cachedTenantId;
        }

        // Otherwise, resolve from Spatie's container binding
        if (app()->bound('permission.team_id')) {
            return app('permission.team_id');
        }

        return null;
    }

    /**
     * {@inheritdoc}
     *
     * Returns a cache-safe scope key: 'team_{id}' or 'team_global' if no tenant.
     */
    public function scopeKey(): string
    {
        $tenantId = $this->tenantId();

        if ($tenantId === null) {
            return 'team_global';
        }

        return 'team_' . $tenantId;
    }

    /**
     * {@inheritdoc}
     *
     * Sets Spatie's team context for team_scoped mode.
     */
    public function applyToSpatie(): void
    {
        $tenantId = $this->tenantId();

        if ($tenantId !== null) {
            app()->instance('permission.team_id', $tenantId);
        }
    }

    /**
     * Get the team ID from authenticated user if available.
     *
     * @return int|string|null
     */
    public function getTeamIdFromUser(): int|string|null
    {
        $user = auth()->user();

        if (!$user) {
            return null;
        }

        $fk = $this->teamForeignKey();

        // Check if user has the team foreign key
        if (property_exists($user, $fk) || isset($user->$fk)) {
            return $user->$fk;
        }

        // Check for common team relationship patterns
        if (method_exists($user, 'currentTeam') && $user->currentTeam) {
            return $user->currentTeam->id;
        }

        if (method_exists($user, 'team') && $user->team) {
            return $user->team->id;
        }

        return null;
    }

    /**
     * Initialize context from authenticated user.
     *
     * @return void
     */
    public function initializeFromUser(): void
    {
        $teamId = $this->getTeamIdFromUser();

        if ($teamId !== null) {
            $this->setTenantId($teamId);
        }
    }
}
