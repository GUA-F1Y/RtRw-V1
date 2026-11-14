<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Organization\Models\Rw;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Rw
 */
class RwResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'number' => $this->number,
            'name' => $this->name,
            'status' => $this->status->value,
            'rts_count' => $this->whenCounted('rts'),
            'rts' => RtResource::collection($this->whenLoaded('rts')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
