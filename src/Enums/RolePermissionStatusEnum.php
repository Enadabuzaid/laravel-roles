<?php

namespace Enadstack\LaravelRoles\Enums;

enum RolePermissionStatusEnum: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case DELETED = 'deleted';

    /**
     * Get all status values
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get status label
     */
    public function label(): string
    {
        return match($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::DELETED => 'Deleted',
        };
    }

    /**
     * Get status color for UI
     */
    public function color(): string
    {
        return match($this) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'warning',
            self::DELETED => 'danger',
        };
    }

    /**
     * Check if status is active
     */
    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * Check if status is inactive
     */
    public function isInactive(): bool
    {
        return $this === self::INACTIVE;
    }

    /**
     * Check if status is deleted
     */
    public function isDeleted(): bool
    {
        return $this === self::DELETED;
    }

    /**
     * Get badge HTML
     */
    public function badge(): string
    {
        return sprintf(
            '<span class="badge badge-%s">%s</span>',
            $this->color(),
            $this->label()
        );
    }
}

