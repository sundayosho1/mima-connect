<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master Database: Tenant Users Table
 * 
 * Links platform users to tenants for cross-tenant access.
 * Enables super admins to manage multiple tenants.
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
        Schema::create('tenant_users', function (Blueprint $table) {
            $table->id();
            
            // Relationships
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('user_id'); // References users table in tenant database
            
            // Role within this tenant
            $table->enum('role', [
                'super_admin',     // Full tenant access
                'tenant_admin',    // Tenant admin (not platform)
                'user'             // Regular user
            ])->default('user');
            
            // Access Tracking
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->integer('login_count')->default(0);
            
            // Invitation Status
            $table->enum('invitation_status', [
                'pending',
                'accepted',
                'expired',
                'revoked'
            ])->default('accepted');
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('invitation_accepted_at')->nullable();
            $table->foreignId('invited_by_user_id')->nullable();
            
            // Permissions Override (optional, null = use role defaults)
            $table->json('permissions_override_json')->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamp('deactivated_at')->nullable();
            $table->text('deactivation_reason')->nullable();
            
            $table->timestamps();
            
            // Unique constraint - one user per tenant
            $table->unique(['tenant_id', 'user_id']);
            
            // Indexes
            $table->index(['tenant_id', 'role']);
            $table->index(['user_id', 'is_active']);
            $table->index('invitation_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_users');
    }
};
