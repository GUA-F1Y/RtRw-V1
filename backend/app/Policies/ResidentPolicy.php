<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Enums\PrivacyTier;
use App\Domain\Authorization\ScopeRef;
use App\Domain\Authorization\Services\AccessResolver;
use App\Domain\Identity\Models\User;
use App\Domain\Resident\Models\Resident;

final class ResidentPolicy
{
    public function __construct(private readonly AccessResolver $access) {}

    /**
     * Determine whether the user can view the resident directory or list in any scope.
     */
    public function viewAny(User $user): bool
    {
        return $this->access->scopesGranting($user, PermissionKey::ResidentView) !== []
            || $this->access->scopesGranting($user, PermissionKey::ResidentDirectoryView) !== [];
    }

    /**
     * Determine whether the user can view the public community resident directory in a scope.
     */
    public function viewDirectory(User $user, ScopeRef|Resident|null $target = null): bool
    {
        if ($target === null) {
            return false;
        }

        $scope = $target instanceof Resident ? $target->scopeRef() : $target;

        return $this->access->allows($user, PermissionKey::ResidentDirectoryView, $scope);
    }

    /**
     * Determine whether the user can view non-sensitive resident profile details.
     */
    public function view(User $user, Resident $resident): bool
    {
        return $this->access->allows($user, PermissionKey::ResidentView, $resident->scopeRef());
    }

    /**
     * Determine whether the user can view sensitive resident details (full NIK, phone, civil documents).
     */
    public function viewSensitive(User $user, Resident $resident): bool
    {
        return $this->access->allows($user, PermissionKey::ResidentViewSensitive, $resident->scopeRef());
    }

    /**
     * Map the caller's authority over a resident to the API representation tier.
     *
     * The returned tier is the ONLY input a Resource needs; Resources never authorize.
     * Sensitive requires resident.view AND resident.view_sensitive, so a sensitive-only
     * grant never leaks more than the normal profile contract allows.
     *
     * @return PrivacyTier|null null = no access at all (caller must respond 403/404)
     */
    public function representationTier(User $user, Resident $resident): ?PrivacyTier
    {
        if ($this->view($user, $resident)) {
            return $this->viewSensitive($user, $resident) ? PrivacyTier::Sensitive : PrivacyTier::Normal;
        }

        if ($this->viewDirectory($user, $resident)) {
            return PrivacyTier::Directory;
        }

        return null;
    }

    /**
     * Determine whether the user can register a new prospective resident into a scope.
     */
    public function register(User $user, ScopeRef|Resident|null $target = null): bool
    {
        if ($target === null) {
            return false;
        }

        $scope = $target instanceof Resident ? $target->scopeRef() : $target;

        return $this->access->allows($user, PermissionKey::ResidentRegister, $scope);
    }

    /**
     * Determine whether the user can verify a prospective resident in their scope.
     */
    public function verify(User $user, Resident $resident): bool
    {
        return $this->access->allows($user, PermissionKey::ResidentVerify, $resident->scopeRef());
    }

    /**
     * Determine whether the user can update resident details.
     */
    public function update(User $user, Resident $resident): bool
    {
        return $this->access->allows($user, PermissionKey::ResidentUpdate, $resident->scopeRef());
    }

    /**
     * Determine whether the user can relocate a resident from their source scope,
     * and optionally into a target scope.
     */
    public function relocate(User $user, Resident $resident, ?ScopeRef $targetScope = null): bool
    {
        if (! $this->access->allows($user, PermissionKey::ResidentRelocate, $resident->scopeRef())) {
            return false;
        }

        // If an internal target scope is specified, actor must also have authority over target scope
        if ($targetScope !== null && ! $this->access->allows($user, PermissionKey::ResidentRelocate, $targetScope)) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can remove a resident from household membership.
     */
    public function removeFromHousehold(User $user, Resident $resident): bool
    {
        return $this->access->allows($user, PermissionKey::ResidentRemoveFromHousehold, $resident->scopeRef());
    }

    /**
     * Determine whether the user can change civil status (including recording death).
     */
    public function changeStatus(User $user, Resident $resident): bool
    {
        return $this->access->allows($user, PermissionKey::ResidentChangeStatus, $resident->scopeRef());
    }
}
