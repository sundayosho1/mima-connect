<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master Database: System Logs Table
 * 
 * Platform-wide logging for monitoring, debugging, and audit purposes.
 * Captures errors, security events, and operational metrics.
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
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            
            // Log Level & Category
            $table->enum('level', [
                'debug',
                'info',
                'notice',
                'warning',
                'error',
                'critical',
                'alert',
                'emergency'
            ])->default('info');
            
            $table->string('category'); // auth, database, payment, security, etc.
            $table->string('subcategory')->nullable();
            
            // Message & Context
            $table->text('message');
            $table->json('context_json')->nullable();
            $table->json('metadata_json')->nullable();
            
            // Tenant Context (nullable for platform-wide events)
            $table->foreignId('tenant_id')->nullable()->constrained('tenants');
            $table->foreignId('user_id')->nullable(); // User in tenant context
            
            // Request Context
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_id')->nullable();
            $table->string('url')->nullable();
            $table->string('http_method')->nullable();
            $table->integer('response_status')->nullable();
            $table->integer('response_time_ms')->nullable();
            
            // Source
            $table->string('source_file')->nullable();
            $table->integer('source_line')->nullable();
            $table->string('source_function')->nullable();
            
            // Exception Details (for errors)
            $table->text('exception_class')->nullable();
            $table->text('exception_trace')->nullable();
            
            // Resolution
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable();
            $table->text('resolution_notes')->nullable();
            
            $table->timestamp('created_at');
            
            // Indexes for efficient querying
            $table->index(['level', 'created_at']);
            $table->index(['category', 'created_at']);
            $table->index(['tenant_id', 'created_at']);
            $table->index('request_id');
            $table->index(['level', 'category', 'created_at']);
            
            // Partitioning consideration for high volume
            // Consider monthly partitions for production
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_logs');
    }
};
