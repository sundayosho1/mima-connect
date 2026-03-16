<?php

declare(strict_types=1);

namespace App\Models\Master;

use App\Contracts\Tenant\TenantInterface;
use App\Enums\Tenant\SubscriptionStatus;
use App\Enums\Tenant\TenantStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

/**
 * Tenant Model (Master Database)
 * 
 * Represents a company/organization using the MiMaConnect platform.
 * Each tenant has complete data isolation through a dedicated database.
 * 
 * @package App\Models\Master
 * @property int $id
 * @property string $uuid
 * @property string $company_name
 * @property string $company_email
 * @property string $subdomain
 * @property string|null $custom_domain
 * @property string $database_name
 * @property int $plan_id
 * @property SubscriptionStatus $subscription_status
 * @property TenantStatus $status
 * @property array $settings_json
 * @property array $branding_json
 * @property array $features_enabled_json
 * @property \Carbon\Carbon|null $trial_ends_at
 * @property \Carbon\Carbon|null $subscription_ends_at
 * @property \Carbon\Carbon|null $activated_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Tenant extends Model implements TenantInterface
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'tenants';

    /**
     * The connection name for the model.
     */
    protected $connection = 'mysql';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uuid',
        'company_name',
        'company_email',
        'company_phone',
        'company_address',
        'company_website',
        'subdomain',
        'custom_domain',
        'domain_verified_at',
        'database_name',
        'database_username',
        'database_password_encrypted',
        'plan_id',
        'subscription_status',
        'trial_ends_at',
        'subscription_ends_at',
        'subscription_started_at',
        'settings_json',
        'branding_json',
        'features_enabled_json',
        'business_rules_json',
        'owner_user_id',
        'owner_name',
        'owner_email',
        'owner_phone',
        'status',
        'suspension_reason',
        'activated_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'domain_verified_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
        'subscription_started_at' => 'datetime',
        'activated_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'settings_json' => 'array',
        'branding_json' => 'array',
        'features_enabled_json' => 'array',
        'business_rules_json' => 'array',
        'storage_used_gb' => 'decimal:2',
        'subscription_status' => SubscriptionStatus::class,
        'status' => TenantStatus::class,
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'database_password_encrypted',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $tenant) {
            if (empty($tenant->uuid)) {
                $tenant->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    // ============================================================================
    // Relationships
    // ============================================================================

    /**
     * Get the plan associated with this tenant.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(TenantPlan::class, 'plan_id');
    }

    /**
     * Get the tenant users for cross-tenant access.
     */
    public function tenantUsers(): HasMany
    {
        return $this->hasMany(TenantUser::class, 'tenant_id');
    }

    /**
     * Get the migration logs for this tenant.
     */
    public function migrationLogs(): HasMany
    {
        return $this->hasMany(TenantMigrationLog::class, 'tenant_id');
    }

    // ============================================================================
    // Accessors & Mutators
    // ============================================================================

    /**
     * Get the decrypted database password.
     */
    public function getDatabasePassword(): string
    {
        return Crypt::decryptString($this->database_password_encrypted);
    }

    /**
     * Set the encrypted database password.
     */
    public function setDatabasePassword(string $password): void
    {
        $this->database_password_encrypted = Crypt::encryptString($password);
    }

    /**
     * Get the tenant's domain (custom or subdomain).
     */
    public function getDomain(): string
    {
        return $this->custom_domain ?? "{$this->subdomain}.mimaconnect.com";
    }

    /**
     * Get the full database connection configuration.
     */
    public function getDatabaseConfig(): array
    {
        return [
            'driver' => 'mysql',
            'host' => config('database.connections.mysql.host'),
            'port' => config('database.connections.mysql.port'),
            'database' => $this->database_name,
            'username' => $this->database_username,
            'password' => $this->getDatabasePassword(),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ];
    }

    // ============================================================================
    // Feature & Limit Checks
    // ============================================================================

    /**
     * Check if a feature is enabled for this tenant.
     */
    public function hasFeature(string $feature): bool
    {
        $features = $this->features_enabled_json ?? [];
        return $features[$feature] ?? false;
    }

    /**
     * Check if a module is accessible for this tenant.
     */
    public function hasModuleAccess(string $module): bool
    {
        $modules = $this->plan?->modules_json ?? [];
        return in_array($module, $modules, true);
    }

    /**
     * Check if the tenant is within plan limits.
     */
    public function isWithinLimit(string $metric, int $additional = 0): bool
    {
        $limit = $this->plan?->{"max_{$metric}"} ?? PHP_INT_MAX;
        $current = match ($metric) {
            'users' => $this->current_users_count,
            'properties' => $this->current_properties_count,
            'storage_gb' => $this->storage_used_gb,
            default => 0,
        };

        return ($current + $additional) <= $limit;
    }

    /**
     * Get the usage percentage for a metric.
     */
    public function getUsagePercentage(string $metric): float
    {
        $limit = $this->plan?->{"max_{$metric}"} ?? 0;
        if ($limit === 0 || $limit === null) {
            return 0;
        }

        $current = match ($metric) {
            'users' => $this->current_users_count,
            'properties' => $this->current_properties_count,
            'storage_gb' => $this->storage_used_gb,
            default => 0,
        };

        return min(100, ($current / $limit) * 100);
    }

    // ============================================================================
    // Status Checks
    // ============================================================================

    /**
     * Check if the tenant is active.
     */
    public function isActive(): bool
    {
        return $this->status === TenantStatus::ACTIVE 
            && $this->subscription_status->isActive();
    }

    /**
     * Check if the tenant is in trial period.
     */
    public function isTrialing(): bool
    {
        return $this->subscription_status === SubscriptionStatus::TRIALING
            && $this->trial_ends_at?->isFuture();
    }

    /**
     * Check if the trial has expired.
     */
    public function hasTrialExpired(): bool
    {
        return $this->subscription_status === SubscriptionStatus::TRIALING
            && $this->trial_ends_at?->isPast();
    }

    /**
     * Check if the subscription is past due.
     */
    public function isPastDue(): bool
    {
        return $this->subscription_status === SubscriptionStatus::PAST_DUE;
    }

    /**
     * Get days remaining in trial.
     */
    public function getTrialDaysRemaining(): int
    {
        if (!$this->isTrialing()) {
            return 0;
        }

        return max(0, now()->diffInDays($this->trial_ends_at, false));
    }

    // ============================================================================
    // Scopes
    // ============================================================================

    /**
     * Scope to active tenants.
     */
    public function scopeActive($query)
    {
        return $query->where('status', TenantStatus::ACTIVE);
    }

    /**
     * Scope to tenants with active subscriptions.
     */
    public function scopeWithActiveSubscription($query)
    {
        return $query->whereIn('subscription_status', [
            SubscriptionStatus::ACTIVE,
            SubscriptionStatus::TRIALING,
        ]);
    }

    /**
     * Scope to find by subdomain.
     */
    public function scopeBySubdomain($query, string $subdomain)
    {
        return $query->where('subdomain', $subdomain);
    }

    /**
     * Scope to find by custom domain.
     */
    public function scopeByCustomDomain($query, string $domain)
    {
        return $query->where('custom_domain', $domain);
    }

    /**
     * Scope to tenants with expired trials.
     */
    public function scopeExpiredTrials($query)
    {
        return $query
            ->where('subscription_status', SubscriptionStatus::TRIALING)
            ->where('trial_ends_at', '<', now());
    }

    /**
     * Scope to tenants nearing plan limits.
     */
    public function scopeNearingLimits($query, float $threshold = 80)
    {
        // This requires a more complex query or can be handled in application logic
        return $query->whereHas('plan', function ($q) use ($threshold) {
            // Implementation depends on specific limit checks needed
        });
    }

    // ============================================================================
    // Actions
    // ============================================================================

    /**
     * Activate the tenant.
     */
    public function activate(): void
    {
        $this->update([
            'status' => TenantStatus::ACTIVE,
            'activated_at' => now(),
            'subscription_status' => $this->trial_ends_at?->isFuture() 
                ? SubscriptionStatus::TRIALING 
                : SubscriptionStatus::ACTIVE,
        ]);
    }

    /**
     * Suspend the tenant.
     */
    public function suspend(string $reason): void
    {
        $this->update([
            'status' => TenantStatus::SUSPENDED,
            'suspension_reason' => $reason,
        ]);
    }

    /**
     * Update last activity timestamp.
     */
    public function touchActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }

    /**
     * Increment usage counter.
     */
    public function incrementUsage(string $metric, int $amount = 1): void
    {
        $column = match ($metric) {
            'users' => 'current_users_count',
            'properties' => 'current_properties_count',
            'api_calls' => 'api_calls_this_month',
            default => null,
        };

        if ($column) {
            $this->increment($column, $amount);
        }
    }

    /**
     * Decrement usage counter.
     */
    public function decrementUsage(string $metric, int $amount = 1): void
    {
        $column = match ($metric) {
            'users' => 'current_users_count',
            'properties' => 'current_properties_count',
            default => null,
        };

        if ($column) {
            $this->decrement($column, $amount);
        }
    }
}
