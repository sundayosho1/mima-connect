<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for the multi-tenant architecture
    | of the MiMaConnect platform. Each tenant (company) operates in complete
    | data isolation with a dedicated database.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Base Domain
    |--------------------------------------------------------------------------
    |
    | The base domain for the application. Subdomains will be created
    | under this domain (e.g., company.mimaconnect.com).
    |
    */
    'base_domain' => env('APP_BASE_DOMAIN', 'mimaconnect.com'),

    /*
    |--------------------------------------------------------------------------
    | Main Site URL
    |--------------------------------------------------------------------------
    |
    | The URL of the main marketing/landing page. Users without a tenant
    | context will be redirected here.
    |
    */
    'main_site_url' => env('APP_MAIN_SITE_URL', 'https://mimaconnect.com'),

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for tenant database provisioning.
    |
    */
    'database' => [
        // Prefix for tenant database names
        'name_prefix' => env('TENANT_DB_PREFIX', 'mimaconn_tenant_'),

        // Database character set
        'charset' => 'utf8mb4',

        // Database collation
        'collation' => 'utf8mb4_unicode_ci',

        // Whether to use database-specific users (false = use shared user)
        'use_separate_users' => env('TENANT_DB_SEPARATE_USERS', false),

        // Database connection settings
        'host' => env('TENANT_DB_HOST', '127.0.0.1'),
        'port' => env('TENANT_DB_PORT', '3306'),
        'username' => env('TENANT_DB_USERNAME', 'mimaconn_mima_dev'),
        'password' => env('TENANT_DB_PASSWORD', 'MimaConnect2025'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Resolution
    |--------------------------------------------------------------------------
    |
    | Configuration for how tenants are resolved from requests.
    |
    */
    'resolution' => [
        // Priority order for tenant resolution strategies
        'strategies' => [
            'subdomain',     // company.mimaconnect.com (highest priority)
            'custom_domain', // erp.company.com
            'header',        // X-Tenant-ID header
            'session',       // Fallback to session
        ],

        // Header name for tenant identification
        'header_name' => 'X-Tenant-ID',

        // Session key for tenant identification
        'session_key' => 'current_tenant_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    |
    | Default settings applied to new tenants.
    |
    */
    'defaults' => [
        'timezone' => 'Africa/Lagos',
        'currency' => 'NGN',
        'locale' => 'en_NG',
        'date_format' => 'd/m/Y',
        'time_format' => 'h:i A',
        'first_day_of_week' => 1, // Monday
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Branding
    |--------------------------------------------------------------------------
    |
    | Default branding settings for new tenants.
    |
    */
    'branding' => [
        'primary_color' => '#1a56db',
        'secondary_color' => '#1c64f2',
        'accent_color' => '#3f83f8',
        'logo_url' => null,
        'favicon_url' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Trial Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for tenant trial periods.
    |
    */
    'trial' => [
        // Default trial days (can be overridden per plan)
        'default_days' => 30,

        // Maximum trial days allowed
        'max_days' => 90,

        // Whether to require credit card for trial
        'require_card' => false,

        // Reminder days before trial expires
        'reminder_days' => [7, 3, 1],
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for tenant database migrations.
    |
    */
    'migrations' => [
        // Default batch size for bulk migrations
        'batch_size' => 100,

        // Sleep time between batches (seconds)
        'sleep_seconds' => 1,

        // Path to tenant migrations
        'path' => 'database/migrations/tenant',

        // Whether to run migrations automatically on tenant creation
        'auto_run' => true,

        // Retry attempts for failed migrations
        'max_retries' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for tenant file storage.
    |
    */
    'storage' => [
        // Disk for tenant files
        'disk' => env('TENANT_STORAGE_DISK', 'local'),

        // Base path for tenant files
        'base_path' => 'tenants',

        // Whether to isolate tenant files
        'isolate' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for tenant-specific caching.
    |
    */
    'cache' => [
        // Prefix for tenant cache keys
        'key_prefix' => 'tenant',

        // Default cache TTL (seconds)
        'ttl' => 3600,

        // Whether to tag cache by tenant
        'use_tags' => false, // Set to true when using Redis
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Security settings for multi-tenant isolation.
    |
    */
    'security' => [
        // Enforce tenant isolation on all queries
        'enforce_isolation' => true,

        // Block cross-tenant data access attempts
        'block_cross_tenant' => true,

        // Log cross-tenant access attempts
        'log_violations' => true,

        // Encrypt tenant database passwords
        'encrypt_passwords' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits Configuration
    |--------------------------------------------------------------------------
    |
    | Default limits for new tenants.
    |
    */
    'limits' => [
        // Warning threshold percentage (send alert at this usage)
        'warning_threshold' => 80,

        // Critical threshold percentage (urgent alert)
        'critical_threshold' => 95,

        // Enforce hard limits
        'enforce_hard_limits' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Provisioning Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for tenant provisioning.
    |
    */
    'provisioning' => [
        // Default plan ID for new tenants
        'default_plan_id' => 1,

        // Auto-create database on provisioning
        'auto_create_database' => true,

        // Auto-run migrations on provisioning
        'auto_run_migrations' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Subdomain Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for subdomain-based tenant resolution.
    |
    */
    'subdomain' => [
        'enabled' => true,
        'base_domain' => env('APP_BASE_DOMAIN', 'mimaconnect.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Domain Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for custom domain tenant resolution.
    |
    */
    'custom_domain' => [
        'enabled' => true,
        'requires_verification' => true,
        'verification_method' => 'dns_txt', // dns_txt or file
    ],
];