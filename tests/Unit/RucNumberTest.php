<?php

namespace Tests\Unit;

use App\Support\RucNumber;
use Tests\TestCase;

class RucNumberTest extends TestCase
{
    public function test_accepts_valid_ruc_check_digits(): void
    {
        $this->assertTrue(RucNumber::isValid('20000000001'));
        $this->assertTrue(RucNumber::isValid('20512333797'));
    }

    public function test_rejects_invalid_check_digit(): void
    {
        $this->assertFalse(RucNumber::isValid('20512333798'));
        $this->assertFalse(RucNumber::isValid('20000000002'));
    }

    public function test_rejects_non_eleven_digit_input(): void
    {
        $this->assertFalse(RucNumber::isValid('12345'));
        $this->assertFalse(RucNumber::isValid('2051233379712'));
        $this->assertFalse(RucNumber::isValid('20A1233379*'));
    }
}
