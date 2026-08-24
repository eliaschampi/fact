<?php

namespace App\Support\Sunat;

use Greenter\Api;
use Greenter\Api\ApiFactory;
use Greenter\Model\Response\BillResult;
use Greenter\Model\Response\SummaryResult;
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
            return SubmissionResult::transportError($endpoint, $exception);
        }

        $ticket = $result instanceof SummaryResult ? $result->getTicket() : null;

        if (! $result?->isSuccess()) {
            return SubmissionResult::fault(
                $endpoint,
                $result?->getError()?->getCode(),
                $result?->getError()?->getMessage() ?? 'Respuesta GRE vacía.',
                $ticket,
            );
        }

        $cdr = $result instanceof BillResult ? $result->getCdrResponse() : null;

        return new SubmissionResult(
            transportOk: true,
            ticket: $ticket,
            cdrZip: $result instanceof BillResult ? $result->getCdrZip() : null,
            cdrCode: $cdr?->getCode(),
            cdrDescription: $cdr?->getDescription(),
            notes: $cdr?->getNotes() ?? [],
            faultCode: null,
            faultMessage: null,
            endpoint: $endpoint,
        );
    }

    public function queryTicket(string $ticket): TicketResult
    {
        try {
            $result = $this->api()->getStatus($ticket);
        } catch (Throwable $exception) {
            return TicketResult::transportError($exception->getMessage());
        }

        $cdr = $result->getCdrResponse();

        return TicketResult::fromSunatTicket(
            $result->getCode() ?? $result->getError()?->getCode(),
            $result->getCdrZip(),
            $cdr?->getCode(),
            $cdr?->getDescription(),
            $cdr?->getNotes() ?? [],
            $result->isSuccess() ? null : $result->getError()?->getMessage(),
        );
    }

    private function api(): Api
    {
        $credentials = SunatCredentials::fromConfig();

        if (! $credentials->hasCertificate() || ! $credentials->hasSol() || ! $credentials->hasGreOAuth()) {
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
