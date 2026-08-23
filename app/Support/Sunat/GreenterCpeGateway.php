<?php

namespace App\Support\Sunat;

use Greenter\Model\Response\BillResult;
use Greenter\Model\Response\StatusResult;
use Greenter\See;
use Greenter\Ws\Services\ConsultCdrService;
use Greenter\Ws\Services\SoapClient;
use Greenter\XMLSecLibs\Sunat\SignedXml as GreenterSigner;
use RuntimeException;
use Throwable;

final class GreenterCpeGateway implements CpeGateway
{
    public function __construct(
        private readonly GreenterSeeFactory $seeFactory,
        private readonly SunatEndpoints $endpoints,
    ) {}

    public function signXml(string $xmlContents, string $filename): SignedXml
    {
        $credentials = SunatCredentials::fromConfig();

        if (! $credentials->hasCertificate()) {
            throw new RuntimeException('No hay certificado digital configurado para firmar.');
        }

        $signer = new GreenterSigner;
        $signer->setCertificate($credentials->certificatePem);
        $signed = $signer->signXml($xmlContents);

        return SignedXml::fromContents($signed, $filename);
    }

    public function sendSigned(SignedXml $xml): SubmissionResult
    {
        $endpoint = $this->endpoints->billService();

        try {
            $result = $this->seeFactory->makeReadyToSend()->sendXmlFile($xml->contents);
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

        if (! $result instanceof BillResult && ! $result instanceof StatusResult) {
            return new SubmissionResult(
                transportOk: false,
                ticket: method_exists($result, 'getTicket') ? $result->getTicket() : null,
                cdrZip: null,
                cdrCode: null,
                cdrDescription: null,
                notes: [],
                faultCode: $result?->getError()?->getCode(),
                faultMessage: $result?->getError()?->getMessage() ?? 'Respuesta SUNAT vacía.',
                endpoint: $endpoint,
            );
        }

        if (! $result->isSuccess()) {
            return new SubmissionResult(
                transportOk: false,
                ticket: method_exists($result, 'getTicket') ? $result->getTicket() : null,
                cdrZip: null,
                cdrCode: null,
                cdrDescription: null,
                notes: [],
                faultCode: $result->getError()?->getCode(),
                faultMessage: $result->getError()?->getMessage(),
                endpoint: $endpoint,
            );
        }

        $cdr = method_exists($result, 'getCdrResponse') ? $result->getCdrResponse() : null;

        return new SubmissionResult(
            transportOk: true,
            ticket: method_exists($result, 'getTicket') ? $result->getTicket() : null,
            cdrZip: method_exists($result, 'getCdrZip') ? $result->getCdrZip() : null,
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
        $result = $this->seeFactory->makeReadyToSend()->getStatus($ticket);

        if (! $result->isSuccess()) {
            return new TicketResult(
                status: TicketResult::ERROR,
                sunatCode: $result->getError()?->getCode(),
                cdrZip: null,
                cdrCode: null,
                cdrDescription: null,
                notes: [],
                faultMessage: $result->getError()?->getMessage(),
            );
        }

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
            faultMessage: null,
        );
    }

    public function queryCdr(string $ruc, string $tipo, string $serie, int $numero): CdrQueryResult
    {
        $credentials = SunatCredentials::fromConfig();
        $client = new SoapClient;
        $client->setService($this->endpoints->cdrConsult());

        if ($credentials->hasSol()) {
            $client->setCredentials($credentials->ruc.$credentials->solUser, $credentials->solPassword);
        }

        $service = new ConsultCdrService;
        $service->setClient($client);
        $result = $service->getStatusCdr($ruc, $tipo, $serie, $numero);

        if (! $result->isSuccess()) {
            return new CdrQueryResult(
                found: false,
                cdrZip: null,
                cdrCode: null,
                cdrDescription: null,
                notes: [],
                faultMessage: $result->getError()?->getMessage() ?? $result->getMessage(),
            );
        }

        $cdr = $result->getCdrResponse();

        return new CdrQueryResult(
            found: $cdr !== null || $result->getCdrZip() !== null,
            cdrZip: $result->getCdrZip(),
            cdrCode: $cdr?->getCode(),
            cdrDescription: $cdr?->getDescription() ?? $result->getMessage(),
            notes: $cdr?->getNotes() ?? [],
            faultMessage: null,
        );
    }

    public function see(): See
    {
        return $this->seeFactory->make();
    }
}
