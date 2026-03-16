<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\SubscriptionStatus;
use App\Enums\Tenant\TenantStatus;
use App\Events\Tenant\TenantActivated;
use App\Events\Tenant\TenantCreated;
use App\Models\Master\Tenant;
use App\Models\Master\TenantPlan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Tenant Provisioning Service
 * 
 * Handles the complete lifecycle of tenant provisioning including:
 * - Database creation
 * - Schema initialization
 * - Admin user creation
 * - Configuration setup
 * 
 * @package App\Services\Tenant
 */
class TenantProvisioningService
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private readonly TenantMigrationService $migrationService,
        private readonly TenantContext $tenantContext
    ) {
    }

    /**
     * Provision a new tenant.
     * 
     * @param array<string, mixed> $data
     * @throws \Exception
     */
    public function provisionTenant(array $data): Tenant
    {
        return DB::connection('mysql')->transaction(function () use ($data) {
            // Step 1: Create tenant record
            $tenant = $this->createTenantRecord($data);

            // Step 2: Create tenant database
            $this->createTenantDatabase($tenant);

            // Step 3: Run migrations on tenant database
            $this->migrationService->runMigrationsForTenant($tenant);

            // Step 4: Seed initial data
            $this->seedInitialData($tenant, $data);

            // Step 5: Activate tenant
            $this->activateTenant($tenant);

            // Fire event
            event(new TenantCreated($tenant));

            Log::info('Tenant provisioned successfully', [
                'tenant_id' => $tenant->id,
                'company_name' => $tenant->company_name,
                'subdomain' => $tenant->subdomain,
            ]);

            return $tenant;
        });
    }

    /**
     * Create the tenant record in master database.
     * 
     * @param array<string, mixed> $data
     */
    private function createTenantRecord(array $data): Tenant
    {
        $uuid = (string) Str::uuid();
        $databaseName = "canmpefc_tenant_{$uuid}";
        $databaseUsername = config('database.connections.mysql.username');
        $databasePassword = $this->generateSecurePassword();

        $plan = $data['plan_id'] 
            ? TenantPlan::find($data['plan_id']) 
            : TenantPlan::bySlug('free')->first();

        if (!$plan) {
            throw new \InvalidArgumentException('Invalid plan selected');
        }

        $tenant = Tenant::create([
            'uuid' => $uuid,
            'company_name' => $data['company_name'],
            'company_email' => $data['company_email'],
            'company_phone' => $data['company_phone'] ?? null,
            'company_address' => $data['company_address'] ?? null,
            'company_website' => $data['company_website'] ?? null,
            'subdomain' => $this->generateSubdomain($data['company_name']),
            'custom_domain' => $data['custom_domain'] ?? null,
            'database_name' => $databaseName,
            'database_username' => $databaseUsername,
            'database_password_encrypted' => Crypt::encryptString($databasePassword),
            'plan_id' => $plan->id,
            'subscription_status' => SubscriptionStatus::TRIALING,
            'trial_ends_at' => now()->addDays($plan->trial_days),
            'settings_json' => $this->getDefaultSettings($data),
            'branding_json' => $this->getDefaultBranding(),
            'features_enabled_json' => $plan->features_json,
            'owner_name' => $data['owner_name'],
            'owner_email' => $data['owner_email'],
            'owner_phone' => $data['owner_phone'] ?? null,
            'status' => TenantStatus::PENDING,
        ]);

        Log::info('Tenant record created', ['tenant_id' => $tenant->id]);

        return $tenant;
    }

    /**
     * Create the tenant database.
     * 
     * @throws \Exception
     */
    private function createTenantDatabase(Tenant $tenant): void
    {
        $databaseName = $tenant->database_name;

        // Check if database already exists
        $exists = DB::connection('mysql')->select(
            "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?",
            [$databaseName]
        );

        if (!empty($exists)) {
            throw new \RuntimeException("Database {$databaseName} already exists");
        }

        // Create database
        DB::connection('mysql')->statement("CREATE DATABASE IF NOT EXISTS `{$databaseName}` 
            CHARACTER SET utf8mb4 
            COLLATE utf8mb4_unicode_ci");

        Log::info('Tenant database created', [
            'tenant_id' => $tenant->id,
            'database' => $databaseName,
        ]);
    }

    /**
     * Seed initial data in tenant database.
     * 
     * @param array<string, mixed> $data
     */
    private function seedInitialData(Tenant $tenant, array $data): void
    {
        // Switch to tenant connection
        $this->tenantContext->set($tenant);

        // Create admin user
        $this->createAdminUser($tenant, $data);

        // Seed default roles (Module 2 will expand this)
        $this->seedDefaultRoles($tenant);

        // Seed default settings
        $this->seedDefaultSettings($tenant);

        Log::info('Initial data seeded', ['tenant_id' => $tenant->id]);
    }

    /**
     * Create the admin user in tenant database.
     * 
     * @param array<string, mixed> $data
     */
    private function createAdminUser(Tenant $tenant, array $data): void
    {
        // This will be expanded in Module 2 (IAM)
        // For now, create basic user record
        DB::connection('tenant')->table('users')->insert([
            'tenant_id' => $tenant->id,
            'email' => $data['owner_email'],
            'password' => bcrypt($data['password'] ?? $this->generateSecurePassword()),
            'first_name' => $this->extractFirstName($data['owner_name']),
            'last_name' => $this->extractLastName($data['owner_name']),
            'phone' => $data['owner_phone'] ?? null,
            'email_verified_at' => now(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create tenant user link
        \App\Models\Master\TenantUser::create([
            'tenant_id' => $tenant->id,
            'user_id' => 1, // First user
            'role' => 'super_admin',
            'invitation_status' => 'accepted',
            'invitation_accepted_at' => now(),
            'is_active' => true,
        ]);

        Log::info('Admin user created', [
            'tenant_id' => $tenant->id,
            'email' => $data['owner_email'],
        ]);
    }

    /**
     * Seed default roles.
     */
    private function seedDefaultRoles(Tenant $tenant): void
    {
        // Will be expanded in Module 2 (IAM)
        $roles = [
            [
                'tenant_id' => $tenant->id,
                'name' => 'super_admin',
                'display_name' => 'Super Administrator',
                'description' => 'Full system access',
                'permissions_json' => ['*'],
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => $tenant->id,
                'name' => 'admin',
                'display_name' => 'Administrator',
                'description' => 'System administrator',
                'permissions_json' => ['users.*', 'settings.*', 'customers.*', 'properties.*'],
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::connection('tenant')->table('roles')->insert($roles);
    }

    /**
     * Seed default settings.
     */
    private function seedDefaultSettings(Tenant $tenant): void
    {
        // Will be expanded with settings module
        DB::connection('tenant')->table('settings')->insert([
            'tenant_id' => $tenant->id,
            'key' => 'company_name',
            'value' => $tenant->company_name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Activate the tenant.
     */
    private function activateTenant(Tenant $tenant): void
    {
        $tenant->update([
            'status' => TenantStatus::ACTIVE,
            'activated_at' => now(),
        ]);

        event(new TenantActivated($tenant));

        Log::info('Tenant activated', ['tenant_id' => $tenant->id]);
    }

    /**
     * Generate a unique subdomain from company name.
     */
    private function generateSubdomain(string $companyName): string
    {
        $base = Str::slug($companyName);
        $subdomain = $base;
        $counter = 1;

        // Ensure uniqueness
        while (Tenant::where('subdomain', $subdomain)->exists()) {
            $subdomain = "{$base}-{$counter}";
            $counter++;
        }

        return $subdomain;
    }

    /**
     * Generate a secure random password.
     */
    private function generateSecurePassword(int $length = 16): string
    {
        return Str::random($length);
    }

    /**
     * Get default settings for a new tenant.
     * 
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function getDefaultSettings(array $data): array
    {
        return [
            'timezone' => $data['timezone'] ?? 'Africa/Lagos',
            'currency' => $data['currency'] ?? 'NGN',
            'locale' => $data['locale'] ?? 'en_NG',
            'date_format' => $data['date_format'] ?? 'd/m/Y',
            'time_format' => $data['time_format'] ?? 'h:i A',
            'first_day_of_week' => $data['first_day_of_week'] ?? 1, // Monday
            'number_format' => $data['number_format'] ?? 'en_NG',
        ];
    }

    /**
     * Get default branding settings.
     * 
     * @return array<string, mixed>
     */
    private function getDefaultBranding(): array
    {
        return [
            'primary_color' => '#1a56db',
            'secondary_color' => '#1c64f2',
            'accent_color' => '#3f83f8',
            'logo_url' => null,
            'favicon_url' => null,
            'email_header_url' => null,
            'email_footer_text' => null,
        ];
    }

    /**
     * Extract first name from full name.
     */
    private function extractFirstName(string $fullName): string
    {
        $parts = explode(' ', $fullName);
        return $parts[0] ?? $fullName;
    }

    /**
     * Extract last name from full name.
     */
    private function extractLastName(string $fullName): ?string
    {
        $parts = explode(' ', $fullName);
        return count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : null;
    }

    // ============================================================================
    // Lifecycle Operations
    // ============================================================================

    /**
     * Suspend a tenant.
     */
    public function suspendTenant(Tenant $tenant, string $reason): void
    {
        $tenant->suspend($reason);

        Log::info('Tenant suspended', [
            'tenant_id' => $tenant->id,
            'reason' => $reason,
        ]);
    }

    /**
     * Reactivate a suspended tenant.
     */
    public function reactivateTenant(Tenant $tenant): void
    {
        $tenant->update([
            'status' => TenantStatus::ACTIVE,
            'suspension_reason' => null,
        ]);

        Log::info('Tenant reactivated', ['tenant_id' => $tenant->id]);
    }

    /**
     * Terminate a tenant (permanent deletion).
     * 
     * @throws \Exception
     */
    public function terminateTenant(Tenant $tenant, bool $deleteData = false): void
    {
        $databaseName = $tenant->database_name;

        if ($deleteData) {
            // Drop tenant database
            DB::connection('mysql')->statement("DROP DATABASE IF EXISTS `{$databaseName}`");
            
            Log::info('Tenant database dropped', [
                'tenant_id' => $tenant->id,
                'database' => $databaseName,
            ]);
        }

        $tenant->update(['status' => TenantStatus::TERMINATED]);

        Log::info('Tenant terminated', [
            'tenant_id' => $tenant->id,
            'data_deleted' => $deleteData,
        ]);
    }

    /**
     * Export tenant data for offboarding.
     * 
     * @return array<string, mixed>
     */
    public function exportTenantData(Tenant $tenant): array
    {
        // This will be expanded with full data export functionality
        return [
            'tenant_info' => $tenant->toArray(),
            'export_date' => now()->toIso8601String(),
            'exported_by' => auth()->id(),
        ];
    }
}
