<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Master\Tenant;
use App\Models\Master\TenantMigrationLog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tenant Migration Service
 * 
 * Handles database migrations across all tenant databases.
 * Supports batch processing, retry logic, and comprehensive logging.
 * 
 * @package App\Services\Tenant
 */
class TenantMigrationService
{
    /**
     * Default batch size for bulk migrations.
     */
    private const DEFAULT_BATCH_SIZE = 100;

    /**
     * Default sleep time between batches (in seconds).
     */
    private const DEFAULT_SLEEP_SECONDS = 1;

    /**
     * Run migrations for a single tenant.
     * 
     * @throws \Exception
     */
    public function runMigrationsForTenant(
        Tenant $tenant, 
        string $migrationPath = 'database/migrations/tenant',
        ?int $initiatedByUserId = null,
        string $initiatedVia = 'manual'
    ): TenantMigrationLog {
        // Create migration log
        $log = TenantMigrationLog::create([
            'tenant_id' => $tenant->id,
            'migration_path' => $migrationPath,
            'initiated_by_user_id' => $initiatedByUserId,
            'initiated_via' => $initiatedVia,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            // Configure tenant connection
            $this->configureTenantConnection($tenant);

            // Run migrations
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => $migrationPath,
                '--force' => true,
            ]);

            $output = Artisan::output();

            // Parse output to get migration count
            $migrationsCount = $this->parseMigrationCount($output);

            // Mark as successful
            $log->markSuccess($output, $migrationsCount);

            Log::info('Tenant migrations completed', [
                'tenant_id' => $tenant->id,
                'migrations_count' => $migrationsCount,
                'duration' => $log->duration_formatted,
            ]);

        } catch (\Exception $e) {
            $log->markFailed($e->getMessage(), Artisan::output());

            Log::error('Tenant migrations failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $log;
    }

    /**
     * Run migrations across all active tenants.
     * 
     * @return array<string, mixed> Summary of migration results
     */
    public function runMigrationsAcrossAllTenants(
        string $migrationPath = 'database/migrations/tenant',
        int $batchSize = self::DEFAULT_BATCH_SIZE,
        int $sleepSeconds = self::DEFAULT_SLEEP_SECONDS,
        ?int $initiatedByUserId = null
    ): array {
        $batchId = $this->generateBatchId();
        $results = [
            'batch_id' => $batchId,
            'started_at' => now()->toIso8601String(),
            'total_tenants' => 0,
            'successful' => 0,
            'failed' => 0,
            'failed_tenants' => [],
        ];

        Log::info('Starting batch tenant migrations', [
            'batch_id' => $batchId,
            'migration_path' => $migrationPath,
        ]);

        Tenant::active()
            ->chunkById($batchSize, function ($tenants) use (
                $migrationPath, 
                $batchId, 
                $sleepSeconds,
                $initiatedByUserId,
                &$results
            ) {
                foreach ($tenants as $tenant) {
                    $results['total_tenants']++;

                    try {
                        $this->runMigrationsForTenant(
                            $tenant, 
                            $migrationPath,
                            $initiatedByUserId,
                            'deployment'
                        );
                        $results['successful']++;

                    } catch (\Exception $e) {
                        $results['failed']++;
                        $results['failed_tenants'][] = [
                            'tenant_id' => $tenant->id,
                            'company_name' => $tenant->company_name,
                            'error' => $e->getMessage(),
                        ];

                        Log::error('Batch migration failed for tenant', [
                            'tenant_id' => $tenant->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Sleep between batches to prevent database overload
                if ($sleepSeconds > 0) {
                    sleep($sleepSeconds);
                }
            });

        $results['completed_at'] = now()->toIso8601String();

        Log::info('Batch tenant migrations completed', [
            'batch_id' => $batchId,
            'total' => $results['total_tenants'],
            'successful' => $results['successful'],
            'failed' => $results['failed'],
        ]);

        return $results;
    }

    /**
     * Rollback the last migration batch for a tenant.
     * 
     * @throws \Exception
     */
    public function rollbackTenant(Tenant $tenant, ?int $steps = null): void
    {
        $this->configureTenantConnection($tenant);

        $params = [
            '--database' => 'tenant',
            '--force' => true,
        ];

        if ($steps !== null) {
            $params['--step'] = $steps;
        }

        Artisan::call('migrate:rollback', $params);

        Log::info('Tenant migrations rolled back', [
            'tenant_id' => $tenant->id,
            'steps' => $steps ?? 'last batch',
        ]);
    }

    /**
     * Reset all migrations for a tenant (rollback everything).
     * 
     * @throws \Exception
     */
    public function resetTenant(Tenant $tenant): void
    {
        $this->configureTenantConnection($tenant);

        Artisan::call('migrate:reset', [
            '--database' => 'tenant',
            '--force' => true,
        ]);

        Log::info('Tenant migrations reset', ['tenant_id' => $tenant->id]);
    }

    /**
     * Refresh migrations for a tenant (rollback and re-run).
     * 
     * @throws \Exception
     */
    public function refreshTenant(Tenant $tenant, string $migrationPath = 'database/migrations/tenant'): void
    {
        $this->configureTenantConnection($tenant);

        Artisan::call('migrate:refresh', [
            '--database' => 'tenant',
            '--path' => $migrationPath,
            '--force' => true,
        ]);

        Log::info('Tenant migrations refreshed', ['tenant_id' => $tenant->id]);
    }

    /**
     * Get migration status for a tenant.
     * 
     * @return array<string, mixed>
     */
    public function getMigrationStatus(Tenant $tenant): array
    {
        $this->configureTenantConnection($tenant);

        // Get migrations from database
        $migrations = DB::connection('tenant')
            ->table('migrations')
            ->orderBy('batch')
            ->orderBy('migration')
            ->get()
            ->toArray();

        // Get migration files
        $migrationPath = database_path('migrations/tenant');
        $files = [];

        if (is_dir($migrationPath)) {
            $files = glob($migrationPath . '/*.php');
            $files = array_map(function ($file) {
                return basename($file, '.php');
            }, $files);
            sort($files);
        }

        $ranMigrations = array_column($migrations, 'migration');
        $pendingMigrations = array_diff($files, $ranMigrations);

        return [
            'tenant_id' => $tenant->id,
            'ran_migrations' => $ranMigrations,
            'pending_migrations' => array_values($pendingMigrations),
            'total_ran' => count($ranMigrations),
            'total_pending' => count($pendingMigrations),
            'last_batch' => $migrations ? max(array_column($migrations, 'batch')) : 0,
        ];
    }

    /**
     * Retry a failed migration.
     * 
     * @throws \Exception
     */
    public function retryMigration(TenantMigrationLog $failedLog): TenantMigrationLog
    {
        if (!$failedLog->isFailed()) {
            throw new \InvalidArgumentException('Can only retry failed migrations');
        }

        $tenant = $failedLog->tenant;

        // Create retry log
        $retryLog = $failedLog->createRetry();

        try {
            $this->configureTenantConnection($tenant);

            // For failed migrations, we try to run pending migrations again
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => $failedLog->migration_path,
                '--force' => true,
            ]);

            $output = Artisan::output();
            $migrationsCount = $this->parseMigrationCount($output);

            $retryLog->markSuccess($output, $migrationsCount);

            Log::info('Migration retry successful', [
                'tenant_id' => $tenant->id,
                'original_log_id' => $failedLog->id,
            ]);

        } catch (\Exception $e) {
            $retryLog->markFailed($e->getMessage(), Artisan::output());

            Log::error('Migration retry failed', [
                'tenant_id' => $tenant->id,
                'original_log_id' => $failedLog->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $retryLog;
    }

    /**
     * Configure the tenant database connection.
     */
    private function configureTenantConnection(Tenant $tenant): void
    {
        $config = $tenant->getDatabaseConfig();

        Config::set('database.connections.tenant', $config);
        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    /**
     * Parse migration count from Artisan output.
     */
    private function parseMigrationCount(string $output): int
    {
        // Look for patterns like "Migrated: 2024_01_01_000001_create_users_table"
        preg_match_all('/Migrated:\s+\d{4}_\d{2}_\d{2}_\d{6}_/', $output, $matches);
        
        return count($matches[0] ?? []);
    }

    /**
     * Generate a unique batch ID.
     */
    private function generateBatchId(): string
    {
        return now()->format('Ymd_His') . '_' . uniqid();
    }

    /**
     * Get migration statistics.
     * 
     * @return array<string, mixed>
     */
    public function getStatistics(int $days = 30): array
    {
        $logs = TenantMigrationLog::recent($days);

        return [
            'period_days' => $days,
            'total_migrations' => $logs->count(),
            'successful' => $logs->successful()->count(),
            'failed' => $logs->failed()->count(),
            'partial' => $logs->where('status', 'partial')->count(),
            'running' => $logs->running()->count(),
            'success_rate' => $this->calculateSuccessRate($logs),
            'average_duration_seconds' => $logs->whereNotNull('duration_seconds')->avg('duration_seconds'),
            'by_tenant' => $logs->selectRaw('tenant_id, COUNT(*) as count')
                ->groupBy('tenant_id')
                ->pluck('count', 'tenant_id')
                ->toArray(),
        ];
    }

    /**
     * Calculate success rate from logs.
     */
    private function calculateSuccessRate($logs): float
    {
        $total = $logs->count();
        if ($total === 0) {
            return 0;
        }

        $successful = $logs->successful()->count();
        return round(($successful / $total) * 100, 2);
    }
}
