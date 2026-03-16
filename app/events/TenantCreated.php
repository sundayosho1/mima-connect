<?php

declare(strict_types=1);

namespace App\Events\Tenant;

use App\Models\Master\Tenant;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Tenant Created Event
 * 
 * Fired when a new tenant is successfully provisioned.
 * 
 * @package App\Events\Tenant
 */
class TenantCreated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly Tenant $tenant
    ) {
    }

    /**
     * Get the tenant.
     */
    public function getTenant(): Tenant
    {
        return $this->tenant;
    }

    /**
     * Get event data for broadcasting.
     * 
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'tenant_id' => $this->tenant->id,
            'tenant_uuid' => $this->tenant->uuid,
            'company_name' => $this->tenant->company_name,
            'subdomain' => $this->tenant->subdomain,
            'created_at' => $this->tenant->created_at->toIso8601String(),
        ];
    }
}
