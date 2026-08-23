<?php

namespace App\Support\Sunat;

final class FakeCpeGateway implements CpeGateway
{
    public function signXml(string $xmlContents, string $filename): SignedXml
    {
        return SignedXml::fromContents($xmlContents, $filename);
    }

    public function sendSigned(SignedXml $xml): SubmissionResult
    {
        return new SubmissionResult(
            transportOk: true,
            ticket: null,
            cdrZip: 'fake-cdr',
            cdrCode: '0',
            cdrDescription: 'Aceptado (fake)',
            notes: [],
            faultCode: null,
            faultMessage: null,
            endpoint: 'fake://sunat',
        );
    }

    public function queryTicket(string $ticket): TicketResult
    {
        return new TicketResult(
            status: TicketResult::COMPLETED,
            sunatCode: '0',
            cdrZip: 'fake-cdr',
            cdrCode: '0',
            cdrDescription: 'Aceptado (fake)',
            notes: [],
            faultMessage: null,
        );
    }

    public function queryCdr(string $ruc, string $tipo, string $serie, int $numero): CdrQueryResult
    {
        return new CdrQueryResult(
            found: true,
            cdrZip: 'fake-cdr',
            cdrCode: '0',
            cdrDescription: 'Aceptado (fake)',
            notes: [],
            faultMessage: null,
        );
    }
}
