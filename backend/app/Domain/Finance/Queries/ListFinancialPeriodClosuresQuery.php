<?php

declare(strict_types=1);

namespace App\Domain\Finance\Queries;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\ScopedQuery;
use App\Domain\Finance\Models\FinancialPeriodClosure;
use App\Domain\Identity\Models\User;
use App\Support\Api\Contracts\QueryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListFinancialPeriodClosuresQuery
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
        $query = FinancialPeriodClosure::query()
            ->with([
                'account',
                'closedBy:id,name,email',
            ]);

        // 1. Authoritative scope constraint (viewable by users with FinanceAccountView or FinanceTransactionView)
        $query = $this->scopedQuery->constrain(
            $query,
            $user,
            PermissionKey::FinanceAccountView,
            [
                'organization' => 'organization_id',
                'rw' => 'rw_id',
                'rt' => 'rt_id',
            ]
        );

        // 2. Filters
        if (! empty($filters['cash_account_id'])) {
            $query->where('cash_account_id', $filters['cash_account_id']);
        }

        if (! empty($filters['period_year'])) {
            $query->where('period_year', (int) $filters['period_year']);
        }

        if (! empty($filters['period_month'])) {
            $query->where('period_month', (int) $filters['period_month']);
        }

        if (! empty($filters['rw_id'])) {
            $query->where('rw_id', $filters['rw_id']);
        }

        if (array_key_exists('rt_id', $filters)) {
            if ($filters['rt_id'] === null || $filters['rt_id'] === 'null' || $filters['rt_id'] === '') {
                $query->whereNull('rt_id');
            } else {
                $query->where('rt_id', $filters['rt_id']);
            }
        }

        // 3. Sort
        $sortConfig = $sort ?? QueryContract::parseSort(null, QueryContract::FINANCE_PERIOD_CLOSURE);
        if ($sortConfig !== null) {
            $query->orderBy($sortConfig['field'], $sortConfig['direction']);
        } else {
            $query->orderByDesc('period_year')->orderByDesc('period_month');
        }

        return $query->paginate($perPage);
    }
}
