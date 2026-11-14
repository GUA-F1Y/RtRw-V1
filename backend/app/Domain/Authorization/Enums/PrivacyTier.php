<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Enums;

/**
 * The representation tier an authorized caller selects for an API resource.
 *
 * Flow: Policy decides -> a tier is chosen -> the Resource serializes that tier.
 * Resources never decide the tier themselves and never read it from the request.
 *
 * Resident:   Directory = resident.directory_view, Normal = resident.view,
 *             Sensitive = resident.view + resident.view_sensitive.
 * Household:  Directory (safe) = no KK number, Normal = household.view (KK masked),
 *             Sensitive = household.view + household.view_sensitive (KK in full).
 * Ledgers (movements / status history): Sensitive adds restricted metadata
 *             (reference letter, notes, attachment, recorder); any other tier is the safe shape.
 */
enum PrivacyTier: string
{
    case Directory = 'directory';
    case Normal = 'normal';
    case Sensitive = 'sensitive';

    public function atLeast(self $minimum): bool
    {
        return $this->rank() >= $minimum->rank();
    }

    private function rank(): int
    {
        return match ($this) {
            self::Directory => 0,
            self::Normal => 1,
            self::Sensitive => 2,
        };
    }
}
