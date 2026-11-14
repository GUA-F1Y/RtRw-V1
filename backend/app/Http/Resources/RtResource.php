<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Organization\Models\Rt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Rt
 */
class RtResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rw_id' => $this->rw_id,
            'number' => $this->number,
            'name' => $this->name,
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
