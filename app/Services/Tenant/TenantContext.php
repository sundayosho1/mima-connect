<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Contracts\Tenant\TenantInterface;
use App\Models\Master\Tenant;

/**
 * Tenant Context Service
 * 
 * Manages the current tenant context throughout the request lifecycle.
 * Provides global access to the current tenant and handles context switching.
 * 
 * @package App\Services\Tenant
 */
class TenantContext
{
    /**
     * The current tenant instance.
     */
    private static ?TenantInterface $currentTenant = null;

    /**
     * Whether the context has been initialized.
     */
    private static bool $initialized = false;

    /**
     * Context metadata.
     */
    private static array $metadata = [];

    /**
     * Set the current tenant.
     */
    public static function set(TenantInterface $tenant): void
    {
        self::$currentTenant = $tenant;
        self::$initialized = true;
    }

    /**
     * Get the current tenant.
     */
    public static function get(): ?TenantInterface
    {
        return self::$currentTenant;
    }

    /**
     * Get the current tenant ID.
     */
    public static function getId(): ?int
    {
        return self::$currentTenant?->getKey();
    }

    /**
     * Get the current tenant UUID.
     */
    public static function getUuid(): ?string
    {
        return self::$currentTenant?->getUuid();
    }

    /**
     * Get the current tenant's database name.
     */
    public static function getDatabaseName(): ?string
    {
        return self::$currentTenant?->getDatabaseName();
    }

    /**
     * Check if a tenant is currently set.
     */
    public static function hasTenant(): bool
    {
        return self::$currentTenant !== null;
    }

    /**
     * Check if the context has been initialized.
     */
    public static function isInitialized(): bool
    {
        return self::$initialized;
    }

    /**
     * Clear the current tenant context.
     */
    public static function clear(): void
    {
        self::$currentTenant = null;
        self::$initialized = false;
        self::$metadata = [];
    }

    /**
     * Check if a feature is enabled for the current tenant.
     */
    public static function hasFeature(string $feature): bool
    {
        if (!self::hasTenant()) {
            return false;
        }

        return self::$currentTenant->hasFeature($feature);
    }

    /**
     * Check if the current tenant has access to a module.
     */
    public static function hasModuleAccess(string $module): bool
    {
        if (!self::hasTenant()) {
            return false;
        }

        return self::$currentTenant->hasModuleAccess($module);
    }

    /**
     * Check if the current tenant is within plan limits.
     */
    public static function isWithinLimit(string $metric, int $additional = 0): bool
    {
        if (!self::hasTenant()) {
            return false;
        }

        return self::$currentTenant->isWithinLimit($metric, $additional);
    }

    /**
     * Get the usage percentage for a metric.
     */
    public static function getUsagePercentage(string $metric): float
    {
        if (!self::hasTenant()) {
            return 0;
        }

        return self::$currentTenant->getUsagePercentage($metric);
    }

    /**
     * Check if the current tenant is active.
     */
    public static function isActive(): bool
    {
        if (!self::hasTenant()) {
            return false;
        }

        return self::$currentTenant->isActive();
    }

    /**
     * Check if the current tenant is in trial.
     */
    public static function isTrialing(): bool
    {
        if (!self::hasTenant()) {
            return false;
        }

        return self::$currentTenant->isTrialing();
    }

    /**
     * Get the current tenant's settings.
     */
    public static function getSettings(?string $key = null): mixed
    {
        if (!self::hasTenant()) {
            return $key ? null : [];
        }

        $settings = self::$currentTenant->settings_json ?? [];

        if ($key === null) {
            return $settings;
        }

        return $settings[$key] ?? null;
    }

    /**
     * Get the current tenant's branding.
     */
    public static function getBranding(?string $key = null): mixed
    {
        if (!self::hasTenant()) {
            return $key ? null : [];
        }

        $branding = self::$currentTenant->branding_json ?? [];

        if ($key === null) {
            return $branding;
        }

        return $branding[$key] ?? null;
    }

    /**
     * Get the current tenant's timezone.
     */
    public static function getTimezone(): string
    {
        return self::getSettings('timezone') ?? config('app.timezone', 'UTC');
    }

    /**
     * Get the current tenant's currency.
     */
    public static function getCurrency(): string
    {
        return self::getSettings('currency') ?? 'NGN';
    }

    /**
     * Get the current tenant's locale.
     */
    public static function getLocale(): string
    {
        return self::getSettings('locale') ?? config('app.locale', 'en');
    }

    /**
     * Set context metadata.
     */
    public static function setMetadata(string $key, mixed $value): void
    {
        self::$metadata[$key] = $value;
    }

    /**
     * Get context metadata.
     */
    public static function getMetadata(string $key, mixed $default = null): mixed
    {
        return self::$metadata[$key] ?? $default;
    }

    /**
     * Get all context metadata.
     */
    public static function getAllMetadata(): array
    {
        return self::$metadata;
    }

    /**
     * Require a tenant to be set.
     * 
     * @throws \RuntimeException
     */
    public static function requireTenant(): TenantInterface
    {
        if (!self::hasTenant()) {
            throw new \RuntimeException('No tenant context is set');
        }

        return self::$currentTenant;
    }

    /**
     * Execute a callback within a specific tenant context.
     * 
     * @template T
     * @param Tenant $tenant
     * @param callable(): T $callback
     * @return T
     */
    public static function executeAs(Tenant $tenant, callable $callback): mixed
    {
        $previousTenant = self::$currentTenant;

        try {
            self::set($tenant);
            return $callback();
        } finally {
            self::$currentTenant = $previousTenant;
        }
    }

    /**
     * Get a summary of the current context.
     */
    public static function getSummary(): array
    {
        if (!self::hasTenant()) {
            return [
                'has_tenant' => false,
                'initialized' => self::$initialized,
            ];
        }

        return [
            'has_tenant' => true,
            'initialized' => self::$initialized,
            'tenant_id' => self::getId(),
            'tenant_uuid' => self::getUuid(),
            'database_name' => self::getDatabaseName(),
            'is_active' => self::isActive(),
            'is_trialing' => self::isTrialing(),
            'timezone' => self::getTimezone(),
            'currency' => self::getCurrency(),
            'locale' => self::getLocale(),
            'metadata' => self::$metadata,
        ];
    }
}
