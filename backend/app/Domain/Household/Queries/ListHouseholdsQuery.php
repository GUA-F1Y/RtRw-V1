<?php

declare(strict_types=1);

namespace App\Domain\Household\Queries;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\ScopedQuery;
use App\Domain\Household\Models\Household;
use App\Domain\Identity\Models\User;
use App\Support\Api\Contracts\QueryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListHouseholdsQuery
{
    public function __construct(
        private readonly ScopedQuery $scopedQuery
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  array{field: string, direction: 'asc'|'desc'}|null  $sort
     * @param  list<string>  $includeTokens
     */
    public function execute(
        User $user,
        array $filters = [],
        ?array $sort = null,
        array $includeTokens = [],
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = Household::query()
            ->withCount(['activeMembers as active_members_count']);

        // 1. Authoritative scope constraint
        $query = $this->scopedQuery->constrain(
            $query,
            $user,
            PermissionKey::HouseholdView,
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
        if (! empty($filters['rt_id'])) {
            $query->where('rt_id', $filters['rt_id']);
        }
        if (! empty($filters['rw_id'])) {
            $query->where('rw_id', $filters['rw_id']);
        }
        if (! empty($filters['head_availability'])) {
            if ($filters['head_availability'] === 'present') {
                $query->whereHas('activeHead');
            } elseif ($filters['head_availability'] === 'vacant') {
                $query->whereDoesntHave('activeHead');
            }
        }

        // 3. Search query (?q=)
        if (! empty($filters['q'])) {
            $term = QueryContract::normalizeSearch((string) $filters['q']);
            if ($term !== null) {
                $escaped = QueryContract::escapeLike($term);
                $query->where('address', 'ILIKE', "%{$escaped}%");
            }
        }

        // 4. Whitelisted sort
        $sortConfig = $sort ?? QueryContract::parseSort(null, QueryContract::HOUSEHOLD);
        if ($sortConfig !== null) {
            $query->orderBy($sortConfig['field'], $sortConfig['direction']);
        }

        // 5. Eager load allowed includes
        $relationMap = QueryContract::includes(QueryContract::HOUSEHOLD);
        $eagerLoads = [];
        foreach ($includeTokens as $token) {
            if (isset($relationMap[$token])) {
                $eagerLoads[] = $relationMap[$token]['relation'];
            }
        }
        if ($eagerLoads !== []) {
            $query->with($eagerLoads);
        }

        return $query->paginate(perPage: $perPage);
    }
}
