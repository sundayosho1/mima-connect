<?php

declare(strict_types=1);

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant Migration Log Model (Master Database)
 * 
 * Tracks migration execution across all tenant databases.
 * Essential for managing schema changes in multi-tenant architecture.
 * 
 * @package App\Models\Master
 * @property int $id
 * @property int $tenant_id
 * @property string $migration_path
 * @property string|null $migration_batch
 * @property int $migrations_count
 * @property \Carbon\Carbon $started_at
 * @property \Carbon\Carbon|null $completed_at
 * @property int|null $duration_seconds
 * @property string $status
 * @property string|null $output
 * @property string|null $error_message
 * @property array|null $failed_migrations_json
 * @property int|null $initiated_by_user_id
 * @property string $initiated_via
 * @property int $retry_count
 * @property int|null $original_log_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TenantMigrationLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'tenant_migration_logs';

    /**
     * The connection name for the model.
     */
    protected $connection = 'mysql';

    /**
     * Indicates if the model should be timestamped.
     * We use custom timestamps (started_at, completed_at).
     */
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tenant_id',
        'migration_path',
        'migration_batch',
        'migrations_count',
        'started_at',
        'completed_at',
        'duration_seconds',
        'status',
        'output',
        'error_message',
        'failed_migrations_json',
        'initiated_by_user_id',
        'initiated_via',
        'retry_count',
        'original_log_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_migrations_json' => 'array',
        'migrations_count' => 'integer',
        'duration_seconds' => 'integer',
        'retry_count' => 'integer',
    ];

    // ============================================================================
    // Relationships
    // ============================================================================

    /**
     * Get the tenant this log belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Get the original log if this is a retry.
     */
    public function originalLog(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_log_id');
    }

    /**
     * Get retry logs for this log.
     */
    public function retryLogs()
    {
        return $this->hasMany(self::class, 'original_log_id');
    }

    // ============================================================================
    // Status Checks
    // ============================================================================

    /**
     * Check if migration was successful.
     */
    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if migration failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if migration is running.
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Check if migration had partial success.
     */
    public function isPartial(): bool
    {
        return $this->status === 'partial';
    }

    /**
     * Check if this is a retry.
     */
    public function isRetry(): bool
    {
        return $this->original_log_id !== null;
    }

    // ============================================================================
    // Computed Properties
    // ============================================================================

    /**
     * Get the duration in human-readable format.
     */
    public function getDurationFormattedAttribute(): string
    {
        if ($this->duration_seconds === null) {
            return 'N/A';
        }

        if ($this->duration_seconds < 60) {
            return "{$this->duration_seconds}s";
        }

        $minutes = floor($this->duration_seconds / 60);
        $seconds = $this->duration_seconds % 60;

        return "{$minutes}m {$seconds}s";
    }

    /**
     * Get failed migrations count.
     */
    public function getFailedCountAttribute(): int
    {
        return count($this->failed_migrations_json ?? []);
    }

    /**
     * Get success rate percentage.
     */
    public function getSuccessRateAttribute(): float
    {
        if ($this->migrations_count === 0) {
            return 0;
        }

        $successCount = $this->migrations_count - $this->failed_count;
        return round(($successCount / $this->migrations_count) * 100, 1);
    }

    // ============================================================================
    // Actions
    // ============================================================================

    /**
     * Mark migration as started.
     */
    public function markStarted(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark migration as completed successfully.
     */
    public function markSuccess(string $output, int $migrationsCount): void
    {
        $completedAt = now();
        $duration = $this->started_at ? $completedAt->diffInSeconds($this->started_at) : null;

        $this->update([
            'status' => 'success',
            'completed_at' => $completedAt,
            'duration_seconds' => $duration,
            'output' => $output,
            'migrations_count' => $migrationsCount,
        ]);
    }

    /**
     * Mark migration as failed.
     */
    public function markFailed(string $errorMessage, ?string $output = null, ?array $failedMigrations = null): void
    {
        $completedAt = now();
        $duration = $this->started_at ? $completedAt->diffInSeconds($this->started_at) : null;

        $this->update([
            'status' => 'failed',
            'completed_at' => $completedAt,
            'duration_seconds' => $duration,
            'error_message' => $errorMessage,
            'output' => $output,
            'failed_migrations_json' => $failedMigrations,
        ]);
    }

    /**
     * Mark migration as partial success.
     */
    public function markPartial(string $output, int $migrationsCount, array $failedMigrations): void
    {
        $completedAt = now();
        $duration = $this->started_at ? $completedAt->diffInSeconds($this->started_at) : null;

        $this->update([
            'status' => 'partial',
            'completed_at' => $completedAt,
            'duration_seconds' => $duration,
            'output' => $output,
            'migrations_count' => $migrationsCount,
            'failed_migrations_json' => $failedMigrations,
        ]);
    }

    /**
     * Create a retry log.
     */
    public function createRetry(): self
    {
        return self::create([
            'tenant_id' => $this->tenant_id,
            'migration_path' => $this->migration_path,
            'migration_batch' => $this->migration_batch,
            'initiated_by_user_id' => $this->initiated_by_user_id,
            'initiated_via' => 'retry',
            'original_log_id' => $this->id,
            'retry_count' => $this->retry_count + 1,
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    // ============================================================================
    // Scopes
    // ============================================================================

    /**
     * Scope to successful migrations.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope to failed migrations.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to running migrations.
     */
    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    /**
     * Scope by tenant.
     */
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope by batch.
     */
    public function scopeByBatch($query, string $batch)
    {
        return $query->where('migration_batch', $batch);
    }

    /**
     * Scope recent logs.
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Order by most recent first.
     */
    public function scopeLatestFirst($query)
    {
        return $query->orderByDesc('started_at');
    }
}
