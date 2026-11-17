<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    #[Test]
    public function it_round_trips_nine_decimal_strings(): void
    {
        $nanos = Money::fromDecimalString('0.373875000');

        $this->assertSame(373875000, $nanos);
        $this->assertSame('0.373875000', Money::toDecimalString($nanos));
    }

    #[Test]
    public function it_applies_markup_rounding_up(): void
    {
        // 1 nanodollar * 1.25 cannot be exact → rounds up to 2.
        $this->assertSame(2, Money::withMarkup(1, 25));
        $this->assertSame(125, Money::withMarkup(100, 25));
    }

    #[Test]
    public function docs_worked_example_maximum_cost(): void
    {
        $cost = Money::costForTokens(115, '5.00')
            + Money::costForTokens(1530, '10.00')
            + Money::costForTokens(6450, '40.00')
            + Money::costForTokens(8000, '12.50');

        $this->assertSame(Money::fromDecimalString('0.373875000'), $cost);
    }
}
