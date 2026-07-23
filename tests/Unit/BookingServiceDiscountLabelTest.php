<?php

namespace Tests\Unit;

use App\Models\BookingService;
use PHPUnit\Framework\TestCase;

class BookingServiceDiscountLabelTest extends TestCase
{
    public function test_describes_weekly_free_sessions_instead_of_matrix_percentage(): void
    {
        $label = BookingService::describeDiscountFromAttributes([
            'discount_amount'             => 300_000,
            'excluded_from_veteran_quota' => false,
            'quantity'                    => 3,
            'unit_price'                  => 100_000,
            'free_units'                  => 3,
            'discount_percentage'         => 65,
        ]);

        $this->assertSame('3 جلسه رایگان', $label);
        $this->assertStringNotContainsString('65', $label);
    }

    public function test_describes_mixed_free_sessions_and_paid_percentage(): void
    {
        $label = BookingService::describeDiscountFromAttributes([
            'discount_amount'             => 430_000,
            'excluded_from_veteran_quota' => false,
            'quantity'                    => 5,
            'unit_price'                  => 100_000,
            'free_units'                  => 3,
            'discount_percentage'         => 65,
        ]);

        $this->assertSame('3 جلسه رایگان · 65٪ روی جلسات غیررایگان', $label);
    }

    public function test_describes_manual_discount_for_excluded_service(): void
    {
        $label = BookingService::describeDiscountFromAttributes([
            'discount_amount'             => 50_000,
            'excluded_from_veteran_quota' => true,
            'manual_discount_percentage'  => 25,
            'quantity'                    => 1,
            'unit_price'                  => 200_000,
            'free_units'                  => 0,
            'discount_percentage'         => 0,
        ]);

        $this->assertSame('25٪ تخفیف دستی', $label);
    }

    public function test_describes_percentage_only_when_no_free_sessions(): void
    {
        $label = BookingService::describeDiscountFromAttributes([
            'discount_amount'             => 130_000,
            'excluded_from_veteran_quota' => false,
            'quantity'                    => 2,
            'unit_price'                  => 100_000,
            'free_units'                  => 0,
            'discount_percentage'         => 65,
        ]);

        $this->assertSame('65٪ ایثارگری', $label);
    }
}
