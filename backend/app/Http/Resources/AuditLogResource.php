<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Audit\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'log_name' => $this->log_name,
            'description' => $this->description,
            'event' => $this->event,
            'causer' => $this->causer ? [
                'id' => $this->causer->id,
                'name' => $this->causer->name ?? null,
            ] : null,
            'properties' => $this->properties,
            'organization_id' => $this->organization_id,
            'rw_id' => $this->rw_id,
            'rt_id' => $this->rt_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
