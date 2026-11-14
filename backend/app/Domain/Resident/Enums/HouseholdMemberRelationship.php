<?php

declare(strict_types=1);

namespace App\Domain\Resident\Enums;

enum HouseholdMemberRelationship: string
{
    case KepalaKeluarga = 'KEPALA_KELUARGA';
    case Suami = 'SUAMI';
    case Istri = 'ISTRI';
    case Anak = 'ANAK';
    case Menantu = 'MENANTU';
    case Cucu = 'CUCU';
    case OrangTua = 'ORANG_TUA';
    case Mertua = 'MERTUA';
    case FamiliLain = 'FAMILI_LAIN';
    case Pembantu = 'PEMBANTU';
    case Lainnya = 'LAINNYA';
}
