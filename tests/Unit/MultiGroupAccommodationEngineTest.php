<?php

namespace Tests\Unit;

use App\Services\MultiGroupAccommodationEngine;
use PHPUnit\Framework\TestCase;

class MultiGroupAccommodationEngineTest extends TestCase
{
    public function test_allocates_primary_group_before_secondary(): void
    {
        $result = MultiGroupAccommodationEngine::allocateNights(5, [
            [
                'key'                    => 'veteran_70_spouses',
                'accommodation_discount' => 70,
                'remaining_period'       => 3,
                'remaining_total'        => 6,
            ],
            [
                'key'                    => 'martyr_children',
                'accommodation_discount' => 50,
                'remaining_period'       => 3,
                'remaining_total'        => 6,
            ],
        ]);

        $this->assertSame([70, 70, 70, 50, 50], $result['night_discounts']);
        $this->assertSame(3, $result['group_usage']['veteran_70_spouses']);
        $this->assertSame(2, $result['group_usage']['martyr_children']);
    }

    public function test_secondary_continues_when_primary_period_exhausted(): void
    {
        $result = MultiGroupAccommodationEngine::allocateNights(4, [
            [
                'key'                    => 'veteran_70_spouses',
                'accommodation_discount' => 70,
                'remaining_period'       => 0,
                'remaining_total'        => 6,
            ],
            [
                'key'                    => 'martyr_children',
                'accommodation_discount' => 50,
                'remaining_period'       => 3,
                'remaining_total'        => 6,
            ],
        ]);

        $this->assertSame([50, 50, 50, 0], $result['night_discounts']);
        $this->assertSame(3, $result['group_usage']['martyr_children']);
        $this->assertArrayNotHasKey('veteran_70_spouses', $result['group_usage']);
    }

    public function test_partial_primary_then_secondary_then_full_rate(): void
    {
        $result = MultiGroupAccommodationEngine::allocateNights(6, [
            [
                'key'                    => 'veteran_70_spouses',
                'accommodation_discount' => 70,
                'remaining_period'       => 1,
                'remaining_total'        => 6,
            ],
            [
                'key'                    => 'martyr_children',
                'accommodation_discount' => 50,
                'remaining_period'       => 2,
                'remaining_total'        => 6,
            ],
        ]);

        $this->assertSame([70, 50, 50, 0, 0, 0], $result['night_discounts']);
        $this->assertSame(1, $result['group_usage']['veteran_70_spouses']);
        $this->assertSame(2, $result['group_usage']['martyr_children']);
    }
}
