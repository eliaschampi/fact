<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public const int MAX_CENTS = 999_999_999_999;

    private const string DECIMAL_BODY = '\d{1,10}(?:\.\d{1,2})?';

    public const string UNSIGNED_PATTERN = '/^'.self::DECIMAL_BODY.'$/';

    private const string SIGNED_PATTERN = '/^-?'.self::DECIMAL_BODY.'$/';

    public static function toCents(string|int $amount): int
    {
        $normalized = trim((string) $amount);

        if (! preg_match(self::SIGNED_PATTERN, $normalized)) {
            throw new InvalidArgumentException('Invalid monetary amount.');
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $negative = str_starts_with($whole, '-');
        $cents = (abs((int) $whole) * 100) + (int) str_pad($fraction, 2, '0');

        return $negative ? -$cents : $cents;
    }

    public static function fromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return sprintf('%s%d.%02d', $sign, intdiv($absolute, 100), $absolute % 100);
    }

    public static function fitsDatabase(int $cents): bool
    {
        return abs($cents) <= self::MAX_CENTS;
    }
}
