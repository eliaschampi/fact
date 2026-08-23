<?php

namespace App\Support\Sunat;

use Greenter\See;
use Greenter\Ws\Services\ConsultCdrService;
use Greenter\Ws\Services\SoapClient;
use RuntimeException;

final class GreenterSeeFactory
{
    public function __construct(private readonly SunatEndpoints $endpoints) {}

    public function make(?SunatCredentials $credentials = null): See
    {
        $credentials ??= SunatCredentials::fromConfig();
        $see = new See;
        $see->setService($this->endpoints->billService());

        if ($credentials->hasCertificate()) {
            $see->setCertificate($credentials->certificatePem);
        }

        if ($credentials->hasSol()) {
            $see->setClaveSOL($credentials->ruc, $credentials->solUser, $credentials->solPassword);
        }

        return $see;
    }

    public function makeReadyToSend(?SunatCredentials $credentials = null): See
    {
        $credentials ??= SunatCredentials::fromConfig();

        if (! $credentials->hasCertificate() || ! $credentials->hasSol()) {
            throw new RuntimeException(
                'SUNAT certificate and Clave SOL must be configured before sending.',
            );
        }

        return $this->make($credentials);
    }

    public function makeCdrConsultService(?SunatCredentials $credentials = null): ConsultCdrService
    {
        $credentials ??= SunatCredentials::fromConfig();
        $client = new SoapClient;
        $client->setService($this->endpoints->cdrConsult());

        if ($credentials->hasSol()) {
            $client->setCredentials($credentials->ruc.$credentials->solUser, $credentials->solPassword);
        }

        $service = new ConsultCdrService;
        $service->setClient($client);

        return $service;
    }
}
