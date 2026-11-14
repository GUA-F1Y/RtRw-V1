<?php

declare(strict_types=1);

namespace App\Domain\Resident\Enums;

enum ResidentEducation: string
{
    case None = 'NONE';
    case Sd = 'SD';
    case Smp = 'SMP';
    case Sma = 'SMA';
    case Diploma = 'DIPLOMA';
    case SarjanaS1 = 'SARJANA_S1';
    case MagisterS2 = 'MAGISTER_S2';
    case DoktorS3 = 'DOKTOR_S3';
}
