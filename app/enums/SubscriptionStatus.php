<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

/**
 * Subscription Status Enum
 * 
 * Represents the subscription lifecycle states for a tenant.
 * 
 * @package App\Enums\Tenant
 */
enum SubscriptionStatus: string
{
    /**
     * Tenant is in trial period.
     */
    case TRIALING = 'trialing';

    /**
     * Subscription is active and paid.
     */
    case ACTIVE = 'active';

    /**
     * Payment is past due.
     */
    case PAST_DUE = 'past_due';

    /**
     * Subscription has been cancelled.
     */
    case CANCELLED = 'cancelled';

    /**
     * Subscription has been suspended.
     */
    case SUSPENDED = 'suspended';

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::TRIALING => 'Trialing',
            self::ACTIVE => 'Active',
            self::PAST_DUE => 'Past Due',
            self::CANCELLED => 'Cancelled',
            self::SUSPENDED => 'Suspended',
        };
    }

    /**
     * Get the description.
     */
    public function description(): string
    {
        return match ($this) {
            self::TRIALING => 'Tenant is in trial period.',
            self::ACTIVE => 'Subscription is active and paid.',
            self::PAST_DUE => 'Payment is past due. Access may be restricted.',
            self::CANCELLED => 'Subscription has been cancelled.',
            self::SUSPENDED => 'Subscription has been suspended.',
        };
    }

    /**
     * Get the color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::TRIALING => 'blue',
            self::ACTIVE => 'green',
            self::PAST_DUE => 'orange',
            self::CANCELLED => 'red',
            self::SUSPENDED => 'red',
        };
    }

    /**
     * Check if the subscription status allows full access.
     */
    public function isActive(): bool
    {
        return in_array($this, [self::ACTIVE, self::TRIALING], true);
    }

    /**
     * Check if the subscription status requires payment.
     */
    public function requiresPayment(): bool
    {
        return in_array($this, [self::ACTIVE, self::PAST_DUE], true);
    }

    /**
     * Check if the subscription can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this, [self::ACTIVE, self::TRIALING], true);
    }

    /**
     * Check if the subscription can be reactivated.
     */
    public function canBeReactivated(): bool
    {
        return in_array($this, [self::CANCELLED, self::SUSPENDED], true);
    }

    /**
     * Check if this is a paid status.
     */
    public function isPaid(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * Check if this is a trial status.
     */
    public function isTrial(): bool
    {
        return $this === self::TRIALING;
    }

    /**
     * Get all active statuses.
     */
    public static function activeStatuses(): array
    {
        return [self::ACTIVE, self::TRIALING];
    }

    /**
     * Get all problem statuses.
     */
    public static function problemStatuses(): array
    {
        return [self::PAST_DUE, self::CANCELLED, self::SUSPENDED];
    }
}
