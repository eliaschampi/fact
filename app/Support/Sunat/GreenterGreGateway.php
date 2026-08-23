<?php

namespace App\Support\Sunat;

use Greenter\Api;
use Greenter\Api\ApiFactory;
use Greenter\Services\Api\TokenStoreInterface;
use Greenter\Sunat\GRE\Api\AuthApi;
use Greenter\Sunat\GRE\Configuration;
use GuzzleHttp\Client;
use RuntimeException;
use Throwable;

final class GreenterGreGateway implements GreGateway
{
    public function __construct(
        private readonly SunatEndpoints $endpoints,
        private readonly TokenStoreInterface $tokenStore,
    ) {}

    public function sendSigned(SignedXml $xml): SubmissionResult
    {
        $endpoint = $this->endpoints->greCpe();

        try {
            $result = $this->api()->sendXml($xml->filename, $xml->contents);
        } catch (Throwable $exception) {
            return new SubmissionResult(
                transportOk: false,
                ticket: null,
                cdrZip: null,
                cdrCode: null,
                cdrDescription: null,
                notes: [],
                faultCode: $exception->getCode() !== 0 ? (string) $exception->getCode() : null,
                faultMessage: $exception->getMessage(),
                endpoint: $endpoint,
            );
        }

        if (! $result?->isSuccess()) {
            return new SubmissionResult(
                transportOk: false,
                ticket: method_exists($result, 'getTicket') ? $result->getTicket() : null,
                cdrZip: null,
                cdrCode: null,
                cdrDescription: null,
                notes: [],
                faultCode: $result?->getError()?->getCode(),
                faultMessage: $result?->getError()?->getMessage() ?? 'Respuesta GRE vacía.',
                endpoint: $endpoint,
            );
        }

        return new SubmissionResult(
            transportOk: true,
            ticket: method_exists($result, 'getTicket') ? $result->getTicket() : null,
            cdrZip: method_exists($result, 'getCdrZip') ? $result->getCdrZip() : null,
            cdrCode: null,
            cdrDescription: null,
            notes: [],
            faultCode: null,
            faultMessage: null,
            endpoint: $endpoint,
        );
    }

    public function queryTicket(string $ticket): TicketResult
    {
        $result = $this->api()->getStatus($ticket);
        $code = (int) $result->getCode();
        $status = match (true) {
            $code === 98 => TicketResult::PENDING,
            $code === 0, $code === 99 => TicketResult::COMPLETED,
            default => TicketResult::ERROR,
        };

        $cdr = $result->getCdrResponse();

        return new TicketResult(
            status: $status,
            sunatCode: (string) $code,
            cdrZip: $result->getCdrZip(),
            cdrCode: $cdr?->getCode(),
            cdrDescription: $cdr?->getDescription(),
            notes: $cdr?->getNotes() ?? [],
            faultMessage: $result->isSuccess() ? null : $result->getError()?->getMessage(),
        );
    }

    private function api(): Api
    {
        $credentials = SunatCredentials::fromConfig();

        if (! $credentials->hasCertificate() || ! $credentials->hasSol() || $credentials->greClientId === null) {
            throw new RuntimeException(
                'GRE requiere certificado, Clave SOL y client_id/client_secret OAuth.',
            );
        }

        $client = new Client;
        $config = (new Configuration)->setHost($this->endpoints->greAuth());
        $factory = new ApiFactory(
            new AuthApi($client, $config),
            $client,
            $this->tokenStore,
            $this->endpoints->greCpe(),
        );

        $api = new Api([
            'auth' => $this->endpoints->greAuth(),
            'cpe' => $this->endpoints->greCpe(),
        ], $factory);

        $api->setCertificate($credentials->certificatePem);
        $api->setClaveSOL($credentials->ruc, $credentials->solUser, $credentials->solPassword);
        $api->setApiCredentials($credentials->greClientId, (string) $credentials->greClientSecret);

        return $api;
    }
}
