<?php

declare(strict_types=1);

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant Plan Model (Master Database)
 * 
 * Defines subscription tiers available on the MiMaConnect platform.
 * Controls feature access, usage limits, and pricing.
 * 
 * @package App\Models\Master
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property string|null $tagline
 * @property float $price_monthly
 * @property float $price_annually
 * @property string $currency
 * @property float $setup_fee
 * @property int $max_users
 * @property int $max_properties
 * @property int $max_storage_gb
 * @property int $max_api_calls_per_month
 * @property int|null $max_realtors
 * @property int|null $max_branches
 * @property array $modules_json
 * @property array $features_json
 * @property array|null $permissions_json
 * @property int $trial_days
 * @property array|null $trial_features_json
 * @property bool $is_public
 * @property bool $is_active
 * @property bool $is_popular
 * @property int $display_order
 * @property string $support_level
 * @property string|null $sla
 * @property int $backup_retention_days
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TenantPlan extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'tenant_plans';

    /**
     * The connection name for the model.
     */
    protected $connection = 'mysql';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'slug',
        'name',
        'description',
        'tagline',
        'price_monthly',
        'price_annually',
        'currency',
        'setup_fee',
        'max_users',
        'max_properties',
        'max_storage_gb',
        'max_api_calls_per_month',
        'max_realtors',
        'max_branches',
        'modules_json',
        'features_json',
        'permissions_json',
        'trial_days',
        'trial_features_json',
        'is_public',
        'is_active',
        'is_popular',
        'display_order',
        'support_level',
        'sla',
        'backup_retention_days',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'price_monthly' => 'decimal:2',
        'price_annually' => 'decimal:2',
        'setup_fee' => 'decimal:2',
        'modules_json' => 'array',
        'features_json' => 'array',
        'permissions_json' => 'array',
        'trial_features_json' => 'array',
        'is_public' => 'boolean',
        'is_active' => 'boolean',
        'is_popular' => 'boolean',
    ];

    // ============================================================================
    // Relationships
    // ============================================================================

    /**
     * Get the tenants subscribed to this plan.
     */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'plan_id');
    }

    // ============================================================================
    // Accessors
    // ============================================================================

    /**
     * Get the annual savings compared to monthly billing.
     */
    public function getAnnualSavingsAttribute(): float
    {
        $monthlyCost = $this->price_monthly * 12;
        return max(0, $monthlyCost - $this->price_annually);
    }

    /**
     * Get the annual savings percentage.
     */
    public function getAnnualSavingsPercentageAttribute(): float
    {
        $monthlyCost = $this->price_monthly * 12;
        if ($monthlyCost === 0.0) {
            return 0;
        }

        return round(($this->annual_savings / $monthlyCost) * 100, 1);
    }

    /**
     * Get the effective monthly price for annual billing.
     */
    public function getEffectiveMonthlyPriceAttribute(): float
    {
        return round($this->price_annually / 12, 2);
    }

    // ============================================================================
    // Feature & Module Checks
    // ============================================================================

    /**
     * Check if a module is included in this plan.
     */
    public function hasModule(string $module): bool
    {
        $modules = $this->modules_json ?? [];
        return in_array($module, $modules, true);
    }

    /**
     * Check if a feature is included in this plan.
     */
    public function hasFeature(string $feature): bool
    {
        $features = $this->features_json ?? [];
        return $features[$feature] ?? false;
    }

    /**
     * Get a specific limit.
     */
    public function getLimit(string $metric): ?int
    {
        $column = "max_{$metric}";
        return $this->{$column} ?? null;
    }

    /**
     * Check if a metric has a limit.
     */
    public function hasLimit(string $metric): bool
    {
        return $this->getLimit($metric) !== null;
    }

    // ============================================================================
    // Scopes
    // ============================================================================

    /**
     * Scope to active plans.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to public plans (shown on pricing page).
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope to publicly visible active plans.
     */
    public function scopePubliclyVisible($query)
    {
        return $query->where('is_active', true)
            ->where('is_public', true);
    }

    /**
     * Scope ordered by display order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }

    /**
     * Find plan by slug.
     */
    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }

    // ============================================================================
    // Predefined Plans
    // ============================================================================

    /**
     * Get the free plan.
     */
    public static function free(): ?self
    {
        return self::bySlug('free')->first();
    }

    /**
     * Get default plan data for seeding.
     */
    public static function getDefaultPlans(): array
    {
        return [
            [
                'slug' => 'free',
                'name' => 'Free',
                'description' => 'Perfect for getting started with basic property management.',
                'tagline' => 'Get started for free',
                'price_monthly' => 0,
                'price_annually' => 0,
                'setup_fee' => 0,
                'max_users' => 3,
                'max_properties' => 50,
                'max_storage_gb' => 5,
                'max_api_calls_per_month' => 1000,
                'max_realtors' => 5,
                'max_branches' => 1,
                'modules_json' => [
                    'customers',
                    'properties',
                    'payments_basic',
                    'help_desk_basic',
                ],
                'features_json' => [
                    'custom_domain' => false,
                    'api_access' => false,
                    'priority_support' => false,
                    'advanced_reporting' => false,
                    'white_label' => false,
                ],
                'trial_days' => 0,
                'is_public' => true,
                'is_active' => true,
                'is_popular' => false,
                'display_order' => 1,
                'support_level' => 'community',
                'backup_retention_days' => 7,
            ],
            [
                'slug' => 'growth',
                'name' => 'Growth',
                'description' => 'Ideal for growing real estate businesses with expanding portfolios.',
                'tagline' => 'Scale your business',
                'price_monthly' => 50000,
                'price_annually' => 500000, // ~17% discount
                'setup_fee' => 0,
                'max_users' => 10,
                'max_properties' => 200,
                'max_storage_gb' => 20,
                'max_api_calls_per_month' => 10000,
                'max_realtors' => 25,
                'max_branches' => 3,
                'modules_json' => [
                    'customers',
                    'properties',
                    'payments',
                    'crm',
                    'realtors',
                    'documents',
                    'workflows_basic',
                ],
                'features_json' => [
                    'custom_domain' => true,
                    'api_access' => false,
                    'priority_support' => false,
                    'advanced_reporting' => true,
                    'white_label' => false,
                ],
                'trial_days' => 30,
                'is_public' => true,
                'is_active' => true,
                'is_popular' => true,
                'display_order' => 2,
                'support_level' => 'email',
                'sla' => '99.5%',
                'backup_retention_days' => 30,
            ],
            [
                'slug' => 'professional',
                'name' => 'Professional',
                'description' => 'Complete solution for established real estate companies.',
                'tagline' => 'Full-featured solution',
                'price_monthly' => 150000,
                'price_annually' => 1500000, // ~17% discount
                'setup_fee' => 50000,
                'max_users' => 50,
                'max_properties' => PHP_INT_MAX, // Unlimited
                'max_storage_gb' => 100,
                'max_api_calls_per_month' => 100000,
                'max_realtors' => null, // Unlimited
                'max_branches' => 10,
                'modules_json' => [
                    'customers',
                    'properties',
                    'land_banking',
                    'payments',
                    'crm',
                    'realtors',
                    'commissions',
                    'documents',
                    'workflows',
                    'investment_management',
                    'analytics',
                    'construction_management',
                    'lease_management',
                ],
                'features_json' => [
                    'custom_domain' => true,
                    'api_access' => true,
                    'priority_support' => true,
                    'advanced_reporting' => true,
                    'white_label' => false,
                ],
                'trial_days' => 30,
                'is_public' => true,
                'is_active' => true,
                'is_popular' => false,
                'display_order' => 3,
                'support_level' => 'priority',
                'sla' => '99.9%',
                'backup_retention_days' => 90,
            ],
            [
                'slug' => 'enterprise',
                'name' => 'Enterprise',
                'description' => 'Custom solution for large organizations with dedicated support.',
                'tagline' => 'Tailored for you',
                'price_monthly' => 0, // Custom pricing
                'price_annually' => 0,
                'setup_fee' => 0,
                'max_users' => PHP_INT_MAX,
                'max_properties' => PHP_INT_MAX,
                'max_storage_gb' => 500,
                'max_api_calls_per_month' => PHP_INT_MAX,
                'max_realtors' => null,
                'max_branches' => null,
                'modules_json' => [
                    'customers',
                    'properties',
                    'land_banking',
                    'payments',
                    'crm',
                    'realtors',
                    'commissions',
                    'documents',
                    'workflows',
                    'investment_management',
                    'analytics',
                    'construction_management',
                    'lease_management',
                    'hr_payroll',
                    'api_gateway',
                ],
                'features_json' => [
                    'custom_domain' => true,
                    'api_access' => true,
                    'priority_support' => true,
                    'advanced_reporting' => true,
                    'white_label' => true,
                    'dedicated_infrastructure' => true,
                    'custom_integrations' => true,
                ],
                'trial_days' => 60,
                'is_public' => true,
                'is_active' => true,
                'is_popular' => false,
                'display_order' => 4,
                'support_level' => 'dedicated',
                'sla' => '99.99%',
                'backup_retention_days' => 365,
            ],
        ];
    }
}
