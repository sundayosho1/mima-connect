<?php

declare(strict_types=1);

namespace App\Contracts\Tenant;

/**
 * Tenant Interface
 * 
 * Defines the contract for tenant entities in the multi-tenant system.
 * Ensures consistent implementation across different tenant types.
 * 
 * @package App\Contracts\Tenant
 */
interface TenantInterface
{
    /**
     * Get the unique identifier for the tenant.
     */
    public function getKey();

    /**
     * Get the UUID for the tenant.
     */
    public function getUuid(): string;

    /**
     * Get the tenant's domain (custom or subdomain).
     */
    public function getDomain(): string;

    /**
     * Get the tenant's subdomain.
     */
    public function getSubdomain(): string;

    /**
     * Get the tenant's database name.
     */
    public function getDatabaseName(): string;

    /**
     * Get the full database connection configuration.
     * 
     * @return array<string, mixed>
     */
    public function getDatabaseConfig(): array;

    /**
     * Check if the tenant is active.
     */
    public function isActive(): bool;

    /**
     * Check if the tenant is in trial period.
     */
    public function isTrialing(): bool;

    /**
     * Check if a feature is enabled for this tenant.
     */
    public function hasFeature(string $feature): bool;

    /**
     * Check if a module is accessible for this tenant.
     */
    public function hasModuleAccess(string $module): bool;

    /**
     * Check if the tenant is within plan limits.
     */
    public function isWithinLimit(string $metric, int $additional = 0): bool;

    /**
     * Get the usage percentage for a metric.
     */
    public function getUsagePercentage(string $metric): float;
}
