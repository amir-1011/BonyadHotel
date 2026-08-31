<?php

namespace Tests\Feature;

use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\VeteranGroup;
use App\Services\AccommodationDiscountTierEngine;
use App\Services\BookingPricingService;
use App\Services\MultiGroupAccommodationEngine;
use App\Services\VeteranPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiGroupTieredAccommodationTest extends TestCase
{
    use RefreshDatabase;

    private $accommodation;
    private RoomType $roomType;
    private RoomRate $roomRate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accommodation = $this->createTestAccommodation();
        $this->roomType = RoomType::create([
            'accommodation_id' => $this->accommodation->id,
            'name'             => 'اتاق تست',
            'capacity'         => 2,
            'quantity'         => 5,
            'is_active'        => true,
        ]);
        $this->roomRate = RoomRate::create([
            'room_type_id'    => $this->roomType->id,
            'name'            => 'نرخ عادی',
            'price_per_night' => 1_000_000,
            'is_active'       => true,
        ]);

        $this->enableDualTieredAccommodationDiscounts();
    }

    public function test_dual_group_picks_higher_discount_per_night_across_tiers(): void
    {
        $result = MultiGroupAccommodationEngine::allocateNights(6, $this->dualGroupPayload());

        $this->assertSame([70, 70, 70, 50, 50, 50], $result['night_discounts']);
        $this->assertSame(3, $result['group_usage']['veteran_70_spouses']);
        $this->assertSame(3, $result['group_usage']['martyr_children']);
    }

    public function test_dual_group_tiered_pricing_uses_best_group_each_night(): void
    {
        $policy = $this->veteranPolicyFor($this->accommodation);
        $policy->clearCache($this->accommodation->id);

        $plan = $policy->accommodationNightPlan(
            ['veteran_70_spouses', 'martyr_children'],
            1,
            6,
            '3030303030',
        );

        $this->assertSame([70, 70, 70, 50, 50, 50], $plan['night_discounts']);
        $this->assertSame(3, $plan['group_usage']['veteran_70_spouses']);
        $this->assertSame(3, $plan['group_usage']['martyr_children']);
    }

    public function test_dual_group_tiered_booking_pricing_breakdown_splits_by_group_and_tier(): void
    {
        $pricing = app(BookingPricingService::class)->calculate([
            'check_in'      => now()->addDays(5)->format('Y-m-d'),
            'check_out'     => now()->addDays(11)->format('Y-m-d'),
            'guests'        => 1,
            'veteran_types' => ['veteran_70_spouses', 'martyr_children'],
            'national_id'   => '4040404040',
            'room_type_id'  => $this->roomType->id,
            'room_rate_id'  => $this->roomRate->id,
            'accommodation' => $this->accommodation,
        ]);

        $this->assertTrue($pricing['accommodation_tiered_discount']);
        $this->assertSame(6, $pricing['veteran_discount_nights']);

        $breakdown = collect($pricing['accommodation_discount_breakdown']);
        $this->assertSame(3, $breakdown->first(fn ($row) => ($row['veteran_group_key'] ?? '') === 'veteran_70_spouses'
            && (int) ($row['discount_percentage'] ?? 0) === 70)['units']);
        $this->assertSame(3, $breakdown->first(fn ($row) => ($row['veteran_group_key'] ?? '') === 'martyr_children'
            && (int) ($row['discount_percentage'] ?? 0) === 50)['units']);
        $this->assertNull($breakdown->first(fn ($row) => ($row['veteran_group_key'] ?? '') === 'martyr_children'
            && (int) ($row['discount_percentage'] ?? 0) === 40));
    }

    public function test_cross_booking_continues_each_groups_tier_ladder_independently(): void
    {
        $policy = $this->veteranPolicyFor($this->accommodation);
        $policy->clearCache($this->accommodation->id);
        $nationalId = '5050505050';

        $prior = $policy->accommodationNightPlan(
            ['veteran_70_spouses', 'martyr_children'],
            1,
            4,
            $nationalId,
        );
        $this->assertSame([70, 70, 70, 50], $prior['night_discounts']);

        $this->seedPriorDualAccommodationUsage($nationalId, $prior['group_usage']);

        $next = $policy->accommodationNightPlan(
            ['veteran_70_spouses', 'martyr_children'],
            1,
            3,
            $nationalId,
        );

        $this->assertSame([50, 50, 40], $next['night_discounts']);
        $this->assertArrayNotHasKey('veteran_70_spouses', $next['group_usage']);
        $this->assertSame(3, $next['group_usage']['martyr_children']);
    }

    public function test_flat_dual_group_still_prefers_higher_percentage_until_quota_exhausted(): void
    {
        $result = MultiGroupAccommodationEngine::allocateNights(5, [
            [
                'key'                    => 'veteran_70_spouses',
                'accommodation_discount' => 70,
                'priority'               => 70,
                'remaining_period'       => 3,
                'remaining_total'        => PHP_INT_MAX,
            ],
            [
                'key'                    => 'martyr_children',
                'accommodation_discount' => 50,
                'priority'               => 50,
                'remaining_period'       => 3,
                'remaining_total'        => PHP_INT_MAX,
            ],
        ]);

        $this->assertSame([70, 70, 70, 50, 50], $result['night_discounts']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dualGroupPayload(): array
    {
        return [
            [
                'key'                               => 'veteran_70_spouses',
                'accommodation_discount'            => 70,
                'priority'                          => 70,
                'use_tiered_accommodation_discount' => true,
                'accommodation_discount_tiers'      => [
                    ['night_count' => 3, 'discount_percentage' => 70],
                    ['night_count' => null, 'discount_percentage' => 30],
                ],
                'used_in_period'   => 0,
                'remaining_period' => 10,
                'remaining_total'  => PHP_INT_MAX,
            ],
            [
                'key'                               => 'martyr_children',
                'accommodation_discount'            => 50,
                'priority'                          => 50,
                'use_tiered_accommodation_discount' => true,
                'accommodation_discount_tiers'      => [
                    ['night_count' => 3, 'discount_percentage' => 50],
                    ['night_count' => null, 'discount_percentage' => 40],
                ],
                'used_in_period'   => 0,
                'remaining_period' => 10,
                'remaining_total'  => PHP_INT_MAX,
            ],
        ];
    }

    private function enableDualTieredAccommodationDiscounts(): void
    {
        foreach ([
            'veteran_70_spouses' => [
                ['night_count' => 3, 'discount_percentage' => 70],
                ['night_count' => null, 'discount_percentage' => 30],
            ],
            'martyr_children' => [
                ['night_count' => 3, 'discount_percentage' => 50],
                ['night_count' => null, 'discount_percentage' => 40],
            ],
        ] as $groupKey => $tiers) {
            $payload = AccommodationDiscountTierEngine::groupRowToPersistence([
                'accommodation_discount'            => 70,
                'use_tiered_accommodation_discount' => true,
                'accommodation_discount_tiers'      => $tiers,
            ]);

            VeteranGroup::query()
                ->where('accommodation_id', $this->accommodation->id)
                ->where('key', $groupKey)
                ->update(array_merge($payload, [
                    'max_nights_per_period' => 10,
                ]));
        }

        $this->veteranPolicyFor($this->accommodation)->clearCache($this->accommodation->id);
    }

    /**
     * @param  array<string, int>  $groupUsage
     */
    private function seedPriorDualAccommodationUsage(string $nationalId, array $groupUsage): void
    {
        $guest = \App\Models\User::firstOrCreate(
            ['national_id' => $nationalId],
            ['name' => 'مهمان قبلی', 'mobile' => '09505050505'],
        );

        \App\Models\Booking::create([
            'user_id'                          => $guest->id,
            'accommodation_id'               => $this->accommodation->id,
            'veteran_type_applied'             => 'veteran_70_spouses',
            'secondary_veteran_type_applied'   => 'martyr_children',
            'veteran_accommodation_group_usage'=> $groupUsage,
            'booking_source'                   => 'manual',
            'check_in'                         => now()->subDays(10)->format('Y-m-d'),
            'check_out'                        => now()->subDays(6)->format('Y-m-d'),
            'nights'                           => array_sum($groupUsage),
            'guests'                           => 1,
            'base_price'                       => 4_000_000,
            'discount_amount'                  => 0,
            'total_price'                      => 4_000_000,
            'status'                           => 'confirmed',
            'tracking_code'                    => strtoupper(substr(md5(uniqid($nationalId, true)), 0, 10)),
        ]);

        $booking = \App\Models\Booking::query()->where('user_id', $guest->id)->latest('id')->first();
        \App\Models\BookingGuestDetail::create([
            'booking_id'  => $booking->id,
            'full_name'   => 'مهمان قبلی',
            'national_id' => $nationalId,
            'mobile'      => '09505050505',
            'sort_order'  => 0,
        ]);
    }
}
