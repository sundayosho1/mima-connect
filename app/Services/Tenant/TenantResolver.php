<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Master\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tenant Resolver Service
 * 
 * Resolves the current tenant from various request sources:
 * - Subdomain (e.g., company.mimaconnect.com)
 * - Custom domain (e.g., erp.company.com)
 * - Header (X-Tenant-ID for API requests)
 * - Session (fallback for web routes)
 * 
 * @package App\Services\Tenant
 */
class TenantResolver
{
    /**
     * Header name for tenant identification.
     */
    private const TENANT_HEADER = 'X-Tenant-ID';

    /**
     * Session key for tenant identification.
     */
    private const TENANT_SESSION_KEY = 'current_tenant_id';

    /**
     * Cache of resolved tenants.
     */
    private static array $cache = [];

    /**
     * Resolve tenant from request.
     */
    public function resolve(Request $request): ?Tenant
    {
        // Try each resolution strategy in order
        $strategies = [
            'resolveFromHeader',
            'resolveFromSubdomain',
            'resolveFromCustomDomain',
            'resolveFromSession',
        ];

        foreach ($strategies as $strategy) {
            $tenant = $this->{$strategy}($request);

            if ($tenant !== null) {
                $this->cacheTenant($tenant);
                return $tenant;
            }
        }

        return null;
    }

    /**
     * Resolve tenant from header.
     */
    private function resolveFromHeader(Request $request): ?Tenant
    {
        $tenantId = $request->header(self::TENANT_HEADER);

        if (empty($tenantId)) {
            return null;
        }

        // Try to find by UUID first, then by ID
        $tenant = Tenant::where('uuid', $tenantId)->first()
            ?? Tenant::find($tenantId);

        if ($tenant) {
            Log::debug('Tenant resolved from header', [
                'tenant_id' => $tenant->id,
                'strategy' => 'header',
            ]);
        }

        return $tenant;
    }

    /**
     * Resolve tenant from subdomain.
     */
    private function resolveFromSubdomain(Request $request): ?Tenant
    {
        $host = $request->getHost();
        $baseDomain = config('app.base_domain', 'mimaconnect.com');

        // Check if this is a subdomain request
        if (!str_ends_with($host, $baseDomain)) {
            return null;
        }

        // Extract subdomain
        $subdomain = str_replace(".{$baseDomain}", '', $host);

        // Skip www and root domain
        if (in_array($subdomain, ['www', ''], true)) {
            return null;
        }

        $tenant = Tenant::bySubdomain($subdomain)->first();

        if ($tenant) {
            Log::debug('Tenant resolved from subdomain', [
                'tenant_id' => $tenant->id,
                'subdomain' => $subdomain,
                'strategy' => 'subdomain',
            ]);
        }

        return $tenant;
    }

    /**
     * Resolve tenant from custom domain.
     */
    private function resolveFromCustomDomain(Request $request): ?Tenant
    {
        $host = $request->getHost();
        $baseDomain = config('app.base_domain', 'mimaconnect.com');

        // Skip if this is a subdomain of base domain
        if (str_ends_with($host, $baseDomain)) {
            return null;
        }

        $tenant = Tenant::byCustomDomain($host)->first();

        if ($tenant) {
            Log::debug('Tenant resolved from custom domain', [
                'tenant_id' => $tenant->id,
                'domain' => $host,
                'strategy' => 'custom_domain',
            ]);
        }

        return $tenant;
    }

    /**
     * Resolve tenant from session.
     */
    private function resolveFromSession(Request $request): ?Tenant
    {
        if (!$request->hasSession()) {
            return null;
        }

        $tenantId = $request->session()->get(self::TENANT_SESSION_KEY);

        if (empty($tenantId)) {
            return null;
        }

        $tenant = Tenant::find($tenantId);

        if ($tenant) {
            Log::debug('Tenant resolved from session', [
                'tenant_id' => $tenant->id,
                'strategy' => 'session',
            ]);
        }

        return $tenant;
    }

    /**
     * Store tenant in session.
     */
    public function storeInSession(Request $request, Tenant $tenant): void
    {
        if ($request->hasSession()) {
            $request->session()->put(self::TENANT_SESSION_KEY, $tenant->id);
        }
    }

    /**
     * Clear tenant from session.
     */
    public function clearFromSession(Request $request): void
    {
        if ($request->hasSession()) {
            $request->session()->forget(self::TENANT_SESSION_KEY);
        }
    }

    /**
     * Configure database connection for tenant.
     */
    public function configureDatabase(Tenant $tenant): void
    {
        $config = $tenant->getDatabaseConfig();

        Config::set('database.connections.tenant', $config);
        DB::purge('tenant');
        DB::reconnect('tenant');

        Log::debug('Database configured for tenant', [
            'tenant_id' => $tenant->id,
            'database' => $tenant->database_name,
        ]);
    }

    /**
     * Cache a resolved tenant.
     */
    private function cacheTenant(Tenant $tenant): void
    {
        self::$cache[$tenant->id] = $tenant;
        self::$cache[$tenant->uuid] = $tenant;
        self::$cache[$tenant->subdomain] = $tenant;
    }

    /**
     * Get cached tenant.
     */
    public function getCached(string $key): ?Tenant
    {
        return self::$cache[$key] ?? null;
    }

    /**
     * Clear the tenant cache.
     */
    public function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * Get resolution summary for debugging.
     */
    public function getResolutionSummary(Request $request): array
    {
        return [
            'host' => $request->getHost(),
            'base_domain' => config('app.base_domain'),
            'header_tenant_id' => $request->header(self::TENANT_HEADER),
            'session_tenant_id' => $request->hasSession() 
                ? $request->session()->get(self::TENANT_SESSION_KEY) 
                : null,
            'cache_keys' => array_keys(self::$cache),
        ];
    }
}
