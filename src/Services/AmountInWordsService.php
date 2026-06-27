<?php

declare(strict_types=1);

namespace App\Services;

final class AmountInWordsService
{
    private const UNITS = [
        '', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
        'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize',
        'dix-sept', 'dix-huit', 'dix-neuf',
    ];

    private const TENS = [
        '', '', 'vingt', 'trente', 'quarante', 'cinquante',
        'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt',
    ];

    public function convert(int $amount, string $currency = 'Francs CFA'): string
    {
        if ($amount === 0) {
            return "Arrêter à zéro {$currency}";
        }

        $words = ucfirst(trim($this->toWords($amount)));
        return "Arrêter la présente facture Proforma à la somme de {$words} {$currency}";
    }

    private function toWords(int $n): string
    {
        if ($n < 0) {
            return 'moins ' . $this->toWords(-$n);
        }

        $result = '';

        if ($n >= 1_000_000) {
            $millions = intdiv($n, 1_000_000);
            $result  .= $this->toWords($millions) . ' million' . ($millions > 1 ? 's' : '') . ' ';
            $n       %= 1_000_000;
        }

        if ($n >= 1_000) {
            $thousands = intdiv($n, 1_000);
            $result   .= ($thousands === 1 ? 'mille' : $this->toWords($thousands) . ' mille') . ' ';
            $n        %= 1_000;
        }

        if ($n >= 100) {
            $hundreds = intdiv($n, 100);
            $suffix   = ($n % 100 === 0 && $hundreds > 1) ? 's' : '';
            $result  .= ($hundreds === 1 ? 'cent' : self::UNITS[$hundreds] . ' cent') . $suffix . ' ';
            $n       %= 100;
        }

        if ($n >= 20) {
            $ten  = intdiv($n, 10);
            $unit = $n % 10;

            if ($ten === 7 || $ten === 9) {
                $result .= self::TENS[$ten] . '-' . self::UNITS[10 + $unit] . ' ';
            } else {
                $liaison = ($unit === 1 && $ten !== 8) ? '-et-' : ($unit ? '-' : '');
                $result .= self::TENS[$ten] . $liaison . ($unit ? self::UNITS[$unit] : '') . ' ';
            }
        } elseif ($n > 0) {
            $result .= self::UNITS[$n] . ' ';
        }

        return $result;
    }
}
