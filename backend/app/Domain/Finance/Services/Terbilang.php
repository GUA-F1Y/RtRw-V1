<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

final class Terbilang
{
    private const WORDS = [
        '',
        'Satu',
        'Dua',
        'Tiga',
        'Empat',
        'Lima',
        'Enam',
        'Tujuh',
        'Delapan',
        'Sembilan',
        'Sepuluh',
        'Sebelas',
    ];

    /**
     * Convert an integer amount into formal Indonesian words suffixed with "Rupiah".
     * Example: 1500000 -> "Satu Juta Lima Ratus Ribu Rupiah"
     */
    public static function rupiah(int|float $number): string
    {
        $amount = (int) floor(abs($number));

        if ($amount === 0) {
            return 'Nol Rupiah';
        }

        return trim(self::convert($amount)).' Rupiah';
    }

    /**
     * Convert a positive integer into Indonesian words.
     */
    public static function convert(int $number): string
    {
        if ($number < 12) {
            return self::WORDS[$number];
        }

        if ($number < 20) {
            return self::convert($number - 10).' Belas';
        }

        if ($number < 100) {
            $ten = (int) ($number / 10);
            $rem = $number % 10;

            return trim(self::WORDS[$ten].' Puluh '.($rem > 0 ? self::convert($rem) : ''));
        }

        if ($number < 200) {
            $rem = $number - 100;

            return trim('Seratus '.($rem > 0 ? self::convert($rem) : ''));
        }

        if ($number < 1000) {
            $hundred = (int) ($number / 100);
            $rem = $number % 100;

            return trim(self::WORDS[$hundred].' Ratus '.($rem > 0 ? self::convert($rem) : ''));
        }

        if ($number < 2000) {
            $rem = $number - 1000;

            return trim('Seribu '.($rem > 0 ? self::convert($rem) : ''));
        }

        if ($number < 1000000) {
            $thousand = (int) ($number / 1000);
            $rem = $number % 1000;

            return trim(self::convert($thousand).' Ribu '.($rem > 0 ? self::convert($rem) : ''));
        }

        if ($number < 1000000000) {
            $million = (int) ($number / 1000000);
            $rem = $number % 1000000;

            return trim(self::convert($million).' Juta '.($rem > 0 ? self::convert($rem) : ''));
        }

        if ($number < 1000000000000) {
            $billion = (int) ($number / 1000000000);
            $rem = $number % 1000000000;

            return trim(self::convert($billion).' Miliar '.($rem > 0 ? self::convert($rem) : ''));
        }

        $trillion = (int) ($number / 1000000000000);
        $rem = $number % 1000000000000;

        return trim(self::convert($trillion).' Triliun '.($rem > 0 ? self::convert($rem) : ''));
    }
}
