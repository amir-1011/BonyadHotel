<?php

namespace Tests\Feature;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\BookingGuestDetail;
use App\Models\BookingService;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Models\VeteranGroup;
use App\Services\BookingPricingService;
use App\Services\ManualBookingService;
use App\Services\VeteranPolicyService;
use Carbon\Carbon;
use App\Services\VeteranPolicyProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests for veteran policy compliance in manual booking flow.
 *
 * Covers rules from the official policy document (PDF):
 * - Accommodation discount per group (70%, 50%, 40%)
 * - Pool/gym/sports hall: 3 free sessions per week for veteran_70
 * - Period usage cap: 3 nights per 6 months
 * - Total quota: 6 nights × number of dependents
 * - recalculateTotals must NOT clamp free-session discount to min_discount
 * - Legacy key normalization
 * - All service discounts per group (conference 40%, reception 50%/20%)
 */
class VeteranPolicyBookingTest extends TestCase
{
    use RefreshDatabase;

    private Accommodation $accommodation;
    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $this->adminUser = User::create([
            'name'   => 'ادمین تست',
            'mobile' => '09000000001',
        ]);
        $this->adminUser->assignRole('super_admin');

        // Province → City → Accommodation (city_id is NOT NULL)
        $provinceId = DB::table('provinces')->insertGetId(['name' => 'استان تست', 'created_at' => now(), 'updated_at' => now()]);
        $cityId = DB::table('cities')->insertGetId(['province_id' => $provinceId, 'name' => 'شهر تست', 'created_at' => now(), 'updated_at' => now()]);

        $this->accommodation = Accommodation::create([
            'city_id'         => $cityId,
            'name'            => 'اقامتگاه تست',
            'price_per_night' => 1_000_000,
            'capacity'        => 10,
            'rooms'           => 5,
            'is_active'       => true,
        ]);

