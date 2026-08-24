<?php

namespace Tests\Unit;

use App\Support\Sunat\CdrQueryResult;
use App\Support\Sunat\CpeGateway;
use App\Support\Sunat\FakeCpeGateway;
use App\Support\Sunat\TicketResult;
use Tests\TestCase;

class TicketResultTest extends TestCase
{
    public function test_soap_and_gre_share_the_same_ticket_codes(): void
    {
        $this->assertSame(TicketResult::PENDING, TicketResult::fromSunatTicket('98')->status);
        $this->assertSame(TicketResult::COMPLETED, TicketResult::fromSunatTicket('0')->status);
        $this->assertSame(TicketResult::FAILED, TicketResult::fromSunatTicket('99')->status);
        $this->assertSame(TicketResult::ERROR, TicketResult::fromSunatTicket('127')->status);
        $this->assertSame(TicketResult::ERROR, TicketResult::fromSunatTicket(null)->status);
        $this->assertSame(TicketResult::ERROR, TicketResult::fromSunatTicket('foobar')->status);
        $this->assertSame(TicketResult::ERROR, TicketResult::fromSunatTicket('0foo')->status);
        $this->assertSame(TicketResult::ERROR, TicketResult::fromSunatTicket('00')->status);
        $this->assertSame(TicketResult::ERROR, TicketResult::fromSunatTicket('098')->status);
    }

    public function test_code_99_is_not_treated_as_accepted(): void
    {
        $result = TicketResult::fromSunatTicket('99', cdrDescription: 'Proceso con errores');

        $this->assertSame(TicketResult::FAILED, $result->status);
        $this->assertNotSame(TicketResult::COMPLETED, $result->status);
    }

    public function test_query_ticket_without_credentials_returns_error_instead_of_throwing(): void
    {
        config([
            'sunat.certificate_path' => null,
            'sunat.sol.ruc' => '',
            'sunat.sol.user' => '',
            'sunat.sol.password' => '',
        ]);

        $result = app(CpeGateway::class)->queryTicket('dummy-ticket');

        $this->assertSame(TicketResult::ERROR, $result->status);
        $this->assertNotEmpty($result->faultMessage);
    }

    public function test_fake_gateway_can_simulate_a_rejected_cdr(): void
    {
        $fake = new FakeCpeGateway(
            ticket: TicketResult::fromSunatTicket('99'),
            cdr: new CdrQueryResult(
                found: true,
                cdrZip: 'fake',
                cdrCode: '2324',
                cdrDescription: 'Rechazado (fake)',
                notes: [],
                faultMessage: null,
            ),
        );

        $this->assertSame(TicketResult::FAILED, $fake->queryTicket('t')->status);
        $this->assertSame('2324', $fake->queryCdr('20', '01', 'F001', 1)->cdrCode);
    }
}
