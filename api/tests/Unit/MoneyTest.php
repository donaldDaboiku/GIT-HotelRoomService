<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_basis_points_round_half_up_without_floats(): void
    {
        $quote = Money::quote(200000, 1000, 750);

        $this->assertSame(20000, $quote['service_charge_minor']);
        $this->assertSame(16500, $quote['tax_minor']);
        $this->assertSame(236500, $quote['total_minor']);
        $this->assertSame(1, Money::applyBps(1, 5000));
        $this->assertSame('₦1,000.00', Money::format(100000));
        $this->assertSame('7.5%', Money::bpsLabel(750));
    }
}
