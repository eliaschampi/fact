<?php

namespace App\Support;

/**
 * Peruvian RUC check digit (módulo 11), same algorithm as the reference
 * implementations validated against the SUNAT registry.
 */
final class RucNumber
{
    private const array WEIGHTS = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];

    public static function isValid(string $ruc): bool
    {
        if (! preg_match('/^\d{11}$/', $ruc)) {
            return false;
        }

        $sum = 0;

        foreach (self::WEIGHTS as $index => $weight) {
            $sum += $weight * (int) $ruc[$index];
        }

        return (int) $ruc[10] === (11 - ($sum % 11)) % 10;
    }
}
