<?php

declare(strict_types=1);

namespace App\Domain\Finance\Queries;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AccessResolver;
use App\Domain\Authorization\Services\ScopedQuery;
use App\Domain\Finance\Models\FinanceCategory;
use App\Domain\Identity\Models\User;
use App\Support\Api\Contracts\QueryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class ListFinanceCategoriesQuery
{
    public function __construct(
        private readonly ScopedQuery $scopedQuery,
        private readonly AccessResolver $accessResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  array{field: string, direction: 'asc'|'desc'}|null  $sort
     */
    public function execute(
        User $user,
        array $filters = [],
        ?array $sort = null,
        int $perPage = 50
    ): LengthAwarePaginator {
        $query = FinanceCategory::query();

        // Enforce organization boundaries first
        $scopes = $this->accessResolver->scopesGranting($user, PermissionKey::FinanceAccountView);
        $orgIds = array_values(array_unique(array_map(fn ($s) => $s->organizationId, $scopes)));
        $query->whereIn('organization_id', $orgIds);

        // Scope constraint: allows org-wide default categories + scoped categories
        $query = $this->scopedQuery->constrain(
            $query,
            $user,
            PermissionKey::FinanceAccountView,
            [
                'organization' => 'organization_id',
                'rw' => fn (Builder $q, string $rwId) => $q->where(function (Builder $sub) use ($rwId): void {
                    $sub->whereNull('rw_id')->orWhere('rw_id', $rwId);
                }),
                'rt' => fn (Builder $q, string $rtId) => $q->where(function (Builder $sub) use ($rtId): void {
                    $sub->where(function (Builder $s): void {
                        $s->whereNull('rw_id')->whereNull('rt_id');
                    })->orWhere('rt_id', $rtId);
                }),
            ]
        );

        // Filters
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['q'])) {
            $term = QueryContract::normalizeSearch((string) $filters['q']);
            if ($term !== null) {
                $escaped = QueryContract::escapeLike($term);
                $query->where(function ($q) use ($escaped): void {
                    $q->where('name', 'ILIKE', "%{$escaped}%")
                        ->orWhere('code', 'ILIKE', "%{$escaped}%");
                });
            }
        }

        // Sort
        $sortConfig = $sort ?? QueryContract::parseSort(null, QueryContract::FINANCE_CATEGORY);
        if ($sortConfig !== null) {
            $query->orderBy($sortConfig['field'], $sortConfig['direction']);
        } else {
            $query->orderBy('name', 'asc');
        }

        return $query->paginate($perPage);
    }
}
