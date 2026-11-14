<?php

declare(strict_types=1);

namespace App\Domain\Finance\Queries;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\ScopedQuery;
use App\Domain\Finance\Models\BudgetPlan;
use App\Domain\Identity\Models\User;
use App\Support\Api\Contracts\QueryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListBudgetPlansQuery
{
    public function __construct(
        private readonly ScopedQuery $scopedQuery,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  array{field: string, direction: 'asc'|'desc'}|null  $sort
     * @return LengthAwarePaginator<BudgetPlan>
     */
    public function execute(
        User $user,
        array $filters = [],
        ?array $sort = null,
        int $perPage = QueryContract::DEFAULT_PER_PAGE
    ): LengthAwarePaginator {
        $query = BudgetPlan::query()->with([
            'lines.category',
            'creator:id,name,email',
            'publisher:id,name,email',
            'rw:id,number,name',
            'rt:id,number,name',
        ]);

        $query = $this->scopedQuery->constrain(
            $query,
            $user,
            PermissionKey::FinanceBudgetView,
            [
                'organization' => 'organization_id',
                'rw' => 'rw_id',
                'rt' => 'rt_id',
            ]
        );

        if (! empty($filters['year'])) {
            $query->where('year', (int) $filters['year']);
        }

        if (! empty($filters['month'])) {
            $query->where('month', (int) $filters['month']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', strtoupper((string) $filters['status']));
        }

        if (! empty($filters['version'])) {
            $query->where('version', (int) $filters['version']);
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
                $query->where('notes', 'ILIKE', "%{$escaped}%");
            }
        }

        if ($sort !== null) {
            $query->orderBy($sort['field'], $sort['direction']);
        } else {
            $query->orderByDesc('year')->orderByDesc('month')->orderByDesc('version');
        }

        return $query->paginate($perPage);
    }
}
