<?php

declare(strict_types=1);

namespace App\Domain\Finance\Queries;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\ScopedQuery;
use App\Domain\Finance\Models\CashTransfer;
use App\Domain\Identity\Models\User;
use App\Support\Api\Contracts\QueryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class ListCashTransfersQuery
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
        $query = CashTransfer::query()
            ->with([
                'sourceAccount.rt',
                'destinationAccount.rt',
                'recorder:id,name,email',
                'evidenceFile',
                'reversal:id,original_transfer_id,amount,transfer_date,reversal_reason,created_at',
                'originalTransfer:id,amount,transfer_date,description',
            ]);

        // 1. Authoritative scope constraint
        $query = $this->scopedQuery->constrain(
            $query,
            $user,
            PermissionKey::FinanceTransferView,
            [
                'organization' => 'organization_id',
                'rw' => 'rw_id',
                'rt' => function (Builder $q, string $rtId): void {
                    $q->where(function (Builder $sub) use ($rtId): void {
                        $sub->where('rt_id', $rtId)
                            ->orWhereHas('sourceAccount', fn (Builder $acc) => $acc->where('rt_id', $rtId))
                            ->orWhereHas('destinationAccount', fn (Builder $acc) => $acc->where('rt_id', $rtId));
                    });
                },
            ]
        );

        // 2. Whitelisted filters
        if (! empty($filters['cash_account_id'])) {
            $accountId = $filters['cash_account_id'];
            $query->where(function (Builder $q) use ($accountId): void {
                $q->where('source_cash_account_id', $accountId)
                    ->orWhere('destination_cash_account_id', $accountId);
            });
        }

        if (! empty($filters['source_cash_account_id'])) {
            $query->where('source_cash_account_id', $filters['source_cash_account_id']);
        }

        if (! empty($filters['destination_cash_account_id'])) {
            $query->where('destination_cash_account_id', $filters['destination_cash_account_id']);
        }

        if (isset($filters['is_reversal'])) {
            $query->where('is_reversal', filter_var($filters['is_reversal'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['date_from'])) {
            $query->where('transfer_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('transfer_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['rw_id'])) {
            $query->where('rw_id', $filters['rw_id']);
        }

        if (! empty($filters['rt_id'])) {
            $rtId = $filters['rt_id'];
            $query->where(function (Builder $q) use ($rtId): void {
                $q->where('rt_id', $rtId)
                    ->orWhereHas('sourceAccount', fn (Builder $acc) => $acc->where('rt_id', $rtId))
                    ->orWhereHas('destinationAccount', fn (Builder $acc) => $acc->where('rt_id', $rtId));
            });
        }

        // 3. Search query (?q=)
        if (! empty($filters['q'])) {
            $term = QueryContract::normalizeSearch((string) $filters['q']);
            if ($term !== null) {
                $escaped = QueryContract::escapeLike($term);
                $query->where(function (Builder $q) use ($escaped): void {
                    $q->where('description', 'ILIKE', "%{$escaped}%")
                        ->orWhere('reference_number', 'ILIKE', "%{$escaped}%");
                });
            }
        }

        // 4. Sort
        $sortConfig = $sort ?? QueryContract::parseSort(null, QueryContract::FINANCE_TRANSFER);
        if ($sortConfig !== null) {
            $query->orderBy($sortConfig['field'], $sortConfig['direction']);
        }
        $query->orderBy('created_at', 'desc');

        return $query->paginate(max(1, min($perPage, QueryContract::MAX_PER_PAGE)));
    }
}
