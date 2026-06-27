<?php

namespace Tests\Unit;

use App\Services\ServiceDiscountTierEngine;
use PHPUnit\Framework\TestCase;

class ServiceDiscountTierEngineTest extends TestCase
{
    public function test_calculate_line_returns_breakdown(): void
    {
        $tiers = [
            ['type' => ServiceDiscountTierEngine::TYPE_FREE, 'session_count' => 2],
            ['type' => ServiceDiscountTierEngine::TYPE_FIXED_PAY, 'session_count' => 1, 'pay_amount' => 100_000],
            ['type' => ServiceDiscountTierEngine::TYPE_PERCENTAGE, 'session_count' => null, 'discount_percentage' => 65],
        ];

        $result = ServiceDiscountTierEngine::calculateLine(500_000, 4, 0, 0, $tiers);

        $this->assertCount(3, $result['discount_breakdown']);
        $this->assertSame(2, $result['discount_breakdown'][0]['units']);
        $this->assertSame('free', $result['discount_breakdown'][0]['type']);
    }

    public function test_three_tier_pool_example(): void
    {
        $tiers = [
            ['type' => 'free', 'session_count' => 3],
            ['type' => 'fixed_pay', 'session_count' => 2, 'pay_amount' => 100_000],
            ['type' => 'percentage', 'session_count' => null, 'discount_percentage' => 65],
        ];

        $result = ServiceDiscountTierEngine::calculateLine(500_000, 6, 0, 0, $tiers);

        $this->assertSame(3, $result['free_units']);
        // 3×500k free + 2×400k fixed + 1×325k pct = 2,625,000 discount
        $this->assertSame(2_625_000, $result['discount_amount']);
        $this->assertSame(375_000, 6 * 500_000 - $result['discount_amount']);
    }

    public function test_fixed_pay_is_independent_of_list_price(): void
    {
        $tiers = [
            ['type' => 'fixed_pay', 'session_count' => null, 'pay_amount' => 100_000],
        ];

        $cheap = ServiceDiscountTierEngine::calculateLine(500_000, 1, 0, 0, $tiers);
        $expensive = ServiceDiscountTierEngine::calculateLine(600_000, 1, 0, 0, $tiers);

        $this->assertSame(400_000, $cheap['discount_amount']);
        $this->assertSame(100_000, 500_000 - $cheap['discount_amount']);
        $this->assertSame(500_000, $expensive['discount_amount']);
        $this->assertSame(100_000, 600_000 - $expensive['discount_amount']);
    }

    public function test_weekly_offset_applies_correct_tier(): void
    {
        $tiers = [
            ['type' => 'free', 'session_count' => 3],
            ['type' => 'fixed_pay', 'session_count' => 2, 'pay_amount' => 100_000],
            ['type' => 'percentage', 'session_count' => null, 'discount_percentage' => 65],
        ];

        // Prior week used 4 sessions (3 free + 1 fixed) → sessions 5 fixed, 6 at 65%
        $result = ServiceDiscountTierEngine::calculateLine(500_000, 2, 4, 0, $tiers);

        $this->assertSame(0, $result['free_units']);
        $this->assertSame(725_000, $result['discount_amount']); // 400k + 325k
    }

    public function test_in_booking_offset_shares_ladder(): void
    {
        $tiers = [
            ['type' => 'free', 'session_count' => 2],
            ['type' => 'percentage', 'session_count' => null, 'discount_percentage' => 50],
        ];

        $firstLine = ServiceDiscountTierEngine::calculateLine(200, 2, 0, 0, $tiers);
        $secondLine = ServiceDiscountTierEngine::calculateLine(200, 1, 0, 2, $tiers);

        $this->assertSame(2, $firstLine['free_units']);
        $this->assertSame(400, $firstLine['discount_amount']);
        $this->assertSame(0, $secondLine['free_units']);
        $this->assertSame(100, $secondLine['discount_amount']);
    }

    public function test_tiers_from_legacy_rule(): void
    {
        $tiers = ServiceDiscountTierEngine::tiersFromLegacyRule([
            'free_sessions_eligible' => true,
            'weekly_free_sessions'   => 3,
            'discount_percentage'    => 65,
        ]);

        $this->assertCount(2, $tiers);
        $this->assertSame('free', $tiers[0]['type']);
        $this->assertSame(3, $tiers[0]['session_count']);
        $this->assertSame('percentage', $tiers[1]['type']);
        $this->assertSame(65, $tiers[1]['discount_percentage']);
    }

    public function test_finite_tier_ladder_does_not_repeat_last_tier_forever(): void
    {
        $tiers = [
            ['type' => ServiceDiscountTierEngine::TYPE_FREE, 'session_count' => 1],
            ['type' => ServiceDiscountTierEngine::TYPE_FIXED_PAY, 'session_count' => 2, 'pay_amount' => 50_000],
        ];

        $this->assertSame(
            ServiceDiscountTierEngine::TYPE_FREE,
            ServiceDiscountTierEngine::tierForSessionIndex(1, $tiers)['type'],
        );
        $this->assertSame(
            50_000,
            ServiceDiscountTierEngine::tierForSessionIndex(2, $tiers)['pay_amount'],
        );
        $this->assertSame(
            50_000,
            ServiceDiscountTierEngine::tierForSessionIndex(3, $tiers)['pay_amount'],
        );
        $this->assertSame(
            0,
            ServiceDiscountTierEngine::tierForSessionIndex(4, $tiers)['discount_percentage'],
        );

        $result = ServiceDiscountTierEngine::calculateLine(500_000, 10, 0, 0, $tiers);

        $this->assertSame(1, $result['free_units']);
        $this->assertSame(1_400_000, $result['discount_amount']);
        $this->assertSame(3_600_000, 10 * 500_000 - $result['discount_amount']);
    }
}
