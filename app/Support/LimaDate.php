<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class LimaDate
{
    public const string TIMEZONE = 'America/Lima';

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE);
    }

    public static function today(): CarbonImmutable
    {
        return self::now()->startOfDay();
    }

    /**
     * Civil business date in Lima (Y-m-d). Always computed on the server.
     */
    public static function businessDate(): string
    {
        return self::now()->toDateString();
    }
}
