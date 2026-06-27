<?php

namespace Tests\Feature;

use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Models\VeteranGroup;
use App\Models\VeteranGroupServiceDiscount;
use App\Services\BookingPdfService;
use App\Services\BookingPricingService;
use App\Services\BookingReceiptBreakdownService;
use App\Services\ManualBookingService;
use App\Services\VeteranPolicyService;
use App\Support\VeteranGroups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MultiGroupVeteranPolicyTest extends TestCase
{
    use RefreshDatabase;

    private $accommodation;
    private User $adminUser;
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
        $this->adminUser = User::create([
            'name'   => 'ادمین',
            'mobile' => '09000000099',
        ]);
        $this->adminUser->assignRole('super_admin');

        $this->enableMartyrChildrenPoolFreeSessions(2);
    }

    public function test_accommodation_discount_uses_max_of_both_groups(): void
    {
        $discount = VeteranGroups::accommodationDiscountForTypes(
            ['veteran_70_spouses', 'martyr_children'],
            $this->accommodation->id,
        );

        $this->assertSame(70, $discount);
    }

    public function test_pool_gets_five_free_sessions_with_two_groups(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $policy = $this->veteranPolicyFor($this->accommodation);
        $policy->clearCache($this->accommodation->id);

        $services = $policy->enrichServicesWithDiscountsForTypes(
            ['veteran_70_spouses', 'martyr_children'],
            [[
                'service_catalog_id' => $pool->id,
                'name'               => 'استخر',
                'unit_price'         => 500_000,
                'quantity'           => 5,
            ]],
        );

        $pricing = app(BookingPricingService::class)->calculate([
            'check_in'               => now()->addDays(5)->format('Y-m-d'),
            'check_out'              => now()->addDays(7)->format('Y-m-d'),
            'guests'                 => 1,
            'veteran_types'          => ['veteran_70_spouses', 'martyr_children'],
            'services'               => $services,
            'accommodation'          => $this->accommodation,
        ]);

        $line = $pricing['service_lines'][0];
        $this->assertSame(5, $line['free_units']);
        $this->assertSame(0, $line['line_total']);
        $this->assertSame(3, $line['veteran_group_usage']['veteran_70_spouses']);
        $this->assertSame(2, $line['veteran_group_usage']['martyr_children']);
    }

    public function test_sixth_pool_session_uses_primary_percentage_discount(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $policy = $this->veteranPolicyFor($this->accommodation);
        $policy->clearCache($this->accommodation->id);

        $services = $policy->enrichServicesWithDiscountsForTypes(
            ['veteran_70_spouses', 'martyr_children'],
            [[
                'service_catalog_id' => $pool->id,
                'name'               => 'استخر',
                'unit_price'         => 500_000,
                'quantity'           => 6,
            ]],
        );

        $pricing = app(BookingPricingService::class)->calculate([
            'check_in'      => now()->addDays(5)->format('Y-m-d'),
            'check_out'     => now()->addDays(7)->format('Y-m-d'),
            'guests'        => 1,
            'veteran_types' => ['veteran_70_spouses', 'martyr_children'],
            'services'      => $services,
            'accommodation' => $this->accommodation,
        ]);

        $line = $pricing['service_lines'][0];
        $this->assertSame(5, $line['free_units']);
        // veteran_70 has 0% after free in default matrix; martyr_children applies 50% on session 6
        $this->assertSame(250_000, $line['line_total']);
    }

    public function test_manual_booking_persists_both_veteran_groups(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');

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
            'check_in'             => now()->addDays(3)->format('Y-m-d'),
            'check_out'            => now()->addDays(5)->format('Y-m-d'),
            'guests'               => 1,
            'children_under_6'     => 0,
            'extra_guests'         => 0,
            'veteran_types'        => ['veteran_70_spouses', 'martyr_children'],
            'booker_national_id'   => '4040404040',
            'payment_method'       => 'cash',
            'guest_contact_name'   => 'مهمان دو گروهی',
            'guest_contact_mobile' => '09404040404',
            'services'             => [[
                'service_catalog_id' => $pool->id,
                'name'               => 'استخر',
                'unit_price'         => 500_000,
                'quantity'           => 5,
            ]],
            'guest_details' => [[
                'full_name' => 'مهمان دو گروهی',
                'national_id' => '4040404040',
                'mobile' => '09404040404',
                'relation' => 'رزرو‌کننده',
                'excluded_from_veteran_discount' => false,
                'manual_discount_percentage' => '',
                'manual_discount_reason' => '',
            ]],
        ], $this->adminUser);

        $this->assertSame('veteran_70_spouses', $booking->veteran_type_applied);
        $this->assertSame('martyr_children', $booking->secondary_veteran_type_applied);
        $this->assertSame(70, $booking->discount_percentage);

        $service = $booking->services->first();
        $this->assertSame(5, $service->free_units);
        $this->assertSame(3, $service->veteran_group_usage['veteran_70_spouses']);
        $this->assertSame(2, $service->veteran_group_usage['martyr_children']);
    }

    public function test_usage_summary_shows_combined_weekly_quota(): void
    {
        $policy = $this->veteranPolicyFor($this->accommodation);
        $policy->clearCache($this->accommodation->id);

        $summary = $policy->usageSummary(
            'veteran_70_spouses',
            1,
            '5050505050',
            null,
            now()->format('Y-m-d'),
            'martyr_children',
        );

        $this->assertStringContainsString('جانبازان ۷۰', $summary['label']);
        $this->assertStringContainsString('فرزندان شهدا', $summary['label']);
        $this->assertSame(70, $summary['accommodation_discount']);
        $this->assertSame(5, $summary['weekly_free_usage']['pool']['quota']);
        $this->assertSame(6, $summary['combined_remaining_discounted_nights']);
    }

    public function test_dual_group_accommodation_uses_secondary_period_after_primary_cap(): void
    {
        $pricing = app(BookingPricingService::class)->calculate([
            'check_in'      => now()->addDays(5)->format('Y-m-d'),
            'check_out'     => now()->addDays(10)->format('Y-m-d'),
            'guests'        => 1,
            'veteran_types' => ['veteran_70_spouses', 'martyr_children'],
            'services'      => [],
            'accommodation' => $this->accommodation,
            'room_type'     => $this->roomType,
            'room_rate'     => $this->roomRate,
        ]);

        // 5 nights: 3 @ 70% + 2 @ 50% on 1,000,000/night
        $this->assertSame(5, $pricing['veteran_discount_nights']);
        $this->assertSame(3_100_000, $pricing['veteran_accommodation_discount_amount']);
        $this->assertSame(3, $pricing['veteran_accommodation_group_usage']['veteran_70_spouses']);
        $this->assertSame(2, $pricing['veteran_accommodation_group_usage']['martyr_children']);
        $this->assertSame(1_900_000, $pricing['total_price']);
    }

    public function test_dual_group_accommodation_continues_with_secondary_after_prior_primary_usage(): void
    {
        $guest = User::create([
            'name'        => 'مهمان دو گروهی',
            'mobile'      => '09121212121',
            'national_id' => '6060606060',
        ]);
        $guest->assignRole('guest');

        \App\Models\Booking::create([
            'user_id'                          => $guest->id,
            'accommodation_id'                 => $this->accommodation->id,
            'veteran_type_applied'             => 'veteran_70_spouses',
            'secondary_veteran_type_applied'   => 'martyr_children',
            'veteran_accommodation_group_usage'=> ['veteran_70_spouses' => 3],
            'booking_source'                   => 'manual',
            'nights'                           => 3,
            'check_in'                         => now()->subMonth(),
            'check_out'                        => now()->subMonth()->addDays(3),
            'status'                           => 'confirmed',
            'guests'                           => 1,
            'base_price'                       => 0,
            'discount_percentage'              => 70,
            'discount_amount'                  => 0,
            'total_price'                      => 0,
            'tracking_code'                    => 'PRIOR001',
        ]);

        $pricing = app(BookingPricingService::class)->calculate([
            'check_in'      => now()->addDays(5)->format('Y-m-d'),
            'check_out'     => now()->addDays(9)->format('Y-m-d'),
            'guests'        => 1,
            'veteran_types' => ['veteran_70_spouses', 'martyr_children'],
            'services'      => [],
            'accommodation' => $this->accommodation,
            'room_type'     => $this->roomType,
            'room_rate'     => $this->roomRate,
            'national_id'   => '6060606060',
            'user_id'       => $guest->id,
        ]);

        // Primary period cap exhausted; 4 nights: 3 @ 50% + 1 full rate
        $this->assertSame(3, $pricing['veteran_discount_nights']);
        $this->assertSame(1_500_000, $pricing['veteran_accommodation_discount_amount']);
        $this->assertSame(3, $pricing['veteran_accommodation_group_usage']['martyr_children']);
        $this->assertArrayNotHasKey('veteran_70_spouses', $pricing['veteran_accommodation_group_usage']);
        $this->assertSame(2_500_000, $pricing['total_price']);
    }

    public function test_manual_booking_persists_accommodation_group_usage(): void
    {
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
            'check_in'             => now()->addDays(8)->format('Y-m-d'),
            'check_out'            => now()->addDays(13)->format('Y-m-d'),
            'guests'               => 1,
            'children_under_6'     => 0,
            'extra_guests'         => 0,
            'veteran_types'        => ['veteran_70_spouses', 'martyr_children'],
            'booker_national_id'   => '7070707070',
            'payment_method'       => 'cash',
            'guest_contact_name'   => 'مهمان اقامت دو گروهی',
            'guest_contact_mobile' => '09707070707',
            'services'             => [],
            'guest_details' => [[
                'full_name' => 'مهمان اقامت دو گروهی',
                'national_id' => '7070707070',
                'mobile' => '09707070707',
                'relation' => 'رزرو‌کننده',
                'excluded_from_veteran_discount' => false,
                'manual_discount_percentage' => '',
                'manual_discount_reason' => '',
            ]],
        ], $this->adminUser);

        $this->assertSame([
            'veteran_70_spouses' => 3,
            'martyr_children'    => 2,
        ], $booking->veteran_accommodation_group_usage);
    }

    public function test_check_accommodation_usage_for_types_combines_group_caps(): void
    {
        $policy = $this->veteranPolicyFor($this->accommodation);

        $result = $policy->checkAccommodationUsageForTypes(
            ['veteran_70_spouses', 'martyr_children'],
            1,
            5,
            '8080808080',
        );

        $this->assertSame(5, $result['discounted_nights']);
        $this->assertSame([70, 70, 70, 50, 50], $result['night_discounts']);
        $this->assertSame(6, $result['combined_remaining_discounted_nights']);
    }

    public function test_dual_group_accommodation_with_prior_primary_usage_splits_discount_rates(): void
    {
        $guest = User::create([
            'name'        => 'کاربر تست2',
            'mobile'      => '09364235578',
            'national_id' => '0923983123',
        ]);
        $guest->assignRole('guest');

        \App\Models\Booking::create([
            'user_id'                          => $guest->id,
            'accommodation_id'                 => $this->accommodation->id,
            'veteran_type_applied'             => 'veteran_70_spouses',
            'secondary_veteran_type_applied'   => 'veteran_50_69_dependents',
            'veteran_accommodation_group_usage'=> ['veteran_70_spouses' => 1],
            'booking_source'                   => 'manual',
            'nights'                           => 1,
            'check_in'                         => now()->subWeek(),
            'check_out'                        => now()->subWeek()->addDay(),
            'status'                           => 'confirmed',
            'guests'                           => 4,
            'base_price'                       => 0,
            'discount_percentage'              => 70,
            'discount_amount'                  => 0,
            'total_price'                      => 0,
            'tracking_code'                    => 'USER0923',
        ]);

        $pricing = app(BookingPricingService::class)->calculate([
            'check_in'      => now()->addDays(5)->format('Y-m-d'),
            'check_out'     => now()->addDays(27)->format('Y-m-d'),
            'guests'        => 4,
            'veteran_types' => ['veteran_70_spouses', 'veteran_50_69_dependents'],
            'services'      => [],
            'accommodation' => $this->accommodation,
            'room_type'     => $this->roomType,
            'room_rate'     => $this->roomRate,
            'national_id'   => '0923983123',
            'user_id'       => $guest->id,
        ]);

        $this->assertSame(5, $pricing['veteran_discount_nights']);
        $this->assertSame([
            'veteran_70_spouses'       => 2,
            'veteran_50_69_dependents' => 3,
        ], $pricing['veteran_accommodation_group_usage']);

        $breakdown = collect($pricing['accommodation_discount_breakdown']);
        $primary = $breakdown->firstWhere('veteran_group_key', 'veteran_70_spouses');
        $secondary = $breakdown->firstWhere('veteran_group_key', 'veteran_50_69_dependents');

        $this->assertNotNull($primary);
        $this->assertNotNull($secondary);
        $this->assertSame(2, $primary['units']);
        $this->assertSame(70, $primary['discount_percentage']);
        $this->assertSame(3, $secondary['units']);
        $this->assertSame(50, $secondary['discount_percentage']);
        $this->assertSame(5_600_000, $primary['discount_amount']);
        $this->assertSame(6_000_000, $secondary['discount_amount']);
        $this->assertSame(11_600_000, $pricing['veteran_accommodation_discount_amount']);
    }

    public function test_receipt_breakdown_includes_accommodation_and_service_details(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
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
            'check_in'             => now()->addDays(12)->format('Y-m-d'),
            'check_out'            => now()->addDays(17)->format('Y-m-d'),
            'guests'               => 1,
            'veteran_types'        => ['veteran_70_spouses', 'martyr_children'],
            'booker_national_id'   => '7171717171',
            'payment_method'       => 'cash',
            'guest_contact_name'   => 'مهمان فاکتور',
            'guest_contact_mobile' => '09717171717',
            'services'             => [[
                'service_catalog_id' => $pool->id,
                'name'               => 'استخر',
                'unit_price'         => 500_000,
                'quantity'           => 5,
            ]],
            'guest_details' => [[
                'full_name' => 'مهمان فاکتور',
                'national_id' => '7171717171',
                'mobile' => '09717171717',
                'relation' => 'رزرو‌کننده',
                'excluded_from_veteran_discount' => false,
                'manual_discount_percentage' => '',
                'manual_discount_reason' => '',
            ]],
        ], $this->adminUser);

        $breakdown = app(BookingReceiptBreakdownService::class)->pricingForBooking($booking);

        $this->assertNotEmpty($breakdown['accommodation_discount_breakdown']);
        $this->assertSame(5, $breakdown['veteran_discount_nights']);
        $this->assertSame(5, $breakdown['service_lines'][0]['free_units'] ?? 0);
        $this->assertNotEmpty($breakdown['service_lines'][0]['discount_breakdown'] ?? []);

        $pdf = app(BookingPdfService::class)->render($booking);
        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    private function enableMartyrChildrenPoolFreeSessions(int $weeklyFree): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $group = VeteranGroup::query()
            ->where('accommodation_id', $this->accommodation->id)
            ->where('key', 'martyr_children')
            ->firstOrFail();

        VeteranGroupServiceDiscount::query()
            ->where('veteran_group_id', $group->id)
            ->where('service_catalog_id', $pool->id)
            ->update([
                'discount_percentage'    => 50,
                'free_sessions_eligible' => true,
                'weekly_free_sessions'   => $weeklyFree,
                'use_tiered_discount'    => false,
                'discount_tiers'         => null,
            ]);

        $this->veteranPolicyFor($this->accommodation)->clearCache($this->accommodation->id);
    }
}
