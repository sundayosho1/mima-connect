<?php

declare(strict_types=1);

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant User Model (Master Database)
 * 
 * Links platform users to tenants for cross-tenant access management.
 * Enables super admins to access multiple tenant accounts.
 * 
 * @package App\Models\Master
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property string $role
 * @property \Carbon\Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property int $login_count
 * @property string $invitation_status
 * @property \Carbon\Carbon|null $invited_at
 * @property \Carbon\Carbon|null $invitation_accepted_at
 * @property int|null $invited_by_user_id
 * @property array|null $permissions_override_json
 * @property bool $is_active
 * @property \Carbon\Carbon|null $deactivated_at
 * @property string|null $deactivation_reason
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TenantUser extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'tenant_users';

    /**
     * The connection name for the model.
     */
    protected $connection = 'mysql';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'role',
        'last_login_at',
        'last_login_ip',
        'login_count',
        'invitation_status',
        'invited_at',
        'invitation_accepted_at',
        'invited_by_user_id',
        'permissions_override_json',
        'is_active',
        'deactivated_at',
        'deactivation_reason',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'last_login_at' => 'datetime',
        'invited_at' => 'datetime',
        'invitation_accepted_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'permissions_override_json' => 'array',
        'is_active' => 'boolean',
        'login_count' => 'integer',
    ];

    // ============================================================================
    // Relationships
    // ============================================================================

    /**
     * Get the tenant this user belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    // ============================================================================
    // Role Checks
    // ============================================================================

    /**
     * Check if user is a super admin for this tenant.
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * Check if user is a tenant admin.
     */
    public function isTenantAdmin(): bool
    {
        return in_array($this->role, ['super_admin', 'tenant_admin'], true);
    }

    /**
     * Check if user has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        // Check override permissions first
        $overrides = $this->permissions_override_json ?? [];
        if (isset($overrides[$permission])) {
            return $overrides[$permission];
        }

        // Fall back to role-based permissions
        $rolePermissions = $this->getRolePermissions();
        return in_array($permission, $rolePermissions, true);
    }

    /**
     * Get permissions for the user's role.
     */
    protected function getRolePermissions(): array
    {
        return match ($this->role) {
            'super_admin' => ['*'], // All permissions
            'tenant_admin' => [
                'users.manage',
                'settings.manage',
                'billing.view',
                'reports.view_all',
                'data.export',
            ],
            'user' => [
                'profile.view',
                'profile.edit',
            ],
            default => [],
        };
    }

    // ============================================================================
    // Status Checks
    // ============================================================================

    /**
     * Check if the user can access the tenant.
     */
    public function canAccess(): bool
    {
        return $this->is_active 
            && $this->invitation_status === 'accepted'
            && $this->tenant?->isActive();
    }

    /**
     * Check if invitation is pending.
     */
    public function isInvitationPending(): bool
    {
        return $this->invitation_status === 'pending';
    }

    /**
     * Check if invitation has expired.
     */
    public function isInvitationExpired(): bool
    {
        return $this->invitation_status === 'expired';
    }

    // ============================================================================
    // Actions
    // ============================================================================

    /**
     * Record a login.
     */
    public function recordLogin(string $ipAddress): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
            'login_count' => $this->login_count + 1,
        ]);
    }

    /**
     * Accept invitation.
     */
    public function acceptInvitation(): void
    {
        $this->update([
            'invitation_status' => 'accepted',
            'invitation_accepted_at' => now(),
        ]);
    }

    /**
     * Deactivate the user.
     */
    public function deactivate(string $reason): void
    {
        $this->update([
            'is_active' => false,
            'deactivated_at' => now(),
            'deactivation_reason' => $reason,
        ]);
    }

    /**
     * Reactivate the user.
     */
    public function reactivate(): void
    {
        $this->update([
            'is_active' => true,
            'deactivated_at' => null,
            'deactivation_reason' => null,
        ]);
    }

    // ============================================================================
    // Scopes
    // ============================================================================

    /**
     * Scope to active users.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to users with accepted invitations.
     */
    public function scopeAccepted($query)
    {
        return $query->where('invitation_status', 'accepted');
    }

    /**
     * Scope to users who can access (active + accepted + tenant active).
     */
    public function scopeCanAccess($query)
    {
        return $query
            ->where('is_active', true)
            ->where('invitation_status', 'accepted')
            ->whereHas('tenant', function ($q) {
                $q->where('status', 'active');
            });
    }

    /**
     * Scope by role.
     */
    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope to super admins.
     */
    public function scopeSuperAdmins($query)
    {
        return $query->where('role', 'super_admin');
    }
}
