<?php

namespace App\Support\Sunat;

interface CpeGateway
{
    public function signXml(string $xmlContents, string $filename): SignedXml;

    public function sendSigned(SignedXml $xml): SubmissionResult;

    public function queryTicket(string $ticket): TicketResult;

    public function queryCdr(string $ruc, string $tipo, string $serie, int $numero): CdrQueryResult;
}
