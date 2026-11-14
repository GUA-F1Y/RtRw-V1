<?php

declare(strict_types=1);

namespace App\Domain\Finance\Queries;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\ScopedQuery;
use App\Domain\Finance\Models\CashAccount;
use App\Domain\Identity\Models\User;
use App\Support\Api\Contracts\QueryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListCashAccountsQuery
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
        $query = CashAccount::query()->with(['rw', 'rt']);

        // 1. Authoritative scope constraint
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

        // 2. Whitelisted filters
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['rw_id'])) {
            $query->where('rw_id', $filters['rw_id']);
        }

        if (array_key_exists('rt_id', $filters)) {
            if ($filters['rt_id'] === null || $filters['rt_id'] === 'null' || $filters['rt_id'] === '') {
                // Explicitly filter for RW-level accounts
                $query->whereNull('rt_id');
            } else {
                $query->where('rt_id', $filters['rt_id']);
            }
        }

        // 3. Search query (?q=)
        if (! empty($filters['q'])) {
            $term = QueryContract::normalizeSearch((string) $filters['q']);
            if ($term !== null) {
                $escaped = QueryContract::escapeLike($term);
                $query->where(function ($q) use ($escaped): void {
                    $q->where('name', 'ILIKE', "%{$escaped}%")
                        ->orWhere('account_number', 'ILIKE', "%{$escaped}%");
                });
            }
        }

        // 4. Sort
        $sortConfig = $sort ?? QueryContract::parseSort(null, QueryContract::FINANCE_ACCOUNT);
        if ($sortConfig !== null) {
            $query->orderBy($sortConfig['field'], $sortConfig['direction']);
        } else {
            $query->orderBy('name', 'asc');
        }

        return $query->paginate($perPage);
    }
}
