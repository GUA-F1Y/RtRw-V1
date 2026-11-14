<?php

declare(strict_types=1);

namespace App\Domain\Resident\Queries;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AccessResolver;
use App\Domain\Authorization\Services\ScopedQuery;
use App\Domain\Identity\Models\User;
use App\Domain\Resident\Models\Resident;
use App\Support\Api\Contracts\QueryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListResidentsQuery
{
    public function __construct(
        private readonly ScopedQuery $scopedQuery,
        private readonly AccessResolver $access
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
        $query = Resident::query();

        // 1. Determine list permission: use ResidentView if held in any scope, otherwise fallback to ResidentDirectoryView
        $permission = $this->access->scopesGranting($user, PermissionKey::ResidentView) !== []
            ? PermissionKey::ResidentView
            : PermissionKey::ResidentDirectoryView;

        // 2. Authoritative scope constraint
        $query = $this->scopedQuery->constrain(
            $query,
            $user,
            $permission,
            [
                'organization' => 'organization_id',
                'rw' => 'rw_id',
                'rt' => 'rt_id',
            ]
        );

        // 3. Whitelisted filters
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['rt_id'])) {
            $query->where('rt_id', $filters['rt_id']);
        }
        if (! empty($filters['rw_id'])) {
            $query->where('rw_id', $filters['rw_id']);
        }
        if (! empty($filters['household_id'])) {
            $query->where('current_household_id', $filters['household_id']);
        }
        if (! empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }
        if (! empty($filters['residency_type'])) {
            $query->where('residency_type', $filters['residency_type']);
        }
        if (! empty($filters['marital_status'])) {
            $query->where('marital_status', $filters['marital_status']);
        }

        // 4. Search query (?q=)
        if (! empty($filters['q'])) {
            $term = QueryContract::normalizeSearch((string) $filters['q']);
            if ($term !== null) {
                $escaped = QueryContract::escapeLike($term);
                $query->where('name', 'ILIKE', "%{$escaped}%");
            }
        }

        // 5. Whitelisted sort
        $sortConfig = $sort ?? QueryContract::parseSort(null, QueryContract::RESIDENT);
        if ($sortConfig !== null) {
            $query->orderBy($sortConfig['field'], $sortConfig['direction']);
        }

        // 6. Eager load allowed includes
        $relationMap = QueryContract::includes(QueryContract::RESIDENT);
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
