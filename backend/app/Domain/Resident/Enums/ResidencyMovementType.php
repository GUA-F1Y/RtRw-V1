<?php

declare(strict_types=1);

namespace App\Domain\Resident\Enums;

enum ResidencyMovementType: string
{
    case MoveInExternal = 'MOVE_IN_EXTERNAL';
    case MoveOutExternal = 'MOVE_OUT_EXTERNAL';
    case TransferInternal = 'TRANSFER_INTERNAL';
    case HouseholdSplit = 'HOUSEHOLD_SPLIT';
}
