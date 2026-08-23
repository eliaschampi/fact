<?php

namespace App\Support\Sunat;

final class FakeCpeGateway implements CpeGateway
{
    public function __construct(
        private readonly ?SubmissionResult $send = null,
        private readonly ?TicketResult $ticket = null,
        private readonly ?CdrQueryResult $cdr = null,
    ) {}

    public function signXml(string $xmlContents, string $filename): SignedXml
    {
        return SignedXml::fromContents($xmlContents, $filename);
    }

    public function sendSigned(SignedXml $xml): SubmissionResult
    {
        return $this->send ?? new SubmissionResult(
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
        return $this->ticket ?? TicketResult::fromSunatTicket('0', 'fake-cdr', '0', 'Aceptado (fake)');
    }

    public function queryCdr(string $ruc, string $tipo, string $serie, int $numero): CdrQueryResult
    {
        return $this->cdr ?? new CdrQueryResult(
            found: true,
            cdrZip: 'fake-cdr',
            cdrCode: '0',
            cdrDescription: 'Aceptado (fake)',
            notes: [],
            faultMessage: null,
        );
    }
}
