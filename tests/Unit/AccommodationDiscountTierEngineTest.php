<?php

namespace Tests\Unit;

use App\Services\AccommodationDiscountTierEngine;
use App\Services\MultiGroupAccommodationEngine;
use PHPUnit\Framework\TestCase;

class AccommodationDiscountTierEngineTest extends TestCase
{
    public function test_tier_for_night_index_follows_ladder(): void
    {
        $tiers = [
            ['night_count' => 3, 'discount_percentage' => 70],
            ['night_count' => 5, 'discount_percentage' => 30],
            ['night_count' => null, 'discount_percentage' => 10],
        ];

        $this->assertSame(70, AccommodationDiscountTierEngine::discountForNightIndex(1, $tiers));
        $this->assertSame(70, AccommodationDiscountTierEngine::discountForNightIndex(3, $tiers));
        $this->assertSame(30, AccommodationDiscountTierEngine::discountForNightIndex(4, $tiers));
        $this->assertSame(30, AccommodationDiscountTierEngine::discountForNightIndex(8, $tiers));
        $this->assertSame(10, AccommodationDiscountTierEngine::discountForNightIndex(9, $tiers));
        $this->assertSame(10, AccommodationDiscountTierEngine::discountForNightIndex(99, $tiers));
    }

    public function test_fixed_pay_is_independent_of_list_price(): void
    {
        $tiers = [
            ['type' => 'fixed_pay', 'night_count' => null, 'pay_amount' => 200_000],
        ];

        $this->assertSame(800_000, AccommodationDiscountTierEngine::unitDiscount(1_000_000, $tiers[0]));
        $this->assertSame(300_000, AccommodationDiscountTierEngine::unitDiscount(500_000, $tiers[0]));
    }

    public function test_tier_breakdown_hint_describes_fixed_pay_and_percentage(): void
    {
        $this->assertSame(
            '2 شب · مبلغ ثابت 1,000,000 ریال',
            AccommodationDiscountTierEngine::tierBreakdownHint([
                'type' => 'fixed_pay',
                'units' => 2,
                'pay_amount' => 1_000_000,
            ]),
        );

        $this->assertSame(
            '3 شب · 10٪ تخفیف',
            AccommodationDiscountTierEngine::tierBreakdownHint([
                'type' => 'percentage',
                'units' => 3,
                'discount_percentage' => 10,
            ]),
        );
    }

    public function test_calculate_night_discounts_respects_eligible_cap(): void
    {
        $tiers = [
            ['night_count' => 2, 'discount_percentage' => 70],
            ['night_count' => null, 'discount_percentage' => 30],
        ];

        $result = AccommodationDiscountTierEngine::calculateNightDiscounts(5, 0, 3, $tiers);

        $this->assertSame([70, 70, 30, 0, 0], $result);
    }

    public function test_multi_group_engine_picks_best_discount_per_night(): void
    {
        $result = MultiGroupAccommodationEngine::allocateNights(5, [
            [
                'key'                               => 'veteran_70_spouses',
                'accommodation_discount'            => 70,
                'priority'                          => 70,
                'use_tiered_accommodation_discount' => true,
                'accommodation_discount_tiers'      => [
                    ['night_count' => 2, 'discount_percentage' => 70],
                    ['night_count' => null, 'discount_percentage' => 30],
                ],
                'used_in_period'   => 1,
                'remaining_period' => 4,
                'remaining_total'  => PHP_INT_MAX,
            ],
            [
                'key'                    => 'martyr_children',
                'accommodation_discount' => 50,
                'priority'               => 50,
                'remaining_period'       => 3,
                'remaining_total'        => PHP_INT_MAX,
            ],
        ]);

        // Night 1: primary 70% vs secondary 50% → 70%
        // Night 2-4: primary 30% vs secondary 50% → 50%
        // Night 5: only primary 30% remains within quota
        $this->assertSame([70, 50, 50, 50, 30], $result['night_discounts']);
        $this->assertSame(2, $result['group_usage']['veteran_70_spouses']);
        $this->assertSame(3, $result['group_usage']['martyr_children']);
    }
}
