<?php

declare(strict_types=1);

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * System Log Model (Master Database)
 * 
 * Platform-wide logging for monitoring, debugging, and audit purposes.
 * Captures errors, security events, and operational metrics.
 * 
 * @package App\Models\Master
 * @property int $id
 * @property string $level
 * @property string $category
 * @property string|null $subcategory
 * @property string $message
 * @property array|null $context_json
 * @property array|null $metadata_json
 * @property int|null $tenant_id
 * @property int|null $user_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $request_id
 * @property string|null $url
 * @property string|null $http_method
 * @property int|null $response_status
 * @property int|null $response_time_ms
 * @property string|null $source_file
 * @property int|null $source_line
 * @property string|null $source_function
 * @property string|null $exception_class
 * @property string|null $exception_trace
 * @property bool $is_resolved
 * @property \Carbon\Carbon|null $resolved_at
 * @property int|null $resolved_by_user_id
 * @property string|null $resolution_notes
 * @property \Carbon\Carbon $created_at
 */
class SystemLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'system_logs';

    /**
     * The connection name for the model.
     */
    protected $connection = 'mysql';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The name of the "created at" column.
     */
    const CREATED_AT = 'created_at';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'level',
        'category',
        'subcategory',
        'message',
        'context_json',
        'metadata_json',
        'tenant_id',
        'user_id',
        'ip_address',
        'user_agent',
        'request_id',
        'url',
        'http_method',
        'response_status',
        'response_time_ms',
        'source_file',
        'source_line',
        'source_function',
        'exception_class',
        'exception_trace',
        'is_resolved',
        'resolved_at',
        'resolved_by_user_id',
        'resolution_notes',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'context_json' => 'array',
        'metadata_json' => 'array',
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
        'response_status' => 'integer',
        'response_time_ms' => 'integer',
        'source_line' => 'integer',
    ];

    // ============================================================================
    // Log Levels
    // ============================================================================

    public const LEVEL_DEBUG = 'debug';
    public const LEVEL_INFO = 'info';
    public const LEVEL_NOTICE = 'notice';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_ERROR = 'error';
    public const LEVEL_CRITICAL = 'critical';
    public const LEVEL_ALERT = 'alert';
    public const LEVEL_EMERGENCY = 'emergency';

    /**
     * Get all log levels.
     */
    public static function getLevels(): array
    {
        return [
            self::LEVEL_DEBUG,
            self::LEVEL_INFO,
            self::LEVEL_NOTICE,
            self::LEVEL_WARNING,
            self::LEVEL_ERROR,
            self::LEVEL_CRITICAL,
            self::LEVEL_ALERT,
            self::LEVEL_EMERGENCY,
        ];
    }

    // ============================================================================
    // Relationships
    // ============================================================================

    /**
     * Get the tenant associated with this log entry.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    // ============================================================================
    // Level Checks
    // ============================================================================

    /**
     * Check if this is a debug log.
     */
    public function isDebug(): bool
    {
        return $this->level === self::LEVEL_DEBUG;
    }

    /**
     * Check if this is an info log.
     */
    public function isInfo(): bool
    {
        return $this->level === self::LEVEL_INFO;
    }

    /**
     * Check if this is a warning log.
     */
    public function isWarning(): bool
    {
        return $this->level === self::LEVEL_WARNING;
    }

    /**
     * Check if this is an error log.
     */
    public function isError(): bool
    {
        return $this->level === self::LEVEL_ERROR;
    }

    /**
     * Check if this is a critical log.
     */
    public function isCritical(): bool
    {
        return in_array($this->level, [
            self::LEVEL_CRITICAL,
            self::LEVEL_ALERT,
            self::LEVEL_EMERGENCY,
        ], true);
    }

    // ============================================================================
    // Actions
    // ============================================================================

    /**
     * Mark log entry as resolved.
     */
    public function resolve(int $userId, ?string $notes = null): void
    {
        $this->update([
            'is_resolved' => true,
            'resolved_at' => now(),
            'resolved_by_user_id' => $userId,
            'resolution_notes' => $notes,
        ]);
    }

    // ============================================================================
    // Scopes
    // ============================================================================

    /**
     * Scope to specific level.
     */
    public function scopeLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope to levels.
     */
    public function scopeLevels($query, array $levels)
    {
        return $query->whereIn('level', $levels);
    }

    /**
     * Scope to errors and above.
     */
    public function scopeErrors($query)
    {
        return $query->whereIn('level', [
            self::LEVEL_ERROR,
            self::LEVEL_CRITICAL,
            self::LEVEL_ALERT,
            self::LEVEL_EMERGENCY,
        ]);
    }

    /**
     * Scope to critical and above.
     */
    public function scopeCritical($query)
    {
        return $query->whereIn('level', [
            self::LEVEL_CRITICAL,
            self::LEVEL_ALERT,
            self::LEVEL_EMERGENCY,
        ]);
    }

    /**
     * Scope by category.
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope by tenant.
     */
    public function scopeForTenant($query, ?int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope unresolved logs.
     */
    public function scopeUnresolved($query)
    {
        return $query->where('is_resolved', false);
    }

    /**
     * Scope resolved logs.
     */
    public function scopeResolved($query)
    {
        return $query->where('is_resolved', true);
    }

    /**
     * Scope recent logs.
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope logs from today.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope by request ID.
     */
    public function scopeByRequest($query, string $requestId)
    {
        return $query->where('request_id', $requestId);
    }

    /**
     * Order by most recent first.
     */
    public function scopeLatestFirst($query)
    {
        return $query->orderByDesc('created_at');
    }

    // ============================================================================
    // Static Helper Methods
    // ============================================================================

    /**
     * Log a debug message.
     */
    public static function debug(string $category, string $message, array $context = []): self
    {
        return self::log(self::LEVEL_DEBUG, $category, $message, $context);
    }

    /**
     * Log an info message.
     */
    public static function info(string $category, string $message, array $context = []): self
    {
        return self::log(self::LEVEL_INFO, $category, $message, $context);
    }

    /**
     * Log a warning message.
     */
    public static function warning(string $category, string $message, array $context = []): self
    {
        return self::log(self::LEVEL_WARNING, $category, $message, $context);
    }

    /**
     * Log an error message.
     */
    public static function error(string $category, string $message, array $context = []): self
    {
        return self::log(self::LEVEL_ERROR, $category, $message, $context);
    }

    /**
     * Log a critical message.
     */
    public static function critical(string $category, string $message, array $context = []): self
    {
        return self::log(self::LEVEL_CRITICAL, $category, $message, $context);
    }

    /**
     * Log an exception.
     */
    public static function exception(\Throwable $exception, string $category = 'exception', array $context = []): self
    {
        $level = $exception instanceof \Error ? self::LEVEL_CRITICAL : self::LEVEL_ERROR;

        return self::log($level, $category, $exception->getMessage(), array_merge($context, [
            'exception_class' => get_class($exception),
            'exception_file' => $exception->getFile(),
            'exception_line' => $exception->getLine(),
            'exception_trace' => $exception->getTraceAsString(),
        ]));
    }

    /**
     * Create a log entry.
     */
    public static function log(string $level, string $category, string $message, array $context = []): self
    {
        $data = [
            'level' => $level,
            'category' => $category,
            'message' => $message,
            'context_json' => $context['context'] ?? null,
            'metadata_json' => $context['metadata'] ?? null,
            'tenant_id' => $context['tenant_id'] ?? null,
            'user_id' => $context['user_id'] ?? null,
            'ip_address' => $context['ip_address'] ?? request()?->ip(),
            'user_agent' => $context['user_agent'] ?? request()?->userAgent(),
            'request_id' => $context['request_id'] ?? request()?->header('X-Request-ID'),
            'url' => $context['url'] ?? request()?->fullUrl(),
            'http_method' => $context['http_method'] ?? request()?->method(),
            'response_status' => $context['response_status'] ?? null,
            'response_time_ms' => $context['response_time_ms'] ?? null,
            'source_file' => $context['source_file'] ?? null,
            'source_line' => $context['source_line'] ?? null,
            'source_function' => $context['source_function'] ?? null,
            'exception_class' => $context['exception_class'] ?? null,
            'exception_trace' => $context['exception_trace'] ?? null,
            'created_at' => now(),
        ];

        return self::create($data);
    }

    /**
     * Purge old logs.
     */
    public static function purge(int $daysToKeep = 90): int
    {
        return self::where('created_at', '<', now()->subDays($daysToKeep))->delete();
    }
}
