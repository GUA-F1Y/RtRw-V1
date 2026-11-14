<?php

declare(strict_types=1);

namespace App\Domain\Household\Queries;

use App\Domain\Household\Models\Household;
use App\Domain\Identity\Models\User;
use App\Support\Api\Contracts\QueryContract;

final class GetHouseholdQuery
{
    /**
     * @param  list<string>  $includeTokens
     */
    public function execute(Household $household, User $user, array $includeTokens = []): Household
    {
        $household->loadCount(['activeMembers as active_members_count']);

        $relationMap = QueryContract::includes(QueryContract::HOUSEHOLD);
        $eagerLoads = [];

        foreach ($includeTokens as $token) {
            if (isset($relationMap[$token])) {
                $eagerLoads[] = $relationMap[$token]['relation'];
            }
        }

        if ($eagerLoads !== []) {
            $household->load($eagerLoads);
        }

        return $household;
    }
}
