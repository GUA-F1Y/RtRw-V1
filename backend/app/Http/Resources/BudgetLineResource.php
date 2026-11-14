<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Finance\Models\BudgetLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BudgetLine
 */
class BudgetLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'budget_plan_id' => $this->budget_plan_id,
            'category_id' => $this->category_id,
            'planned_amount' => (int) $this->planned_amount,
            'notes' => $this->notes,
            'created_at' => $this->created_at->toIso8601String(),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'code' => $this->category->code,
                'type' => $this->category->type->value,
                'is_active' => (bool) $this->category->is_active,
            ]),
            'metrics' => $this->when(isset($this->additional['metrics']), fn () => $this->additional['metrics']),
        ];
    }
}
