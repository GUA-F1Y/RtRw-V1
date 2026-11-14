<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Finance\Models\CashAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CashAccount
 */
class CashAccountResource extends JsonResource
{
    private ?int $currentBalance = null;

    public function withCurrentBalance(?int $balance): self
    {
        $this->currentBalance = $balance;

        return $this;
    }

    public static function withBalance(CashAccount $account, int $balance): self
    {
        $resource = new self($account);
        $resource->withCurrentBalance($balance);

        return $resource;
    }

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
            'is_rt_account' => $this->isRtAccount(),
            'name' => $this->name,
            'account_number' => $this->account_number,
            'description' => $this->description,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'opening_balance' => (int) $this->opening_balance,
            'current_balance' => $this->currentBalance,
            'closed_at' => $this->closed_at?->toIso8601String(),
            'closed_reason' => $this->closed_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'rw' => $this->whenLoaded('rw', fn () => [
                'id' => $this->rw->id,
                'rw_number' => $this->rw->rw_number,
            ]),
            'rt' => $this->whenLoaded('rt', fn () => $this->rt ? [
                'id' => $this->rt->id,
                'rt_number' => $this->rt->rt_number,
            ] : null),
        ];
    }
}
