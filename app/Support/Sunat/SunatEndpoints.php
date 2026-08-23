<?php

namespace App\Support\Sunat;

use InvalidArgumentException;

final class SunatEndpoints
{
    public function environment(): string
    {
        $environment = (string) config('sunat.environment', 'beta');

        if (! in_array($environment, ['beta', 'production'], true)) {
            throw new InvalidArgumentException('SUNAT_ENVIRONMENT must be beta or production.');
        }

        return $environment;
    }

    public function billService(): string
    {
        $environment = $this->environment();

        return $environment === 'production'
            ? (string) config('sunat.endpoints.fe.production')
            : (string) config('sunat.endpoints.fe.beta');
    }

    public function cdrConsult(): string
    {
        return (string) config('sunat.endpoints.fe.consulta_cdr');
    }

    public function homologacion(): string
    {
        return (string) config('sunat.endpoints.fe.homologacion');
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
