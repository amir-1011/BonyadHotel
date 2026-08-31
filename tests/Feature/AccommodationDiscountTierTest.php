<?php

namespace Tests\Feature;

use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use App\Models\VeteranGroup;
use App\Services\AccommodationDiscountTierEngine;
use App\Services\BookingPricingService;
use App\Services\ManualBookingService;
use App\Services\VeteranPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccommodationDiscountTierTest extends TestCase
{
    use RefreshDatabase;

    private $accommodation;
    private RoomType $roomType;
    private RoomRate $roomRate;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

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
    }

    public function test_unlimited_total_quota_allows_more_than_six_discounted_nights(): void
    {
        VeteranGroup::query()
            ->where('accommodation_id', $this->accommodation->id)
            ->where('key', 'veteran_70_spouses')
            ->update(['max_nights_per_period' => 15]);

        $policy = $this->veteranPolicyFor($this->accommodation);
        $policy->clearCache($this->accommodation->id);

        $usage = $policy->checkAccommodationUsage('veteran_70_spouses', 1, 10, '9090909090');
        $this->assertTrue($usage['unlimited_total_quota'] ?? false);
        $this->assertSame(10, $usage['discounted_nights']);
    }

    public function test_tiered_accommodation_pricing_with_three_percentage_steps(): void
    {
        $this->enableTieredAccommodationDiscount('veteran_70_spouses', [
            ['night_count' => 3, 'discount_percentage' => 70],
            ['night_count' => 5, 'discount_percentage' => 30],
            ['night_count' => null, 'discount_percentage' => 10],
        ], maxNightsPerPeriod: 20, nightsPerDependent: 20);

        $policy = $this->veteranPolicyFor($this->accommodation);
        $policy->clearCache($this->accommodation->id);

        $plan = $policy->accommodationNightPlan(
            ['veteran_70_spouses'],
            1,
            10,
            '1010101010',
        );

        $this->assertSame(
            [70, 70, 70, 30, 30, 30, 30, 30, 10, 10],
            $plan['night_discounts'],
        );

        $pricing = app(BookingPricingService::class)->calculate([
            'check_in'      => now()->addDays(5)->format('Y-m-d'),
            'check_out'     => now()->addDays(15)->format('Y-m-d'),
            'guests'        => 1,
            'veteran_type'  => 'veteran_70_spouses',
            'national_id'   => '1010101010',
            'room_type_id'  => $this->roomType->id,
            'room_rate_id'  => $this->roomRate->id,
            'accommodation' => $this->accommodation,
        ]);

        // 3×70% + 5×30% + 2×10% on 1M/night, 1 guest slot
        $expectedDiscount = (3 * 700_000) + (5 * 300_000) + (2 * 100_000);
        $this->assertSame($expectedDiscount, $pricing['veteran_accommodation_discount_amount']);
        $this->assertTrue($pricing['accommodation_tiered_discount']);

        $breakdown = collect($pricing['accommodation_discount_breakdown']);
        $this->assertSame(3, $breakdown->count());
        $this->assertSame(3, $breakdown->firstWhere('discount_percentage', 70)['units']);
        $this->assertSame(5, $breakdown->firstWhere('discount_percentage', 30)['units']);
        $this->assertSame(2, $breakdown->firstWhere('discount_percentage', 10)['units']);
        $this->assertSame(2_100_000, $breakdown->firstWhere('discount_percentage', 70)['discount_amount']);
        $this->assertSame(1_500_000, $breakdown->firstWhere('discount_percentage', 30)['discount_amount']);
        $this->assertSame(200_000, $breakdown->firstWhere('discount_percentage', 10)['discount_amount']);
    }

    public function test_tiered_accommodation_continues_ladder_across_bookings_in_period(): void
    {
        $this->enableTieredAccommodationDiscount('veteran_70_spouses', [
            ['night_count' => 3, 'discount_percentage' => 70],
            ['night_count' => 5, 'discount_percentage' => 30],
            ['night_count' => null, 'discount_percentage' => 10],
        ], maxNightsPerPeriod: 20, nightsPerDependent: 20);

        $policy = $this->veteranPolicyFor($this->accommodation);
        $policy->clearCache($this->accommodation->id);
        $nationalId = '2020202020';

        $this->createPriorAccommodationBooking($nationalId, 4);

        $plan = $policy->accommodationNightPlan(
            ['veteran_70_spouses'],
            1,
            5,
            $nationalId,
        );

        // Prior 4 nights consumed tier1 (3×70%) + tier2 (1×30%)
        $this->assertSame([30, 30, 30, 30, 10], $plan['night_discounts']);
    }

    public function test_period_cap_still_limits_tiered_nights(): void
    {
        $this->enableTieredAccommodationDiscount('veteran_70_spouses', [
            ['night_count' => 3, 'discount_percentage' => 70],
            ['night_count' => null, 'discount_percentage' => 10],
        ], maxNightsPerPeriod: 3);

        $policy = $this->veteranPolicyFor($this->accommodation);
        $policy->clearCache($this->accommodation->id);

        $plan = $policy->accommodationNightPlan(
            ['veteran_70_spouses'],
            1,
            6,
            '3030303030',
        );

        $this->assertSame([70, 70, 70, 0, 0, 0], $plan['night_discounts']);
    }

    public function test_flat_accommodation_discount_unchanged_when_not_tiered(): void
    {
        $policy = $this->veteranPolicyFor($this->accommodation);
        $policy->clearCache($this->accommodation->id);

        $plan = $policy->accommodationNightPlan(
            ['veteran_70_spouses'],
            1,
            3,
            '4040404040',
        );

        $this->assertSame([70, 70, 70], $plan['night_discounts']);
    }

    public function test_manual_booking_persists_tiered_group_usage(): void
    {
        $this->enableTieredAccommodationDiscount('veteran_70_spouses', [
            ['night_count' => 2, 'discount_percentage' => 70],
            ['night_count' => null, 'discount_percentage' => 30],
        ], maxNightsPerPeriod: 10, nightsPerDependent: 10);

        $admin = User::create(['name' => 'ادمین', 'mobile' => '09000000101']);
        $admin->assignRole('super_admin');

        $checkIn = now()->addDays(3)->format('Y-m-d');
        $checkOut = now()->addDays(6)->format('Y-m-d');

        $booking = app(ManualBookingService::class)->create($this->accommodation, [
            'room_lines' => [[
                'room_type_id'     => $this->roomType->id,
                'room_rate_id'     => $this->roomRate->id,
                'adults'           => 1,
                'children_under_6' => 0,
                'guests'           => 1,
                'extra_guests'     => 0,
                'bill_full_rooms'  => false,
            ]],
            'check_in'             => $checkIn,
            'check_out'            => $checkOut,
            'guests'               => 1,
            'veteran_type'         => 'veteran_70_spouses',
            'booker_national_id'   => '5050505050',
            'payment_method'       => 'cash',
            'guest_contact_name'   => 'مهمان تست',
            'guest_contact_mobile' => '09505050505',
            'guest_details'        => [[
                'full_name'                      => 'مهمان تست',
                'national_id'                    => '5050505050',
                'mobile'                         => '09505050505',
                'relation'                       => 'خود',
                'excluded_from_veteran_discount' => false,
                'services'                       => [],
            ]],
        ], $admin);

        $this->assertSame(['veteran_70_spouses' => 3], $booking->veteran_accommodation_group_usage);
        $this->assertGreaterThan(0, $booking->discount_amount);
    }

    public function test_tiered_breakdown_shows_each_step_when_period_cap_partial(): void
    {
        $this->enableTieredAccommodationDiscount('veteran_70_spouses', [
            ['night_count' => 3, 'discount_percentage' => 70],
            ['night_count' => 5, 'discount_percentage' => 30],
            ['night_count' => null, 'discount_percentage' => 10],
        ], maxNightsPerPeriod: 6, nightsPerDependent: 6);

        $policy = $this->veteranPolicyFor($this->accommodation);
        $policy->clearCache($this->accommodation->id);

        $pricing = app(BookingPricingService::class)->calculate([
            'check_in'      => now()->addDays(5)->format('Y-m-d'),
            'check_out'     => now()->addDays(25)->format('Y-m-d'),
            'guests'        => 1,
            'veteran_type'  => 'veteran_70_spouses',
            'national_id'   => '8080808080',
            'room_type_id'  => $this->roomType->id,
            'room_rate_id'  => $this->roomRate->id,
            'accommodation' => $this->accommodation,
        ]);

        $this->assertSame(6, $pricing['veteran_discount_nights']);
        $this->assertSame(20, $pricing['nights']);
        $this->assertTrue($pricing['accommodation_tiered_discount']);

        $breakdown = collect($pricing['accommodation_discount_breakdown']);
        $this->assertSame(3, $breakdown->firstWhere('discount_percentage', 70)['units']);
        $this->assertSame(3, $breakdown->firstWhere('discount_percentage', 30)['units']);
        $this->assertNull($breakdown->firstWhere('discount_percentage', 10));
    }

    public function test_tiered_fixed_pay_same_for_different_room_prices(): void
    {
        $this->enableTieredAccommodationDiscount('veteran_70_spouses', [
            ['type' => 'fixed_pay', 'night_count' => 3, 'pay_amount' => 200_000],
            ['type' => 'percentage', 'night_count' => null, 'discount_percentage' => 50],
        ], maxNightsPerPeriod: 20, nightsPerDependent: 20);

        $policy = $this->veteranPolicyFor($this->accommodation);
        $policy->clearCache($this->accommodation->id);

        $plan = $policy->accommodationNightPlan(
            ['veteran_70_spouses'],
            1,
            5,
            '7070707070',
            referenceNightPrice: 1_000_000,
        );

        $this->assertSame(
            ['fixed_pay', 'fixed_pay', 'fixed_pay', 'percentage', 'percentage'],
            array_map(
                fn (array $tier) => $tier['type'] ?? 'percentage',
                $plan['night_tiers'],
            ),
        );

        $pricing = app(BookingPricingService::class)->calculate([
            'check_in'      => now()->addDays(5)->format('Y-m-d'),
            'check_out'     => now()->addDays(10)->format('Y-m-d'),
            'guests'        => 1,
            'veteran_type'  => 'veteran_70_spouses',
            'national_id'   => '7070707070',
            'room_type_id'  => $this->roomType->id,
            'room_rate_id'  => $this->roomRate->id,
            'accommodation' => $this->accommodation,
        ]);

        $this->assertSame(3_400_000, $pricing['veteran_accommodation_discount_amount']);
        $this->assertTrue($pricing['accommodation_tiered_discount']);

        $breakdown = collect($pricing['accommodation_discount_breakdown']);
        $this->assertSame(3, $breakdown->firstWhere('type', 'fixed_pay')['units']);
        $this->assertSame(200_000, $breakdown->firstWhere('type', 'fixed_pay')['pay_amount']);
        $this->assertSame(2, $breakdown->firstWhere('type', 'percentage')['units']);
        $this->assertSame(50, $breakdown->firstWhere('type', 'percentage')['discount_percentage']);
    }

    public function test_tiered_fixed_pay_applies_to_eligible_room_charge_with_two_guest_slots(): void
    {
        $this->enableTieredAccommodationDiscount('veteran_70_spouses', [
            ['type' => 'fixed_pay', 'night_count' => 2, 'pay_amount' => 1_000_000],
            ['type' => 'percentage', 'night_count' => null, 'discount_percentage' => 10],
        ], maxNightsPerPeriod: 20, nightsPerDependent: 20);

        $policy = $this->veteranPolicyFor($this->accommodation);
        $policy->clearCache($this->accommodation->id);

        $pricing = app(BookingPricingService::class)->calculate([
            'check_in'      => now()->addDays(5)->format('Y-m-d'),
            'check_out'     => now()->addDays(15)->format('Y-m-d'),
            'guests'        => 2,
            'veteran_type'  => 'veteran_70_spouses',
            'national_id'   => '7171717171',
            'room_type_id'  => $this->roomType->id,
            'room_rate_id'  => $this->roomRate->id,
            'accommodation' => $this->accommodation,
            'per_guest_slots' => [
                ['is_child' => false, 'veteran_eligible' => true, 'manual_discount_pct' => 0],
                ['is_child' => false, 'veteran_eligible' => true, 'manual_discount_pct' => 0],
            ],
        ]);

        $this->assertSame(3_600_000, $pricing['veteran_accommodation_discount_amount']);

        $breakdown = collect($pricing['accommodation_discount_breakdown']);
        $this->assertSame(2, $breakdown->firstWhere('type', 'fixed_pay')['units']);
        $this->assertSame(8, $breakdown->firstWhere('type', 'percentage')['units']);
        $this->assertSame(2_000_000, $breakdown->firstWhere('type', 'fixed_pay')['discount_amount']);
        $this->assertSame(1_600_000, $breakdown->firstWhere('type', 'percentage')['discount_amount']);
    }

    public function test_user_example_veteran_70_spouses_six_month_ladder(): void
    {
        VeteranGroup::query()
            ->where('accommodation_id', $this->accommodation->id)
            ->where('key', 'veteran_70_spouses')
            ->update([
                'period_months'         => 6,
                'max_nights_per_period' => 50,
                'nights_per_dependent'  => 50,
            ]);

        $this->enableTieredAccommodationDiscount('veteran_70_spouses', [
            ['night_count' => 3, 'discount_percentage' => 70],
            ['night_count' => 5, 'discount_percentage' => 30],
            ['night_count' => null, 'discount_percentage' => 10],
        ], maxNightsPerPeriod: 50);

        $policy = $this->veteranPolicyFor($this->accommodation);
        $policy->clearCache($this->accommodation->id);

        $plan = $policy->accommodationNightPlan(
            ['veteran_70_spouses'],
            1,
            12,
            '6060606060',
        );

        $this->assertSame(
            [70, 70, 70, 30, 30, 30, 30, 30, 10, 10, 10, 10],
            $plan['night_discounts'],
        );
    }

    public function test_period_cap_partial_discount_matches_manual_booking_path(): void
    {
        $checkIn = now()->addDays(5)->format('Y-m-d');
        $checkOut = now()->addDays(9)->format('Y-m-d');

        $pricing = app(BookingPricingService::class)->calculate([
            'check_in'      => $checkIn,
            'check_out'     => $checkOut,
            'guests'        => 1,
            'veteran_type'  => 'veteran_70_spouses',
            'national_id'   => '9988776655',
            'accommodation' => $this->accommodation,
            'per_guest_slots' => [[
                'is_child' => false,
                'veteran_eligible' => true,
                'manual_discount_pct' => 0,
            ]],
        ]);

        $this->assertSame(1_900_000, $pricing['total_price']);
        $this->assertSame(3, $pricing['veteran_discount_nights']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $tiers
     */
    private function enableTieredAccommodationDiscount(
        string $groupKey,
        array $tiers,
        ?int $maxNightsPerPeriod = null,
        ?int $nightsPerDependent = null,
    ): void {
        $payload = AccommodationDiscountTierEngine::groupRowToPersistence([
            'accommodation_discount'            => 70,
            'use_tiered_accommodation_discount' => true,
            'accommodation_discount_tiers'      => $tiers,
        ]);

        $update = [
            'use_tiered_accommodation_discount' => $payload['use_tiered_accommodation_discount'],
            'accommodation_discount_tiers'      => $payload['accommodation_discount_tiers'],
            'accommodation_discount'            => $payload['accommodation_discount'],
        ];

        if ($maxNightsPerPeriod !== null) {
            $update['max_nights_per_period'] = $maxNightsPerPeriod;
        }

        if ($nightsPerDependent !== null) {
            $update['nights_per_dependent'] = $nightsPerDependent;
        }

        VeteranGroup::query()
            ->where('accommodation_id', $this->accommodation->id)
            ->where('key', $groupKey)
            ->update($update);
    }

    private function createPriorAccommodationBooking(string $nationalId, int $nights): void
    {
        $admin = User::create([
            'name'   => 'ادمین ' . $nationalId,
            'mobile' => '0912' . substr($nationalId, 0, 7),
        ]);
        $admin->assignRole('super_admin');

        app(ManualBookingService::class)->create($this->accommodation, [
            'room_lines' => [[
                'room_type_id'     => $this->roomType->id,
                'room_rate_id'     => $this->roomRate->id,
                'adults'           => 1,
                'children_under_6' => 0,
                'guests'           => 1,
                'extra_guests'     => 0,
                'bill_full_rooms'  => false,
            ]],
            'check_in'             => now()->subDays(10)->format('Y-m-d'),
            'check_out'            => now()->subDays(10 - $nights)->format('Y-m-d'),
            'guests'               => 1,
            'veteran_type'         => 'veteran_70_spouses',
            'booker_national_id'   => $nationalId,
            'payment_method'       => 'cash',
            'guest_contact_name'   => 'رزرو قبلی',
            'guest_contact_mobile' => '09202020202',
            'guest_details'        => [[
                'full_name'                      => 'رزرو قبلی',
                'national_id'                    => $nationalId,
                'mobile'                         => '09202020202',
                'relation'                       => 'خود',
                'excluded_from_veteran_discount' => false,
                'services'                       => [],
            ]],
        ], $admin);
    }
}
