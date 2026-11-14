<?php

declare(strict_types=1);

namespace App\Domain\Finance\Queries;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\ScopedQuery;
use App\Domain\Finance\Models\ExpenditureRequest;
use App\Domain\Identity\Models\User;
use App\Support\Api\Contracts\QueryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListExpenditureRequestsQuery
{
    public function __construct(
        private readonly ScopedQuery $scopedQuery,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  array{field: string, direction: 'asc'|'desc'}|null  $sort
     * @return LengthAwarePaginator<ExpenditureRequest>
     */
    public function execute(
        User $user,
        array $filters = [],
        ?array $sort = null,
        int $perPage = QueryContract::DEFAULT_PER_PAGE
    ): LengthAwarePaginator {
        $query = ExpenditureRequest::query()->with([
            'budgetLine.category',
            'budgetLine.budgetPlan',
            'cashAccount',
            'category',
            'requester:id,name,email',
            'approver:id,name,email',
            'poster:id,name,email',
            'financialTransaction',
            'evidenceFile',
            'rw:id,number,name',
            'rt:id,number,name',
        ]);

        $query = $this->scopedQuery->constrain(
            $query,
            $user,
            PermissionKey::FinanceExpenditureView,
            [
                'organization' => 'organization_id',
                'rw' => 'rw_id',
                'rt' => 'rt_id',
            ]
        );

        if (! empty($filters['budget_line_id'])) {
            $query->where('budget_line_id', $filters['budget_line_id']);
        }

        if (! empty($filters['cash_account_id'])) {
            $query->where('cash_account_id', $filters['cash_account_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', strtoupper((string) $filters['status']));
        }

        if (! empty($filters['date_from'])) {
            $query->where('planned_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('planned_date', '<=', $filters['date_to']);
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

        if (! empty($filters['q'])) {
            $q = QueryContract::normalizeSearch((string) $filters['q']);
            if ($q !== null) {
                $escaped = QueryContract::escapeLike($q);
                $query->where(function ($sub) use ($escaped) {
                    $sub->where('title', 'ILIKE', "%{$escaped}%")
                        ->orWhere('description', 'ILIKE', "%{$escaped}%")
                        ->orWhere('reference_number', 'ILIKE', "%{$escaped}%");
                });
            }
        }

        if ($sort !== null) {
            $query->orderBy($sort['field'], $sort['direction']);
        } else {
            $query->orderByDesc('created_at');
        }

        return $query->paginate($perPage);
    }
}
