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

    /**
     * @param bool $standalone true when the result ends the number (governs plurals:
     *                         "deux cents" vs "deux cent mille", "quatre-vingts" vs
     *                         "quatre-vingt mille").
     */
    private function toWords(int $n, bool $standalone = true): string
    {
        if ($n < 0) {
            return 'moins ' . $this->toWords(-$n, $standalone);
        }

        $result = '';

        if ($n >= 1_000_000) {
            $millions = intdiv($n, 1_000_000);
            $result  .= trim($this->toWords($millions, false)) . ' million' . ($millions > 1 ? 's' : '') . ' ';
            $n       %= 1_000_000;
        }

        if ($n >= 1_000) {
            $thousands = intdiv($n, 1_000);
            // "cent" loses its plural 's' when used as a multiplier before "mille"
            $result   .= ($thousands === 1 ? 'mille' : trim($this->toWords($thousands, false)) . ' mille') . ' ';
            $n        %= 1_000;
        }

        if ($n >= 100) {
            $hundreds = intdiv($n, 100);
            // "cents" only when exactly N×100 with N>1 AND not used as a multiplier
            $suffix   = ($n % 100 === 0 && $hundreds > 1 && $standalone) ? 's' : '';
            $result  .= ($hundreds === 1 ? 'cent' : self::UNITS[$hundreds] . ' cent') . $suffix . ' ';
            $n       %= 100;
        }

        if ($n >= 20) {
            $ten  = intdiv($n, 10);
            $unit = $n % 10;

            if ($ten === 7 || $ten === 9) {
                // 71 = soixante-et-onze, 91 = quatre-vingt-onze (no "et" for 91)
                $liaison = ($ten === 7 && $unit === 1) ? '-et-' : '-';
                $result .= self::TENS[$ten] . $liaison . self::UNITS[10 + $unit] . ' ';
            } else {
                // "quatre-vingts" only when 80 is the final unit with nothing after
                $suffix80 = ($ten === 8 && $unit === 0 && $standalone) ? 's' : '';
                $liaison  = ($unit === 1 && $ten !== 8) ? '-et-' : ($unit ? '-' : '');
                $result  .= self::TENS[$ten] . $suffix80 . $liaison . ($unit ? self::UNITS[$unit] : '') . ' ';
            }
        } elseif ($n > 0) {
            $result .= self::UNITS[$n] . ' ';
        }

        return $result;
    }
}
