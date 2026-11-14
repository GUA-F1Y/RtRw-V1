<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Organization\Models\Membership;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Membership
 */
class MembershipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scope_level' => $this->scope_level->value,
            'role' => [
                'id' => $this->role?->id,
                'key' => $this->role?->key,
                'name' => $this->role?->name,
            ],
            'organization_id' => $this->organization_id,
            'organization' => $this->whenLoaded('organization', fn () => [
                'id' => $this->organization?->id,
                'name' => $this->organization?->name,
            ]),
            'rw_id' => $this->rw_id,
            'rw' => $this->whenLoaded('rw', fn () => $this->rw ? [
                'id' => $this->rw->id,
                'number' => $this->rw->number,
                'name' => $this->rw->name,
            ] : null),
            'rt_id' => $this->rt_id,
            'rt' => $this->whenLoaded('rt', fn () => $this->rt ? [
                'id' => $this->rt->id,
                'number' => $this->rt->number,
                'name' => $this->rt->name,
            ] : null),
            'is_active' => $this->isActive(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
