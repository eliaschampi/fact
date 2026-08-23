<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    public function test_round_trips_cents(): void
    {
        $this->assertSame(12345, Money::toCents('123.45'));
        $this->assertSame('123.45', Money::fromCents(12345));
        $this->assertSame(-50, Money::toCents('-0.50'));
    }

    public function test_rejects_invalid_amounts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::toCents('12.345');
    }
}
