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

    public function test_normalize_bring_decimals_to_scale_two(): void
    {
        $this->assertSame('18.50', Money::normalize('18.5'));
        $this->assertSame('18.51', Money::normalize('18.505'));
        $this->assertSame('3.46', Money::normalize('3.456'));
        $this->assertSame('-0.01', Money::normalize('-0.005'));
        $this->assertSame('0.00', Money::normalize('0'));
    }

    public function test_normalize_rejects_invalid_amounts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::normalize('abc');
    }

    public function test_weighted_average_cost_uses_on_hand_and_incoming(): void
    {
        $this->assertSame('5.00', Money::weightedAverageCost(0, '0.00', 10, '5.00'));
        $this->assertSame('10.00', Money::weightedAverageCost(10, '5.00', 10, '15.00'));
        $this->assertSame('7.50', Money::weightedAverageCost(2, '5.00', 2, '10.00'));
    }
}
