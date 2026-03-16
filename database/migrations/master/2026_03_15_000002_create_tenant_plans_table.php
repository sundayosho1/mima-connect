<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master Database: Tenant Plans Table
 * 
 * Subscription plans available on the MiMaConnect platform.
 * Defines feature access, usage limits, and pricing for each tier.
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
        Schema::create('tenant_plans', function (Blueprint $table) {
            $table->id();
            
            // Plan Identification
            $table->string('slug')->unique(); // free, growth, professional, enterprise
            $table->string('name'); // Display name
            $table->text('description')->nullable();
            $table->string('tagline')->nullable(); // e.g., "Perfect for startups"
            
            // Pricing
            $table->decimal('price_monthly', 12, 2)->default(0);
            $table->decimal('price_annually', 12, 2)->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->decimal('setup_fee', 12, 2)->default(0);
            
            // Usage Limits
            $table->integer('max_users');
            $table->integer('max_properties');
            $table->integer('max_storage_gb');
            $table->integer('max_api_calls_per_month');
            $table->integer('max_realtors')->nullable(); // null = unlimited
            $table->integer('max_branches')->nullable();
            
            // Feature Access (JSON for flexibility)
            $table->json('modules_json');
            // Example: ["customers", "properties", "payments", "crm", "realtors"]
            
            $table->json('features_json');
            // Example: {"custom_domain": true, "api_access": false, "priority_support": true}
            
            $table->json('permissions_json')->nullable();
            // Fine-grained feature permissions
            
            // Trial Configuration
            $table->integer('trial_days')->default(30);
            $table->json('trial_features_json')->nullable();
            // Features available during trial
            
            // Plan Settings
            $table->boolean('is_public')->default(true); // Show on pricing page
            $table->boolean('is_active')->default(true);
            $table->boolean('is_popular')->default(false); // Highlight on pricing page
            $table->integer('display_order')->default(0);
            
            // Support Level
            $table->enum('support_level', ['community', 'email', 'priority', 'dedicated'])
                ->default('community');
            $table->string('sla')->nullable(); // e.g., "99.5%", "99.9%"
            
            // Backup & Retention
            $table->integer('backup_retention_days')->default(7);
            
            $table->timestamps();
            
            // Indexes
            $table->index('slug');
            $table->index('is_active');
            $table->index(['is_public', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_plans');
    }
};
