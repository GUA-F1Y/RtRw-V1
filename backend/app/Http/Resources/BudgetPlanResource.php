<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Finance\Models\BudgetPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BudgetPlan
 */
class BudgetPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'rw_id' => $this->rw_id,
            'rt_id' => $this->rt_id,
            'scope_type' => $this->rt_id !== null ? 'RT' : 'RW',
            'year' => (int) $this->year,
            'month' => (int) $this->month,
            'version' => (int) $this->version,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'published_by' => $this->published_by,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ]),
            'publisher' => $this->whenLoaded('publisher', fn () => $this->publisher ? [
                'id' => $this->publisher->id,
                'name' => $this->publisher->name,
                'email' => $this->publisher->email,
            ] : null),
            'rw' => $this->whenLoaded('rw', fn () => $this->rw ? [
                'id' => $this->rw->id,
                'rw_number' => $this->rw->number,
                'number' => $this->rw->number,
                'name' => $this->rw->name,
            ] : null),
            'rt' => $this->whenLoaded('rt', fn () => $this->rt ? [
                'id' => $this->rt->id,
                'rt_number' => $this->rt->number,
                'number' => $this->rt->number,
                'name' => $this->rt->name,
            ] : null),
            'lines' => $this->whenLoaded('lines', fn () => BudgetLineResource::collection($this->lines)),
            'summary' => $this->when(isset($this->additional['summary']), fn () => $this->additional['summary']),
        ];
    }
}
