<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master Database: Tenant Migration Logs Table
 * 
 * Tracks migration execution across all tenant databases.
 * Essential for managing schema changes in multi-tenant architecture.
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
        Schema::create('tenant_migration_logs', function (Blueprint $table) {
            $table->id();
            
            // Tenant Reference
            $table->foreignId('tenant_id')->constrained('tenants');
            
            // Migration Details
            $table->string('migration_path');
            $table->string('migration_batch')->nullable();
            $table->integer('migrations_count')->default(0);
            
            // Execution Details
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            
            // Status
            $table->enum('status', ['running', 'success', 'failed', 'partial'])
                ->default('running');
            
            // Output & Error Tracking
            $table->longText('output')->nullable();
            $table->text('error_message')->nullable();
            $table->json('failed_migrations_json')->nullable();
            // Example: [{"migration": "2024_01_01_000001_create_users", "error": "..."}]
            
            // Initiated By
            $table->foreignId('initiated_by_user_id')->nullable();
            $table->enum('initiated_via', ['manual', 'deployment', 'scheduled', 'api'])
                ->default('manual');
            
            // Retry Information
            $table->integer('retry_count')->default(0);
            $table->foreignId('original_log_id')->nullable(); // For retry chains
            
            $table->timestamps();
            
            // Indexes
            $table->index(['tenant_id', 'run_at']);
            $table->index(['status', 'created_at']);
            $table->index('migration_batch');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_migration_logs');
    }
};
