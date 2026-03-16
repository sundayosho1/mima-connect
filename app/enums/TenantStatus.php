<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

/**
 * Tenant Status Enum
 * 
 * Represents the lifecycle states of a tenant in the MiMaConnect platform.
 * 
 * @package App\Enums\Tenant
 */
enum TenantStatus: string
{
    /**
     * Tenant is pending activation.
     * Database may not be fully provisioned yet.
     */
    case PENDING = 'pending';

    /**
     * Tenant is active and fully operational.
     */
    case ACTIVE = 'active';

    /**
     * Tenant has been suspended (non-payment, violation, etc.).
     */
    case SUSPENDED = 'suspended';

    /**
     * Tenant has been terminated (permanent closure).
     */
    case TERMINATED = 'terminated';

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::ACTIVE => 'Active',
            self::SUSPENDED => 'Suspended',
            self::TERMINATED => 'Terminated',
        };
    }

    /**
     * Get the description.
     */
    public function description(): string
    {
        return match ($this) {
            self::PENDING => 'Tenant is pending activation and database provisioning.',
            self::ACTIVE => 'Tenant is active and fully operational.',
            self::SUSPENDED => 'Tenant has been suspended due to non-payment or policy violation.',
            self::TERMINATED => 'Tenant has been permanently terminated.',
        };
    }

    /**
     * Get the color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'yellow',
            self::ACTIVE => 'green',
            self::SUSPENDED => 'red',
            self::TERMINATED => 'gray',
        };
    }

    /**
     * Check if the status allows access.
     */
    public function allowsAccess(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * Check if the status allows login.
     */
    public function allowsLogin(): bool
    {
        return in_array($this, [self::ACTIVE, self::SUSPENDED], true);
    }

    /**
     * Check if the status is terminal.
     */
    public function isTerminal(): bool
    {
        return $this === self::TERMINATED;
    }

    /**
     * Get all active statuses.
     */
    public static function activeStatuses(): array
    {
        return [self::ACTIVE, self::PENDING];
    }

    /**
     * Get all inactive statuses.
     */
    public static function inactiveStatuses(): array
    {
        return [self::SUSPENDED, self::TERMINATED];
    }
}
