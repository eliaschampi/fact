<?php

namespace App\Support\Sunat;

use InvalidArgumentException;

final class SunatEndpoints
{
    public const string BETA = 'beta';

    public const string PRODUCTION = 'production';

    public function environment(): string
    {
        $environment = (string) config('sunat.environment', self::BETA);

        if (! in_array($environment, [self::BETA, self::PRODUCTION], true)) {
            throw new InvalidArgumentException('SUNAT_ENVIRONMENT must be beta or production.');
        }

        return $environment;
    }

    public function billService(): string
    {
        return $this->environment() === self::PRODUCTION
            ? (string) config('sunat.endpoints.fe.production')
            : (string) config('sunat.endpoints.fe.beta');
    }

    public function cdrConsult(): string
    {
        return (string) config('sunat.endpoints.fe.consulta_cdr');
    }

    public function greAuth(): string
    {
        return (string) config('sunat.endpoints.gre.auth');
    }

    public function greCpe(): string
    {
        return (string) config('sunat.endpoints.gre.cpe');
    }
}
