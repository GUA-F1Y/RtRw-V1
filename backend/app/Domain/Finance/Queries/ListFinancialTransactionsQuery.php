<?php

declare(strict_types=1);

namespace App\Domain\Finance\Queries;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\ScopedQuery;
use App\Domain\Finance\Models\FinancialTransaction;
use App\Domain\Identity\Models\User;
use App\Support\Api\Contracts\QueryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListFinancialTransactionsQuery
{
    public function __construct(
        private readonly ScopedQuery $scopedQuery,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  array{field: string, direction: 'asc'|'desc'}|null  $sort
     */
    public function execute(
        User $user,
        array $filters = [],
        ?array $sort = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = FinancialTransaction::query()
            ->with([
                'account',
                'category',
                'recorder:id,name,email',
                'evidenceFile',
                'reversal:id,original_transaction_id,amount,transaction_date,reversal_reason,created_at',
                'originalTransaction:id,type,amount,transaction_date,description',
            ]);

        // 1. Authoritative scope constraint
        $query = $this->scopedQuery->constrain(
            $query,
            $user,
            PermissionKey::FinanceTransactionView,
            [
                'organization' => 'organization_id',
                'rw' => 'rw_id',
                'rt' => 'rt_id',
            ]
        );

        // 2. Whitelisted filters
        if (! empty($filters['cash_account_id'])) {
            $query->where('cash_account_id', $filters['cash_account_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_reversal'])) {
            $query->where('is_reversal', filter_var($filters['is_reversal'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['date_from'])) {
            $query->where('transaction_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('transaction_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['rw_id'])) {
            $query->where('rw_id', $filters['rw_id']);
        }

        if (! empty($filters['rt_id'])) {
            $query->where('rt_id', $filters['rt_id']);
        }

        // 3. Search query (?q=)
        if (! empty($filters['q'])) {
            $term = QueryContract::normalizeSearch((string) $filters['q']);
            if ($term !== null) {
                $escaped = QueryContract::escapeLike($term);
                $query->where(function ($q) use ($escaped): void {
                    $q->where('description', 'ILIKE', "%{$escaped}%")
                        ->orWhere('reference_number', 'ILIKE', "%{$escaped}%");
                });
            }
        }

        // 4. Sort
        $sortConfig = $sort ?? QueryContract::parseSort(null, QueryContract::FINANCE_TRANSACTION);
        if ($sortConfig !== null) {
            $query->orderBy($sortConfig['field'], $sortConfig['direction']);
        } else {
            $query->orderBy('transaction_date', 'desc')
                ->orderBy('created_at', 'desc');
        }

        return $query->paginate($perPage);
    }
}
