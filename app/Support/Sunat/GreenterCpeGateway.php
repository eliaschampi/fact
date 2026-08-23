<?php

namespace App\Support\Sunat;

use Greenter\Model\Response\BillResult;
use Greenter\Model\Response\SummaryResult;
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
            return SubmissionResult::transportError($endpoint, $exception);
        }

        if ($result instanceof SummaryResult) {
            if (! $result->isSuccess()) {
                return SubmissionResult::fault($endpoint, $result->getError()?->getCode(), $result->getError()?->getMessage(), $result->getTicket());
            }

            return new SubmissionResult(
                transportOk: true,
                ticket: $result->getTicket(),
                cdrZip: null,
                cdrCode: null,
                cdrDescription: null,
                notes: [],
                faultCode: null,
                faultMessage: null,
                endpoint: $endpoint,
            );
        }

        if (! $result instanceof BillResult) {
            return SubmissionResult::fault(
                $endpoint,
                $result?->getError()?->getCode(),
                $result?->getError()?->getMessage() ?? 'Respuesta SUNAT vacía.',
            );
        }

        if (! $result->isSuccess()) {
            return SubmissionResult::fault($endpoint, $result->getError()?->getCode(), $result->getError()?->getMessage());
        }

        $cdr = $result->getCdrResponse();

        return new SubmissionResult(
            transportOk: true,
            ticket: null,
            cdrZip: $result->getCdrZip(),
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
            $result = $this->seeFactory->makeReadyToSend()->getStatus($ticket);
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

    public function queryCdr(string $ruc, string $tipo, string $serie, int $numero): CdrQueryResult
    {
        try {
            $service = $this->seeFactory->makeCdrConsultService();
            $result = $service->getStatusCdr($ruc, $tipo, $serie, $numero);
        } catch (Throwable $exception) {
            return CdrQueryResult::transportError($exception->getMessage());
        }

        if (! $result->isSuccess()) {
            return CdrQueryResult::transportError(
                $result->getError()?->getMessage() ?? $result->getMessage(),
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
}
