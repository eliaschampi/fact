<?php

namespace Tests\Unit;

use App\Support\Fiscal\CpeState;
use InvalidArgumentException;
use Tests\TestCase;

class CpeStateTest extends TestCase
{
    public function test_happy_path_factura_transitions(): void
    {
        $this->assertTrue(CpeState::canTransition(CpeState::RESERVED, CpeState::SIGNED));
        $this->assertTrue(CpeState::canTransition(CpeState::QUEUED, CpeState::SUBMITTED));
        $this->assertTrue(CpeState::canTransition(CpeState::SUBMITTED, CpeState::ACCEPTED));
    }

    public function test_accepted_cannot_return_to_reserved_or_signed(): void
    {
        $this->assertFalse(CpeState::canTransition(CpeState::ACCEPTED, CpeState::RESERVED));
        $this->assertFalse(CpeState::canTransition(CpeState::ACCEPTED, CpeState::SIGNED));
        $this->assertFalse(CpeState::canTransition(CpeState::REJECTED, CpeState::SUBMITTED));
    }

    public function test_transport_error_retries_same_xml_path(): void
    {
        $this->assertTrue(CpeState::canRetrySameXml(CpeState::TRANSPORT_ERROR));
        $this->assertFalse(CpeState::canRetrySameXml(CpeState::REJECTED));
        CpeState::assertCanTransition(CpeState::TRANSPORT_ERROR, CpeState::SUBMITTED);
    }

    public function test_illegal_transition_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CpeState::assertCanTransition(CpeState::ACCEPTED, CpeState::QUEUED);
    }
}