        app(VeteranPolicyProvisioner::class)->seedForAccommodation($this->accommodation);
    }

    // ──────────────────────────────────────────────────────
    // 1.  Accommodation discount per group
    // ──────────────────────────────────────────────────────

    public function test_veteran70_gets_70_percent_accommodation_discount(): void
    {
        $pricing = $this->calculatePricing([
            'veteran_type' => 'veteran_70_spouses',
            'nights'       => 2,
        ]);

        $this->assertSame(70, $pricing['accommodation_discount_percentage']);
        $this->assertSame(2_000_000, $pricing['room_subtotal']);
        $this->assertSame(1_400_000, $pricing['discount_amount']);
        $this->assertSame(600_000, $pricing['total_price']);
    }

    public function test_veteran50_69_gets_50_percent_accommodation_discount(): void
    {
        $pricing = $this->calculatePricing([
            'veteran_type' => 'veteran_50_69_dependents',
            'nights'       => 2,
        ]);

        $this->assertSame(50, $pricing['accommodation_discount_percentage']);
        $this->assertSame(1_000_000, $pricing['total_price']);
    }

    public function test_veteran5_49_gets_40_percent_accommodation_discount(): void
    {
        $pricing = $this->calculatePricing([
            'veteran_type' => 'veteran_25_49_dependents',
            'nights'       => 2,
        ]);

        $this->assertSame(40, $pricing['accommodation_discount_percentage']);
        $this->assertSame(1_200_000, $pricing['total_price']);
    }

    public function test_martyr_parents_gets_70_percent_accommodation_discount(): void
    {
        $pricing = $this->calculatePricing([
            'veteran_type' => 'martyr_parents_dependents',
            'nights'       => 2,
        ]);

        $this->assertSame(70, $pricing['accommodation_discount_percentage']);
    }

    public function test_martyr_children_gets_50_percent_accommodation_discount(): void
    {
        $pricing = $this->calculatePricing([
            'veteran_type' => 'martyr_children',
            'nights'       => 2,
        ]);

        $this->assertSame(50, $pricing['accommodation_discount_percentage']);
    }

    public function test_martyr_spouse_gets_50_percent_accommodation_discount(): void
    {
        $pricing = $this->calculatePricing([
            'veteran_type' => 'martyr_spouse_dependents',
            'nights'       => 2,
        ]);

        $this->assertSame(50, $pricing['accommodation_discount_percentage']);
    }

    public function test_freed_prisoner_gets_50_percent_accommodation_discount(): void
    {
        $pricing = $this->calculatePricing([
            'veteran_type' => 'freed_prisoner_dependents',
            'nights'       => 2,
        ]);

        $this->assertSame(50, $pricing['accommodation_discount_percentage']);
    }

    public function test_no_veteran_type_gets_zero_discount(): void
    {
        $pricing = $this->calculatePricing([
            'veteran_type' => null,
            'nights'       => 2,
        ]);

        $this->assertSame(0, $pricing['accommodation_discount_percentage']);
        $this->assertSame(2_000_000, $pricing['total_price']);
    }

    public function test_children_under_6_pay_half_accommodation_rate_without_veteran_discount(): void
    {
        $pricing = $this->calculatePricing([
            'veteran_type'     => null,
            'guests'           => 2,
            'children_under_6' => 1,
            'nights'           => 2,
        ]);

        $this->assertSame(1, $pricing['children_under_6']);
        $this->assertSame(3_000_000, $pricing['room_subtotal']);
        $this->assertSame(1_000_000, $pricing['children_discount_amount']);
        $this->assertSame(0, $pricing['accommodation_discount_percentage']);
        $this->assertSame(3_000_000, $pricing['total_price']);
    }

    public function test_children_under_6_half_rate_combines_with_veteran_accommodation_discount(): void
    {
        $pricing = $this->calculatePricing([
            'veteran_type'     => 'veteran_70_spouses',
            'guests'           => 2,
            'children_under_6' => 1,
            'nights'           => 2,
        ]);

        $this->assertSame(70, $pricing['accommodation_discount_percentage']);
        $this->assertSame(3_000_000, $pricing['room_subtotal']);
        $this->assertSame(1_000_000, $pricing['children_discount_amount']);
        $this->assertSame(2_100_000, $pricing['discount_amount']);
        $this->assertSame(900_000, $pricing['total_price']);
    }

    public function test_one_guest_excluded_from_veteran_accommodation_discount(): void
    {
        $pricing = $this->calculatePricing([
            'veteran_type'               => 'veteran_70_spouses',
            'guests'                     => 6,
            'non_veteran_discount_guests'=> 1,
            'nights'                     => 1,
        ]);

        $this->assertSame(70, $pricing['accommodation_discount_percentage']);
        $this->assertSame(1, $pricing['non_veteran_discount_guests']);
        $this->assertSame(6_000_000, $pricing['room_subtotal']);
        $this->assertSame(3_500_000, $pricing['veteran_accommodation_discount_amount']);
        $this->assertSame(2_500_000, $pricing['total_price']);
    }

    public function test_manual_guest_discount_for_normal_rate_guest(): void
    {
        $pricingService = app(BookingPricingService::class);
        $perGuestSlots = $pricingService->buildPerGuestSlotsFromGuestDetails(
            [
                ['excluded_from_veteran_discount' => false, 'manual_discount_percentage' => 20, 'manual_discount_reason' => 'همکاری'],
                ['excluded_from_veteran_discount' => false, 'manual_discount_percentage' => '', 'manual_discount_reason' => ''],
            ],
            billingGuests: 2,
            childrenUnder6: 0,
            veteranType: null,
            veteranDiscountPct: 0,
        );

        $pricing = $this->calculatePricing([
            'veteran_type'    => null,
            'guests'          => 2,
            'nights'          => 1,
            'per_guest_slots' => $perGuestSlots,
        ]);

        $this->assertSame(2_000_000, $pricing['room_subtotal']);
        $this->assertSame(200_000, $pricing['manual_accommodation_discount_amount']);
        $this->assertSame(1_800_000, $pricing['total_price']);
    }

    public function test_children_under_6_with_separate_adults_count(): void
    {
        $pricing = $this->calculatePricing([
            'veteran_type'     => null,
            'guests'           => 3,
            'children_under_6' => 1,
            'nights'           => 2,
        ]);

        // 2 adults + 1 child under 6, 2 nights @ 1M
        $this->assertSame(3, $pricing['billing_guests']);
        $this->assertSame(1, $pricing['children_under_6']);
        $this->assertSame(5_000_000, $pricing['room_subtotal']);
        $this->assertSame(1_000_000, $pricing['children_discount_amount']);
        $this->assertSame(5_000_000, $pricing['total_price']);
    }

    // ──────────────────────────────────────────────────────
    // 2.  Pool free sessions for veteran_70
    // ──────────────────────────────────────────────────────

    public function test_veteran70_pool_sessions_within_quota_are_fully_free(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $policy = $this->veteranPolicyFor($this->accommodation);

        $pricing = $this->calculatePricing([
            'veteran_type' => 'veteran_70_spouses',
            'nights'       => 2,
            'services'     => $policy->enrichServicesWithDiscounts('veteran_70_spouses', [[
                'service_catalog_id' => $pool->id,
                'name'               => 'استخر',
                'unit_price'         => 200_000,
                'quantity'           => 2,   // 2 ≤ 3 weekly free → all free
            ]]),
        ]);

        $this->assertSame(400_000, $pricing['services_subtotal']);
        $this->assertSame(400_000, $pricing['services_discount_amount']); // all free
        // Room discount 70% of 2M = 1.4M → paid 600k; services 0 → total 600k
        $this->assertSame(600_000, $pricing['total_price']);
    }

    public function test_veteran70_pool_sessions_beyond_3_pay_full_price(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $policy = $this->veteranPolicyFor($this->accommodation);

        $pricing = $this->calculatePricing([
            'veteran_type' => 'veteran_70_spouses',
            'nights'       => 2,
            'services'     => $policy->enrichServicesWithDiscounts('veteran_70_spouses', [[
                'service_catalog_id' => $pool->id,
                'name'               => 'استخر',
                'unit_price'         => 200_000,
                'quantity'           => 5,   // 3 free, 2 at matrix 0% (full price)
            ]]),
        ]);

        // 3 free = 600k discount; 2 paid at 0% matrix discount = 400k
        $this->assertSame(1_000_000, $pricing['services_subtotal']);
        $this->assertSame(600_000, $pricing['services_discount_amount']);
        // Room 600k + services 400k
        $this->assertSame(1_000_000, $pricing['total_price']);
    }

    public function test_veteran70_duplicate_pool_lines_share_weekly_free_quota(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $policy = $this->veteranPolicyFor($this->accommodation);

        $pricing = $this->calculatePricing([
            'veteran_type' => 'veteran_70_spouses',
            'nights'       => 2,
            'services'     => $policy->enrichServicesWithDiscounts('veteran_70_spouses', [
                [
                    'service_catalog_id' => $pool->id,
                    'name'               => 'استخر',
                    'unit_price'         => 100,
                    'quantity'           => 10,
                ],
                [
                    'service_catalog_id' => $pool->id,
                    'name'               => 'استخر',
                    'unit_price'         => 100,
                    'quantity'           => 10,
                ],
            ]),
        ]);

        $lines = $pricing['service_lines'];
        $this->assertCount(2, $lines);
        // Only 3 free sessions total across both lines (not 3 per line).
        $this->assertSame(300, $pricing['services_discount_amount']);
        $this->assertSame(300, $lines[0]['discount_amount']);
        $this->assertSame(0, $lines[1]['discount_amount']);
    }

    public function test_non_veteran70_pool_gets_65_percent_discount_not_free(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $policy = $this->veteranPolicyFor($this->accommodation);

        $pricing = $this->calculatePricing([
            'veteran_type' => 'veteran_50_69_dependents',
            'nights'       => 2,
            'services'     => $policy->enrichServicesWithDiscounts('veteran_50_69_dependents', [[
                'service_catalog_id' => $pool->id,
                'name'               => 'استخر',
                'unit_price'         => 200_000,
                'quantity'           => 2,
            ]]),
        ]);

        $expectedServiceDiscount = (int) round(2 * 200_000 * 65 / 100); // 260_000
        $this->assertSame($expectedServiceDiscount, $pricing['services_discount_amount']);
    }

    public function test_pool_free_sessions_rule_data_for_veteran70(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $policy = $this->veteranPolicyFor($this->accommodation);

        $rule = $policy->serviceDiscountRule('veteran_70_spouses', $pool->id);

        $this->assertTrue($rule['free_sessions_eligible']);
        $this->assertSame(3, $rule['weekly_free_sessions']);
        $this->assertSame(0, $rule['discount_percentage']); // matrix = 0 for veteran_70
        $this->assertSame(50, $rule['min_discount']);
        $this->assertSame(80, $rule['max_discount']);
    }

    // ──────────────────────────────────────────────────────
    // 3.  CRITICAL BUG FIX: recalculateTotals must NOT clamp free-session
    //     discount (stored 0%) to min_discount (50%) for veteran_70+pool
    // ──────────────────────────────────────────────────────

    public function test_recalculate_totals_preserves_free_sessions_for_veteran70(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $booking = $this->makeBookingWithService('veteran_70_spouses', $pool->id, 200_000, 2);

        app(ManualBookingService::class)->recalculateTotals($booking);
        $booking->refresh();

        $service = $booking->services()->first();
        // 2 sessions ≤ 3 free → all free
        $this->assertSame(400_000, $service->discount_amount);
        $this->assertSame(0, $service->total);
    }

    public function test_recalculate_totals_does_not_clamp_zero_to_min_discount(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        // 5 sessions: 3 free + 2 paid at matrix 0% (NOT at clamped 50%)
        $booking = $this->makeBookingWithService('veteran_70_spouses', $pool->id, 100_000, 5);

        app(ManualBookingService::class)->recalculateTotals($booking);
        $booking->refresh();

        $service = $booking->services()->first();
        // 3 free = 300k discount; 2 paid at 0% (matrix) = 200k total
        $this->assertSame(300_000, $service->discount_amount);
        $this->assertSame(200_000, $service->total);
        // Must NOT be: discount = 300k + (2×100k×50%) = 400k (wrong clamped behavior)
    }

    public function test_recalculate_totals_applies_65_percent_for_veteran50_pool(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $booking = $this->makeBookingWithService('veteran_50_69_dependents', $pool->id, 200_000, 2);

        app(ManualBookingService::class)->recalculateTotals($booking);
        $booking->refresh();

        $service = $booking->services()->first();
        $expectedDiscount = (int) round(2 * 200_000 * 65 / 100); // 260_000
        $this->assertSame($expectedDiscount, $service->discount_amount);
        $this->assertSame(400_000 - $expectedDiscount, $service->total);
    }

    public function test_recalculate_totals_uses_fresh_service_data(): void
    {
        // Ensures recalculate fetches fresh DB rows, not stale in-memory collection
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $booking = $this->makeBookingWithService('veteran_50_69_dependents', $pool->id, 100_000, 2);

        // Directly update the service in DB (simulating saveServiceEdits)
        $booking->services()->update(['unit_price' => 200_000, 'quantity' => 3, 'total' => 600_000]);

        // Load stale version (still shows old 100k/2 values in memory)
        $booking->load('services');

        // recalculateTotals should use fresh 200k/3 values
        app(ManualBookingService::class)->recalculateTotals($booking);
        $booking->refresh();

        $service = $booking->services()->first();
        $expectedDiscount = (int) round(3 * 200_000 * 65 / 100); // 390_000
        $this->assertSame($expectedDiscount, $service->discount_amount);
    }

    // ──────────────────────────────────────────────────────
    // 4.  Conference hall — 40% for all groups
    // ──────────────────────────────────────────────────────

    public function test_conference_hall_40_percent_for_all_groups(): void
    {
        $conference = $this->veteranCatalog($this->accommodation, 'conference_hall');
        $policy = $this->veteranPolicyFor($this->accommodation);

        foreach (VeteranGroup::forAccommodation($this->accommodation->id)->active()->get() as $group) {
            $rule = $policy->serviceDiscountRule($group->key, $conference->id);
            $this->assertSame(40, $rule['discount_percentage'],
                "Expected 40% for {$group->key}");
            $this->assertFalse($rule['free_sessions_eligible']);
        }
    }

    // ──────────────────────────────────────────────────────
    // 5.  Reception — 50% entrance, 20% food for all groups
    // ──────────────────────────────────────────────────────

    public function test_reception_entrance_50_percent_for_all_groups(): void
    {
        $entrance = $this->veteranCatalog($this->accommodation, 'reception_entrance');
        $policy = $this->veteranPolicyFor($this->accommodation);

        foreach (VeteranGroup::forAccommodation($this->accommodation->id)->active()->get() as $group) {
            $rule = $policy->serviceDiscountRule($group->key, $entrance->id);
            $this->assertSame(50, $rule['discount_percentage'],
                "Expected 50% for {$group->key}");
        }
    }

    public function test_reception_food_20_percent_for_all_groups(): void
    {
        $food = $this->veteranCatalog($this->accommodation, 'reception_food');
        $policy = $this->veteranPolicyFor($this->accommodation);

        foreach (VeteranGroup::forAccommodation($this->accommodation->id)->active()->get() as $group) {
            $rule = $policy->serviceDiscountRule($group->key, $food->id);
            $this->assertSame(20, $rule['discount_percentage'],
                "Expected 20% for {$group->key}");
        }
    }

    // ──────────────────────────────────────────────────────
    // 6.  Gym and multi-purpose hall follow same rules as pool
    // ──────────────────────────────────────────────────────

    public function test_gym_is_free_for_veteran70(): void
    {
        $gym = $this->veteranCatalog($this->accommodation, 'gym');
        $rule = $this->veteranPolicyFor($this->accommodation)->serviceDiscountRule('veteran_70_spouses', $gym->id);

        $this->assertTrue($rule['free_sessions_eligible']);
        $this->assertSame(3, $rule['weekly_free_sessions']);
    }

    public function test_multi_purpose_hall_is_free_for_veteran70(): void
    {
        $hall = $this->veteranCatalog($this->accommodation, 'multi_purpose_hall');
        $rule = $this->veteranPolicyFor($this->accommodation)->serviceDiscountRule('veteran_70_spouses', $hall->id);

        $this->assertTrue($rule['free_sessions_eligible']);
    }

    public function test_sports_services_have_65_percent_for_other_groups(): void
    {
        $policy = $this->veteranPolicyFor($this->accommodation);
        $sportKeys = ['pool', 'gym', 'multi_purpose_hall'];
        $veteranKeys = [
            'veteran_50_69_dependents', 'veteran_25_49_dependents',
            'martyr_children', 'martyr_parents_dependents',
            'martyr_spouse_dependents', 'freed_prisoner_dependents',
        ];

        foreach ($sportKeys as $serviceKey) {
            $service = $this->veteranCatalog($this->accommodation, $serviceKey);
            foreach ($veteranKeys as $vKey) {
                $rule = $policy->serviceDiscountRule($vKey, $service->id);
                $this->assertSame(65, $rule['discount_percentage'],
                    "Expected 65% for {$vKey} + {$serviceKey}");
                $this->assertFalse($rule['free_sessions_eligible'],
                    "Expected no free sessions for {$vKey} + {$serviceKey}");
            }
        }
    }

    // ──────────────────────────────────────────────────────
    // 7.  Period usage cap (3 nights per 6 months)
    // ──────────────────────────────────────────────────────

    public function test_booking_exceeding_3_night_period_cap_gets_partial_discount(): void
    {
        $result = $this->veteranPolicyFor($this->accommodation)->checkAccommodationUsage(
            'veteran_70_spouses', 1, 4, '1234567890',
        );

        $this->assertTrue($result['allowed']);
        $this->assertSame(3, $result['discounted_nights']);
        $this->assertStringContainsString('3 شب', $result['message'] ?? '');
    }

    public function test_booking_within_period_cap_is_allowed(): void
    {
        $result = $this->veteranPolicyFor($this->accommodation)->checkAccommodationUsage(
            'veteran_70_spouses', 1, 3, '1234567890',
        );

        $this->assertTrue($result['allowed']);
    }

    public function test_used_nights_are_counted_against_period_cap(): void
    {
        $guest = User::create(['name' => 'مهمان', 'mobile' => '09111222333', 'national_id' => '5554443332']);
        $guest->assignRole('guest');

        Booking::create([
            'user_id'              => $guest->id,
            'accommodation_id'     => $this->accommodation->id,
            'veteran_type_applied' => 'veteran_70_spouses',
            'booking_source'       => 'manual',
            'nights'               => 2,
            'check_in'             => now()->subMonths(1),
            'check_out'            => now()->subMonths(1)->addDays(2),
            'status'               => 'confirmed',
            'guests'               => 1,
            'base_price'           => 0,
            'discount_percentage'  => 70,
            'discount_amount'      => 0,
            'total_price'          => 0,
            'tracking_code'        => 'TEST001',
        ]);

        // 2 used + 2 requested = 4 > cap of 3 → only 1 night gets veteran discount
        $result = $this->veteranPolicyFor($this->accommodation)->checkAccommodationUsage(
            'veteran_70_spouses', 1, 2, '5554443332', $guest->id,
        );

        $this->assertTrue($result['allowed']);
        $this->assertSame(1, $result['discounted_nights']);
        $this->assertSame(2, $result['used_in_period']);
    }

    public function test_cancelled_bookings_excluded_from_usage_count(): void
    {
        $guest = User::create(['name' => 'مهمان', 'mobile' => '09333444555', 'national_id' => '7778889990']);
        $guest->assignRole('guest');

        Booking::create([
            'user_id'              => $guest->id,
            'accommodation_id'     => $this->accommodation->id,
            'veteran_type_applied' => 'veteran_70_spouses',
            'booking_source'       => 'manual',
            'nights'               => 3,
            'check_in'             => now()->subMonths(1),
            'check_out'            => now()->subMonths(1)->addDays(3),
            'status'               => 'cancelled',  // Should NOT count
            'guests'               => 1,
            'base_price'           => 0,
            'discount_percentage'  => 70,
            'discount_amount'      => 0,
            'total_price'          => 0,
            'tracking_code'        => 'TEST002',
        ]);

        $result = $this->veteranPolicyFor($this->accommodation)->checkAccommodationUsage(
            'veteran_70_spouses', 1, 3, '7778889990', $guest->id,
        );

        $this->assertTrue($result['allowed']);
        $this->assertSame(0, $result['used_in_period']);
    }

    public function test_period_cap_is_shared_across_accommodations(): void
    {
        $accommodationB = $this->createTestAccommodation(['name' => 'اقامتگاه دوم']);
        $nationalId = '1112223334';
        $guest = User::create(['name' => 'مهمان', 'mobile' => '09112223344', 'national_id' => $nationalId]);
        $guest->assignRole('guest');

        Booking::create([
            'user_id'                             => $guest->id,
            'accommodation_id'                    => $this->accommodation->id,
            'veteran_type_applied'                => 'veteran_70_spouses',
            'veteran_accommodation_group_usage'   => ['veteran_70_spouses' => 2],
            'booking_source'                      => 'manual',
            'nights'                              => 2,
            'check_in'                            => now()->subMonth(),
            'check_out'                           => now()->subMonth()->addDays(2),
            'status'                              => 'confirmed',
            'guests'                              => 1,
            'base_price'                          => 0,
            'discount_percentage'                 => 70,
            'discount_amount'                     => 0,
            'total_price'                         => 0,
            'tracking_code'                       => 'CROSSACC01',
        ]);

        $policyB = $this->veteranPolicyFor($accommodationB);
        $result = $policyB->checkAccommodationUsage(
            'veteran_70_spouses', 1, 2, $nationalId, $guest->id,
        );

        $this->assertTrue($result['allowed']);
        $this->assertSame(2, $result['used_in_period']);
        $this->assertSame(1, $result['remaining_period']);
        $this->assertSame(1, $result['discounted_nights']);
    }

    public function test_usage_summary_shows_cross_accommodation_period_deductions(): void
    {
        $accommodationB = $this->createTestAccommodation(['name' => 'اقامتگاه دوم']);
        $nationalId = '2223334445';
        $guest = User::create(['name' => 'مهمان', 'mobile' => '09123334455', 'national_id' => $nationalId]);
        $guest->assignRole('guest');

        Booking::create([
            'user_id'                             => $guest->id,
            'accommodation_id'                    => $this->accommodation->id,
            'veteran_type_applied'                => 'veteran_70_spouses',
            'veteran_accommodation_group_usage'   => ['veteran_70_spouses' => 2],
            'booking_source'                      => 'manual',
            'nights'                              => 2,
            'check_in'                            => now()->subWeek(),
            'check_out'                           => now()->subWeek()->addDays(2),
            'status'                              => 'confirmed',
            'guests'                              => 1,
            'base_price'                          => 0,
            'discount_percentage'                 => 70,
            'discount_amount'                     => 0,
            'total_price'                         => 0,
            'tracking_code'                       => 'CROSSACC02',
        ]);

        $summary = $this->veteranPolicyFor($accommodationB)->usageSummary(
            'veteran_70_spouses', 1, $nationalId, $guest->id,
        );

        $this->assertSame(2, $summary['used_in_period']);
        $this->assertSame(1, $summary['remaining_period']);
        $this->assertCount(1, $summary['period_deductions']);
        $this->assertSame('CROSSACC02', $summary['period_deductions'][0]['tracking_code']);
        $this->assertSame($this->accommodation->id, $summary['period_deductions'][0]['accommodation_id']);
        $this->assertSame(2, $summary['period_deductions'][0]['nights']);
    }

    public function test_weekly_pool_quota_is_shared_across_accommodations(): void
    {
        $accommodationB = $this->createTestAccommodation(['name' => 'اقامتگاه دوم']);
        $nationalId = '3334445556';
        $checkIn = $this->sameWeekCheckIn();

        $this->createPriorPoolBooking($nationalId, '09134445556', $checkIn, 3, 100_000);

        $poolB = $this->veteranCatalog($accommodationB, 'pool');
        $policyB = $this->veteranPolicyFor($accommodationB);

        $used = $policyB->usedFreeSessionsInWeek(
            'veteran_70_spouses',
            $nationalId,
            null,
            $poolB->id,
            $checkIn,
        );

        $this->assertSame(3, $used);
    }

    // ──────────────────────────────────────────────────────
    // 8.  Total quota = 6 nights × dependents
    // ──────────────────────────────────────────────────────

    public function test_total_quota_scales_with_number_of_dependents(): void
    {
        $policy = $this->veteranPolicyFor($this->accommodation);

        $summary1 = $policy->usageSummary('veteran_70_spouses', 1);
        $summary2 = $policy->usageSummary('veteran_70_spouses', 2);
        $summary3 = $policy->usageSummary('veteran_70_spouses', 3);

        $this->assertSame(6, $summary1['total_quota']);
        $this->assertSame(12, $summary2['total_quota']);
        $this->assertSame(18, $summary3['total_quota']);
    }

    // ──────────────────────────────────────────────────────
    // 9.  Legacy key normalization
    // ──────────────────────────────────────────────────────

    public function test_all_legacy_keys_normalize_correctly(): void
    {
        $policy = $this->veteranPolicyFor($this->accommodation);
        $map = [
            'veteran_70_plus'       => 'veteran_70_spouses',
            'veteran_50_69'         => 'veteran_50_69_dependents',
            'veteran_25_49'         => 'veteran_25_49_dependents',
            'martyr_family'         => 'martyr_spouse_dependents',
            'freed_prisoner_family' => 'freed_prisoner_dependents',
        ];

        foreach ($map as $legacy => $modern) {
            $this->assertSame($modern, $policy->normalizeKey($legacy),
                "Legacy key {$legacy} should normalize to {$modern}");
        }
    }

    public function test_legacy_veteran70_plus_gets_70_percent_discount(): void
    {
        $this->assertSame(70, $this->veteranPolicyFor($this->accommodation)->accommodationDiscount('veteran_70_plus'));
    }

    // ──────────────────────────────────────────────────────
    // 10. Group label for 5-49% matches PDF (not 25-49%)
    // ──────────────────────────────────────────────────────

    public function test_veteran_5_49_group_label_matches_pdf_not_25(): void
    {
        $group = VeteranGroup::forAccommodation($this->accommodation->id)->where('key', 'veteran_25_49_dependents')->firstOrFail();
        $this->assertStringContainsString('۵ الی ۴۹', $group->label,
            'Group label must say 5-49% as per official policy document (not 25-49%)');
    }

    // ──────────────────────────────────────────────────────
    // 11. ManualBookingService::create — end-to-end discount correctness
    // ──────────────────────────────────────────────────────

    public function test_create_stores_correct_accommodation_and_service_discount_veteran70(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');

        $booking = app(ManualBookingService::class)->create(
            $this->accommodation,
            $this->bookingData('veteran_70_spouses', '1234512345', '09101234567', [
                [
                    'service_catalog_id' => $pool->id,
                    'name'               => 'استخر',
                    'unit_price'         => 150_000,
                    'quantity'           => 2,
                    'discount_override'  => null,
                ],
            ]),
            $this->adminUser
        );

        $this->assertSame(70, $booking->discount_percentage);
        $this->assertSame('veteran_70_spouses', $booking->veteran_type_applied);

        $poolService = $booking->services()->first();
        $this->assertNotNull($poolService);
        $this->assertSame(0, $poolService->discount_percentage);     // matrix = 0 for veteran_70
        $this->assertSame(300_000, $poolService->discount_amount);   // 2 × 150k (all free)
        $this->assertSame(0, $poolService->total);
    }

    public function test_create_allows_booking_beyond_period_cap_with_partial_accommodation_discount(): void
    {
        $checkIn = now()->addDays(5)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(4)->format('Y-m-d');

        $booking = app(ManualBookingService::class)->create(
            $this->accommodation,
            $this->bookingData('veteran_70_spouses', '9988776655', '09119988776', [], $checkIn, $checkOut),
            $this->adminUser
        );

        $this->assertSame(4, $booking->nights);
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame(1_900_000, $booking->total_price);
    }

    public function test_create_stores_correct_65_percent_for_veteran50_pool(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');

        $booking = app(ManualBookingService::class)->create(
            $this->accommodation,
            $this->bookingData('veteran_50_69_dependents', '9876543210', '09209876543', [
                [
                    'service_catalog_id' => $pool->id,
                    'name'               => 'استخر',
                    'unit_price'         => 200_000,
                    'quantity'           => 2,
                    'discount_override'  => null,
                ],
            ]),
            $this->adminUser
        );

        $poolService = $booking->services()->first();
        $this->assertSame(65, $poolService->discount_percentage);
        $this->assertSame((int) round(2 * 200_000 * 65 / 100), $poolService->discount_amount);
    }

    // ──────────────────────────────────────────────────────
    // 12. Weekly free sessions across multiple bookings (same person)
    // ──────────────────────────────────────────────────────

    public function test_used_free_sessions_in_week_counts_prior_booking_pool_usage(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $nationalId = '1212121212';
        $checkIn = $this->sameWeekCheckIn();

        $this->createPriorPoolBooking($nationalId, '09121212121', $checkIn, 3, 100_000);

        $used = $this->veteranPolicyFor($this->accommodation)->usedFreeSessionsInWeek(
            'veteran_70_spouses',
            $nationalId,
            null,
            $pool->id,
            $checkIn,
        );

        $this->assertSame(3, $used);
    }

    public function test_second_booking_same_week_gets_no_additional_free_pool_sessions(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $policy = $this->veteranPolicyFor($this->accommodation);
        $nationalId = '1313131313';
        $mobile = '09131313131';
        $checkIn = $this->sameWeekCheckIn();

        $this->createPriorPoolBooking($nationalId, $mobile, $checkIn, 3, 100_000);

        $pricing = $this->calculatePricing([
            'veteran_type' => 'veteran_70_spouses',
            'check_in'     => $checkIn,
            'nights'       => 1,
            'national_id'  => $nationalId,
            'services'     => $policy->enrichServicesWithDiscounts('veteran_70_spouses', [[
                'service_catalog_id' => $pool->id,
                'name'               => 'استخر',
                'unit_price'         => 100_000,
                'quantity'           => 3,
            ]]),
        ]);

        $this->assertSame(300_000, $pricing['services_subtotal']);
        $this->assertSame(0, $pricing['services_discount_amount']);
        $this->assertSame(300_000, $pricing['service_lines'][0]['line_total']);
        $this->assertSame(0, $pricing['service_lines'][0]['free_units']);
    }

    public function test_second_booking_same_week_gets_only_remaining_free_pool_sessions(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $policy = $this->veteranPolicyFor($this->accommodation);
        $nationalId = '1414141414';
        $checkIn = $this->sameWeekCheckIn();

        $this->createPriorPoolBooking($nationalId, '09141414141', $checkIn, 2, 100_000);

        $pricing = $this->calculatePricing([
            'veteran_type' => 'veteran_70_spouses',
            'check_in'     => $checkIn,
            'nights'       => 1,
            'national_id'  => $nationalId,
            'services'     => $policy->enrichServicesWithDiscounts('veteran_70_spouses', [[
                'service_catalog_id' => $pool->id,
                'name'               => 'استخر',
                'unit_price'         => 100_000,
                'quantity'           => 3,
            ]]),
        ]);

        // 1 remaining free + 2 paid at full price
        $this->assertSame(100_000, $pricing['services_discount_amount']);
        $this->assertSame(1, $pricing['service_lines'][0]['free_units']);
        $this->assertSame(200_000, $pricing['service_lines'][0]['line_total']);
    }

    public function test_cancelled_booking_does_not_count_toward_weekly_pool_quota(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $nationalId = '1515151515';
        $checkIn = $this->sameWeekCheckIn();

        $this->createPriorPoolBooking($nationalId, '09151515151', $checkIn, 3, 100_000, 'cancelled');

        $used = $this->veteranPolicyFor($this->accommodation)->usedFreeSessionsInWeek(
            'veteran_70_spouses',
            $nationalId,
            null,
            $pool->id,
            $checkIn,
        );

        $this->assertSame(0, $used);
    }

    public function test_different_week_gets_fresh_pool_free_quota(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $policy = $this->veteranPolicyFor($this->accommodation);
        $nationalId = '1616161616';
        $priorWeek = Carbon::parse($this->sameWeekCheckIn())->subWeek()->format('Y-m-d');
        $thisWeek = $this->sameWeekCheckIn();

        $this->createPriorPoolBooking($nationalId, '09161616161', $priorWeek, 3, 100_000);

        $pricing = $this->calculatePricing([
            'veteran_type' => 'veteran_70_spouses',
            'check_in'     => $thisWeek,
            'nights'       => 1,
            'national_id'  => $nationalId,
            'services'     => $policy->enrichServicesWithDiscounts('veteran_70_spouses', [[
                'service_catalog_id' => $pool->id,
                'name'               => 'استخر',
                'unit_price'         => 100_000,
                'quantity'           => 3,
            ]]),
        ]);

        $this->assertSame(300_000, $pricing['services_discount_amount']);
        $this->assertSame(3, $pricing['service_lines'][0]['free_units']);
    }

    public function test_gym_and_pool_have_independent_weekly_free_quotas(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $gym = $this->veteranCatalog($this->accommodation, 'gym');
        $policy = $this->veteranPolicyFor($this->accommodation);
        $nationalId = '1717171717';
        $checkIn = $this->sameWeekCheckIn();

        $this->createPriorPoolBooking($nationalId, '09171717171', $checkIn, 3, 100_000);

        $pricing = $this->calculatePricing([
            'veteran_type' => 'veteran_70_spouses',
            'check_in'     => $checkIn,
            'nights'       => 1,
            'national_id'  => $nationalId,
            'services'     => $policy->enrichServicesWithDiscounts('veteran_70_spouses', [[
                'service_catalog_id' => $gym->id,
                'name'               => 'بدنسازی',
                'unit_price'         => 80_000,
                'quantity'           => 2,
            ]]),
        ]);

        $this->assertSame(160_000, $pricing['services_discount_amount']);
        $this->assertSame(2, $pricing['service_lines'][0]['free_units']);
    }

    public function test_create_second_manual_booking_same_week_charges_full_pool_price(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $nationalId = '1818181818';
        $mobile = '09181818181';
        $checkIn = $this->sameWeekCheckIn();
        $checkOut = Carbon::parse($checkIn)->addDay()->format('Y-m-d');

        $first = app(ManualBookingService::class)->create(
            $this->accommodation,
            $this->bookingData('veteran_70_spouses', $nationalId, $mobile, [
                [
                    'service_catalog_id' => $pool->id,
                    'name'               => 'استخر',
                    'unit_price'         => 100_000,
                    'quantity'           => 3,
                    'discount_override'  => null,
                ],
            ], $checkIn, $checkOut),
            $this->adminUser,
        );

        $firstPool = $first->services()->first();
        $this->assertSame(3, $firstPool->free_units);
        $this->assertSame(300_000, $firstPool->discount_amount);

        $second = app(ManualBookingService::class)->create(
            $this->accommodation,
            $this->bookingData('veteran_70_spouses', $nationalId, $mobile, [
                [
                    'service_catalog_id' => $pool->id,
                    'name'               => 'استخر',
                    'unit_price'         => 100_000,
                    'quantity'           => 3,
                    'discount_override'  => null,
                ],
            ], $checkIn, $checkOut),
            $this->adminUser,
        );

        $poolService = $second->services()->first();
        $this->assertSame(0, $poolService->free_units);
        $this->assertSame(0, $poolService->discount_amount);
        $this->assertSame(300_000, $poolService->total);
    }

    public function test_create_stores_free_units_on_pool_service(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');

        $booking = app(ManualBookingService::class)->create(
            $this->accommodation,
            $this->bookingData('veteran_70_spouses', '2323232323', '09232323232', [
                [
                    'service_catalog_id' => $pool->id,
                    'name'               => 'استخر',
                    'unit_price'         => 100_000,
                    'quantity'           => 5,
                    'discount_override'  => null,
                ],
            ]),
            $this->adminUser,
        );

        $poolService = $booking->services()->first();
        $this->assertSame(3, $poolService->free_units);
        $this->assertSame(300_000, $poolService->discount_amount);
        $this->assertSame(200_000, $poolService->total);
    }

    public function test_pricing_preview_without_national_id_does_not_apply_cross_booking_free_quota(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $policy = $this->veteranPolicyFor($this->accommodation);
        $nationalId = '2424242424';
        $checkIn = $this->sameWeekCheckIn();

        $this->createPriorPoolBooking($nationalId, '09242424242', $checkIn, 3, 100_000);

        // Without national_id the weekly quota from prior bookings is unknown.
        $pricing = $this->calculatePricing([
            'veteran_type' => 'veteran_70_spouses',
            'check_in'     => $checkIn,
            'nights'       => 1,
            'services'     => $policy->enrichServicesWithDiscounts('veteran_70_spouses', [[
                'service_catalog_id' => $pool->id,
                'name'               => 'استخر',
                'unit_price'         => 100_000,
                'quantity'           => 3,
            ]]),
        ]);

        $this->assertSame(300_000, $pricing['services_discount_amount']);
        $this->assertSame(3, $pricing['service_lines'][0]['free_units']);
    }

    public function test_pricing_with_national_id_respects_prior_weekly_pool_usage(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $policy = $this->veteranPolicyFor($this->accommodation);
        $nationalId = '2525252525';
        $checkIn = $this->sameWeekCheckIn();

        $this->createPriorPoolBooking($nationalId, '09252525252', $checkIn, 3, 100_000);

        $pricing = $this->calculatePricing([
            'veteran_type' => 'veteran_70_spouses',
            'check_in'     => $checkIn,
            'nights'       => 1,
            'national_id'  => $nationalId,
            'services'     => $policy->enrichServicesWithDiscounts('veteran_70_spouses', [[
                'service_catalog_id' => $pool->id,
                'name'               => 'استخر',
                'unit_price'         => 100_000,
                'quantity'           => 3,
            ]]),
        ]);

        $this->assertSame(0, $pricing['services_discount_amount']);
        $this->assertSame(0, $pricing['service_lines'][0]['free_units']);
    }

    public function test_recalculate_totals_excludes_current_booking_from_weekly_pool_usage(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $booking = $this->makeBookingWithService('veteran_70_spouses', $pool->id, 100_000, 3);

        app(ManualBookingService::class)->recalculateTotals($booking);
        $booking->refresh();

        $service = $booking->services()->first();
        $this->assertSame(300_000, $service->discount_amount);
        $this->assertSame(0, $service->total);
    }

    public function test_usage_summary_includes_weekly_free_session_usage_per_service(): void
    {
        $nationalId = '1919191919';
        $checkIn = $this->sameWeekCheckIn();
        $this->createPriorPoolBooking($nationalId, '09191919191', $checkIn, 2, 100_000);

        $summary = $this->veteranPolicyFor($this->accommodation)->usageSummary(
            'veteran_70_spouses',
            1,
            $nationalId,
            null,
            $checkIn,
        );

        $this->assertArrayHasKey('weekly_free_usage', $summary);
        $this->assertSame(2, $summary['weekly_free_usage']['pool']['used']);
        $this->assertSame(3, $summary['weekly_free_usage']['pool']['quota']);
        $this->assertSame(1, $summary['weekly_free_usage']['pool']['remaining']);
    }

    public function test_cross_booking_and_duplicate_lines_share_single_weekly_pool_quota(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $policy = $this->veteranPolicyFor($this->accommodation);
        $nationalId = '2020202020';
        $checkIn = $this->sameWeekCheckIn();

        $this->createPriorPoolBooking($nationalId, '09202020202', $checkIn, 2, 100_000);

        $pricing = $this->calculatePricing([
            'veteran_type' => 'veteran_70_spouses',
            'check_in'     => $checkIn,
            'nights'       => 1,
            'national_id'  => $nationalId,
            'services'     => $policy->enrichServicesWithDiscounts('veteran_70_spouses', [
                [
                    'service_catalog_id' => $pool->id,
                    'name'               => 'استخر',
                    'unit_price'         => 100,
                    'quantity'           => 2,
                ],
                [
                    'service_catalog_id' => $pool->id,
                    'name'               => 'استخر',
                    'unit_price'         => 100,
                    'quantity'           => 2,
                ],
            ]),
        ]);

        // 2 used in prior booking + 1 remaining free in first line + 0 in second line
        $this->assertSame(100, $pricing['services_discount_amount']);
        $this->assertSame(1, $pricing['service_lines'][0]['free_units']);
        $this->assertSame(0, $pricing['service_lines'][1]['free_units']);
    }

    public function test_service_variant_uses_variant_price_and_parent_discount(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $variant = \App\Models\ServiceCatalogVariant::create([
            'service_catalog_id' => $pool->id,
            'key'                => 'pool_neshat',
            'name'               => 'استخر نشاط',
            'price'              => 500_000,
            'sort_order'         => 1,
            'is_active'          => true,
        ]);

        $pricing = $this->calculatePricing([
            'veteran_type' => 'veteran_50_69',
            'services'     => [
                [
                    'service_catalog_id'         => $pool->id,
                    'service_catalog_variant_id' => $variant->id,
                    'name'                       => 'استخر — استخر نشاط',
                    'unit_price'                 => 500_000,
                    'quantity'                   => 1,
                ],
            ],
        ]);

        $line = $pricing['service_lines'][0];
        $this->assertSame('استخر — استخر نشاط', $line['name']);
        $this->assertSame(500_000, $line['unit_price']);
        $this->assertSame($variant->id, $line['service_catalog_variant_id']);
        $this->assertSame(65, $line['discount_percentage']);
        $this->assertSame(325_000, $line['discount_amount']);
    }

    // ──────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────

    private function calculatePricing(array $params): array
    {
        $nights = $params['nights'] ?? 2;
        $checkIn = $params['check_in'] ?? now()->addDays(5)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays($nights)->format('Y-m-d');

        return app(BookingPricingService::class)->calculate([
            'check_in'        => $checkIn,
            'check_out'       => $checkOut,
            'guests'          => $params['guests'] ?? 1,
            'children_under_6'=> $params['children_under_6'] ?? 0,
            'extra_guests'    => $params['extra_guests'] ?? 0,
            'bill_full_rooms' => $params['bill_full_rooms'] ?? false,
            'veteran_type'    => $params['veteran_type'] ?? null,
            'services'        => $params['services'] ?? [],
            'accommodation'   => $this->accommodation,
            'room_type'       => null,
            'room_rate'       => null,
            'national_id'     => $params['national_id'] ?? null,
            'user_id'         => $params['user_id'] ?? null,
            'non_veteran_discount_guests' => $params['non_veteran_discount_guests'] ?? 0,
            'per_guest_slots'             => $params['per_guest_slots'] ?? null,
            'exclude_booking_id' => $params['exclude_booking_id'] ?? null,
        ]);
    }

    private function sameWeekCheckIn(): string
    {
        return now()->startOfWeek(Carbon::SATURDAY)->addDays(2)->format('Y-m-d');
    }

    private function createPriorPoolBooking(
        string $nationalId,
        string $mobile,
        string $checkIn,
        int $poolQuantity,
        int $unitPrice,
        string $status = 'confirmed',
    ): Booking {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $checkOut = Carbon::parse($checkIn)->addDay()->format('Y-m-d');
        $freeUnits = min(3, $poolQuantity);
        $discountAmount = $freeUnits * $unitPrice;

        $guest = User::firstOrCreate(
            ['national_id' => $nationalId],
            ['name' => 'مهمان هفتگی', 'mobile' => $mobile],
        );
        if (!$guest->hasRole('guest')) {
            $guest->assignRole('guest');
        }

        $booking = Booking::create([
            'user_id'              => $guest->id,
            'accommodation_id'     => $this->accommodation->id,
            'veteran_type_applied' => 'veteran_70_spouses',
            'booking_source'       => 'manual',
            'check_in'             => $checkIn,
            'check_out'            => $checkOut,
            'nights'               => 1,
            'guests'               => 1,
            'base_price'           => 1_000_000,
            'services_subtotal'    => $unitPrice * $poolQuantity,
            'discount_percentage'  => 70,
            'discount_amount'      => $discountAmount,
            'total_price'          => 1_000_000,
            'status'               => $status,
            'tracking_code'        => strtoupper(substr(md5(uniqid((string) $nationalId, true)), 0, 10)),
        ]);

        BookingService::create([
            'booking_id'          => $booking->id,
            'service_catalog_id'  => $pool->id,
            'name'                => 'استخر',
            'unit_price'          => $unitPrice,
            'quantity'            => $poolQuantity,
            'free_units'          => $freeUnits,
            'discount_percentage' => 0,
            'discount_amount'     => $discountAmount,
            'total'               => ($unitPrice * $poolQuantity) - $discountAmount,
            'sort_order'          => 0,
        ]);

        BookingGuestDetail::create([
            'booking_id'  => $booking->id,
            'sort_order'  => 0,
            'full_name'   => 'مهمان هفتگی',
            'national_id' => $nationalId,
            'mobile'      => $mobile,
        ]);

        return $booking;
    }

    private function makeBookingWithService(
        string $veteranKey,
        int $catalogId,
        int $unitPrice,
        int $quantity
    ): Booking {
        $guest = User::create([
            'name'        => 'مهمان تست',
            'mobile'      => '091' . rand(10000000, 99999999),
            'national_id' => (string) rand(1000000000, 9999999999),
        ]);
        $guest->assignRole('guest');

        $booking = Booking::create([
            'user_id'              => $guest->id,
            'accommodation_id'     => $this->accommodation->id,
            'veteran_type_applied' => $veteranKey,
            'booking_source'       => 'manual',
            'check_in'             => now()->addDays(5),
            'check_out'            => now()->addDays(7),
            'nights'               => 2,
            'guests'               => 1,
            'base_price'           => 2_000_000 + $unitPrice * $quantity,
            'services_subtotal'    => $unitPrice * $quantity,
            'discount_percentage'  => 70,
            'discount_amount'      => 0,
            'total_price'          => 2_000_000,
            'status'               => 'confirmed',
            'tracking_code'        => strtoupper(substr(md5(uniqid()), 0, 10)),
        ]);

        // Create service with default 0% discount (as it would be before recalculate)
        BookingService::create([
            'booking_id'          => $booking->id,
            'service_catalog_id'  => $catalogId,
            'name'                => 'خدمت تستی',
            'unit_price'          => $unitPrice,
            'quantity'            => $quantity,
            'discount_percentage' => 0,
            'discount_amount'     => 0,
            'total'               => $unitPrice * $quantity,
            'sort_order'          => 0,
        ]);

        return $booking->load(['accommodation', 'services', 'roomType', 'roomRate']);
    }

    private function bookingData(
        string $veteranType,
        string $nationalId,
        string $mobile,
        array $services = [],
        ?string $checkIn = null,
        ?string $checkOut = null,
    ): array {
        $checkIn ??= now()->addDays(5)->format('Y-m-d');
        $checkOut ??= Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');

        return [
            'room_type_id'         => null,
            'room_rate_id'         => null,
            'check_in'             => $checkIn,
            'check_out'            => $checkOut,
            'guests'               => 1,
            'extra_guests'         => 0,
            'bill_full_rooms'      => false,
            'veteran_type'         => $veteranType,
            'booker_national_id'   => $nationalId,
            'guest_contact_name'   => 'مهمان تستی',
            'guest_contact_mobile' => $mobile,
            'payment_method'       => 'cash',
            'user_id'              => null,
            'notes'                => null,
            'services'             => $services,
            'guest_details'        => [
                ['full_name' => 'مهمان تستی', 'national_id' => $nationalId, 'mobile' => $mobile, 'relation' => ''],
            ],
        ];
    }
}
