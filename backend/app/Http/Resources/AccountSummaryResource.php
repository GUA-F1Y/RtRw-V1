<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Finance\Models\CashAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountSummaryResource extends JsonResource
{
    /**
     * @param  CashAccount  $resource
     * @param  array<string, int>  $balanceSummary
     */
    public function __construct(
        CashAccount $resource,
        private readonly array $balanceSummary,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CashAccount $account */
        $account = $this->resource;

        return array_merge([
            'account' => (new CashAccountResource($account))->withCurrentBalance($this->balanceSummary['current_balance']),
            'summary' => $this->balanceSummary,
            'total_income' => $this->balanceSummary['posted_income'],
            'total_expense' => $this->balanceSummary['posted_expense'],
            'active_income' => $this->balanceSummary['net_income'],
            'active_expense' => $this->balanceSummary['net_expense'],
            'transactions_count' => $this->balanceSummary['total_transactions_count'],
        ], $this->balanceSummary);
    }
}
