<?php

declare(strict_types=1);

namespace App\Domain\Resident\Queries;

use App\Domain\Identity\Models\User;
use App\Domain\Resident\Models\Resident;
use App\Support\Api\Contracts\QueryContract;

final class GetResidentQuery
{
    /**
     * @param  list<string>  $includeTokens
     */
    public function execute(Resident $resident, User $user, array $includeTokens = []): Resident
    {
        $relationMap = QueryContract::includes(QueryContract::RESIDENT);
        $eagerLoads = [];

        foreach ($includeTokens as $token) {
            if (isset($relationMap[$token])) {
                $eagerLoads[] = $relationMap[$token]['relation'];
            }
        }

        if ($eagerLoads !== []) {
            $resident->load($eagerLoads);
        }

        return $resident;
    }
}
