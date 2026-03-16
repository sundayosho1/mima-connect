<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master Database: Tenants Table
 * 
 * This table stores all tenant (company) records in the master database.
 * Each tenant gets an isolated database for complete data isolation.
 * 
 * @package MiMaConnect\Database\Migrations\Master
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            
            // Unique identifier for the tenant
            $table->uuid('uuid')->unique();
            
            // Company Information
            $table->string('company_name');
            $table->string('company_email')->unique();
            $table->string('company_phone')->nullable();
            $table->string('company_address')->nullable();
            $table->string('company_website')->nullable();
            
            // Domain Configuration
            $table->string('subdomain')->unique();
            $table->string('custom_domain')->nullable()->unique();
            $table->timestamp('domain_verified_at')->nullable();
            
            // Database Credentials (encrypted)
            $table->string('database_name');
            $table->string('database_username');
            $table->text('database_password_encrypted');
            
            // Subscription Plan
            $table->foreignId('plan_id')->constrained('tenant_plans');
            $table->enum('subscription_status', [
                'trialing',
                'active',
                'past_due',
                'cancelled',
                'suspended'
            ])->default('trialing');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->timestamp('subscription_started_at')->nullable();
            
            // Tenant Configuration (JSON for flexibility)
            $table->json('settings_json')->nullable();
            // Example: {"timezone": "Africa/Lagos", "currency": "NGN", "locale": "en_NG"}
            
            $table->json('branding_json')->nullable();
            // Example: {"primary_color": "#1a56db", "logo_url": "...", "favicon_url": "..."}
            
            $table->json('features_enabled_json')->nullable();
            // Example: {"crm": true, "land_banking": false, "advanced_analytics": true}
            
            $table->json('business_rules_json')->nullable();
            // Example: {"approval_workflows": {...}, "numbering_formats": {...}}
            
            // Owner Information
            $table->foreignId('owner_user_id')->nullable();
            $table->string('owner_name');
            $table->string('owner_email');
            $table->string('owner_phone')->nullable();
            
            // Usage Tracking
            $table->integer('current_users_count')->default(0);
            $table->integer('current_properties_count')->default(0);
            $table->decimal('storage_used_gb', 10, 2)->default(0);
            $table->integer('api_calls_this_month')->default(0);
            
            // Status & Timestamps
            $table->enum('status', ['pending', 'active', 'suspended', 'terminated'])->default('pending');
            $table->text('suspension_reason')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for common queries
            $table->index('subdomain');
            $table->index('subscription_status');
            $table->index('status');
            $table->index(['subscription_status', 'trial_ends_at']);
            $table->index('plan_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
