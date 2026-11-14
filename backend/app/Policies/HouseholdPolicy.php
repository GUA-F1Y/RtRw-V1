<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Enums\PrivacyTier;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Authorization\Services\AccessResolver;
use App\Domain\Household\Models\Household;
use App\Domain\Identity\Models\User;
use App\Domain\Resident\Models\Resident;

final class HouseholdPolicy
{
    public function __construct(private readonly AccessResolver $access) {}

    /**
     * Determine whether the user can view households in any scope.
     */
    public function viewAny(User $user): bool
    {
        return $this->access->scopesGranting($user, PermissionKey::HouseholdView) !== [];
    }

    /**
     * Determine whether the user can view household details.
     */
    public function view(User $user, Household $household): bool
    {
        return $this->access->allows($user, PermissionKey::HouseholdView, $household->scopeRef());
    }

    /**
     * Determine whether the user can see the full (unmasked) KK number of a household.
     */
    public function viewSensitive(User $user, Household $household): bool
    {
        return $this->access->allows($user, PermissionKey::HouseholdViewSensitive, $household->scopeRef());
    }

    /**
     * Map the caller's authority over a household to the API representation tier.
     *
     * Normal (household.view) masks the KK number; Sensitive additionally requires
     * household.view_sensitive. There is no Directory-level household access: callers
     * without household.view get null and must not receive the household at all.
     *
     * @return PrivacyTier|null null = no access (caller must respond 403/404)
     */
    public function representationTier(User $user, Household $household): ?PrivacyTier
    {
        if (! $this->view($user, $household)) {
            return null;
        }

        return $this->viewSensitive($user, $household) ? PrivacyTier::Sensitive : PrivacyTier::Normal;
    }

    /**
     * Determine whether the user can create a household in a scope.
     */
    public function create(User $user, ScopeRef|Household|null $target = null): bool
    {
        if ($target === null) {
            return false;
        }

        $scope = $target instanceof Household ? $target->scopeRef() : $target;

        return $this->access->allows($user, PermissionKey::HouseholdCreate, $scope);
    }

    /**
     * Determine whether the user can update household basic information.
     */
    public function update(User $user, Household $household): bool
    {
        return $this->access->allows($user, PermissionKey::HouseholdUpdate, $household->scopeRef());
    }

    /**
     * Determine whether the user can add a member to the household.
     */
    public function addMember(User $user, Household $household, ?Resident $resident = null): bool
    {
        if (! $this->access->allows($user, PermissionKey::HouseholdAddMember, $household->scopeRef())) {
            return false;
        }

        if ($resident !== null && ! $this->access->allows($user, PermissionKey::HouseholdAddMember, $resident->scopeRef())) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can remove a member from the household.
     */
    public function removeMember(User $user, Household $household, ?Resident $resident = null): bool
    {
        if (! $this->access->allows($user, PermissionKey::HouseholdRemoveMember, $household->scopeRef())) {
            return false;
        }

        if ($resident !== null && ! $this->access->allows($user, PermissionKey::HouseholdRemoveMember, $resident->scopeRef())) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can change or assign the head of household.
     */
    public function changeHead(User $user, Household $household): bool
    {
        return $this->access->allows($user, PermissionKey::HouseholdChangeHead, $household->scopeRef());
    }

    /**
     * Determine whether the user can move the household (administrative transfer).
     *
     * If an internal target scope is specified (e.g. relocating across RTs),
     * the actor must also have authority over the target scope.
     */
    public function move(User $user, Household $household, ?ScopeRef $targetScope = null): bool
    {
        if (! $this->access->allows($user, PermissionKey::HouseholdMove, $household->scopeRef())) {
            return false;
        }

        if ($targetScope !== null && ! $this->access->allows($user, PermissionKey::HouseholdMove, $targetScope)) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can dissolve a household.
     */
    public function dissolve(User $user, Household $household): bool
    {
        return $this->access->allows($user, PermissionKey::HouseholdDissolve, $household->scopeRef());
    }
}
