<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Authorization\Services\AccessResolver;
use App\Domain\Identity\Models\User;

final class OverviewPolicy
{
    public function __construct(private readonly AccessResolver $accessResolver) {}

    public function view(User $user, ScopeRef $scope): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        $canViewResidents = $this->accessResolver->allows($user, PermissionKey::ResidentDirectoryView, $scope)
            || $this->accessResolver->allows($user, PermissionKey::ResidentView, $scope);

        $canViewHouseholds = $this->accessResolver->allows($user, PermissionKey::HouseholdView, $scope);

        return $canViewResidents && $canViewHouseholds;
    }
}
