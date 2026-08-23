<?php

namespace App\Support\Sunat;

interface GreGateway
{
    public function sendSigned(SignedXml $xml): SubmissionResult;

    public function queryTicket(string $ticket): TicketResult;
}
