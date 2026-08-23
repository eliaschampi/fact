<?php

namespace App\Support\Sunat;

final readonly class SunatCredentials
{
    public function __construct(
        public string $ruc,
        public string $solUser,
        public string $solPassword,
        public ?string $certificatePem,
        public ?string $greClientId,
        public ?string $greClientSecret,
    ) {}

    public static function fromConfig(): self
    {
        $path = config('sunat.certificate_path');
        $pem = is_string($path) && $path !== '' && is_readable($path)
            ? (string) file_get_contents($path)
            : null;

        return new self(
            ruc: (string) config('sunat.sol.ruc'),
            solUser: (string) config('sunat.sol.user'),
            solPassword: (string) config('sunat.sol.password'),
            certificatePem: $pem,
            greClientId: config('sunat.gre_oauth.client_id') ?: null,
            greClientSecret: config('sunat.gre_oauth.client_secret') ?: null,
        );
    }

    public function hasSol(): bool
    {
        return $this->ruc !== '' && $this->solUser !== '' && $this->solPassword !== '';
    }

    public function hasCertificate(): bool
    {
        return is_string($this->certificatePem) && $this->certificatePem !== '';
    }
}
