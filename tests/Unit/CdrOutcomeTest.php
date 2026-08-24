<?php

namespace Tests\Unit;

use App\Support\Fiscal\CpeState;
use App\Support\Sunat\CdrOutcome;
use Tests\TestCase;

class CdrOutcomeTest extends TestCase
{
    public function test_code_zero_without_notes_is_accepted(): void
    {
        $outcome = CdrOutcome::fromCode('0', 'La Factura ha sido aceptada');

        $this->assertSame(CpeState::ACCEPTED, $outcome->status);
    }

    public function test_code_zero_with_notes_is_accepted_with_observations(): void
    {
        $outcome = CdrOutcome::fromCode('0', 'Aceptada', ['Observación 4008']);

        $this->assertSame(CpeState::ACCEPTED_WITH_OBSERVATIONS, $outcome->status);
    }

    public function test_code_4000_plus_is_accepted_with_observations(): void
    {
        $outcome = CdrOutcome::fromCode('4008', 'Observado');

        $this->assertSame(CpeState::ACCEPTED_WITH_OBSERVATIONS, $outcome->status);
    }

    public function test_code_2000_range_is_rejected(): void
    {
        $outcome = CdrOutcome::fromCode('2324', 'Rechazado');

        $this->assertSame(CpeState::REJECTED, $outcome->status);
    }

    public function test_non_numeric_code_is_unknown_not_accepted(): void
    {
        $garbage = CdrOutcome::fromCode('foobar', 'basura');
        $leading = CdrOutcome::fromCode('0foo', 'basura');
        $blank = CdrOutcome::fromCode('', 'vacío');

        $this->assertSame(CpeState::UNKNOWN, $garbage->status);
        $this->assertSame(CpeState::UNKNOWN, $leading->status);
        $this->assertSame(CpeState::UNKNOWN, $blank->status);
        $this->assertNotSame(CpeState::ACCEPTED, $garbage->status);
    }
}
