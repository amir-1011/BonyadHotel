<?php

namespace Tests\Feature;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\BookingGuestDetail;
use App\Models\BookingService;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\ServiceCatalogVariant;
use App\Models\User;
use App\Models\VeteranGroup;
use App\Models\VeteranGroupServiceDiscount;
use App\Services\BookingPricingService;
use App\Services\BookingReceiptBreakdownService;
use App\Services\BookingStayExtensionService;
use App\Services\ManualBookingService;
use App\Services\ServiceDiscountTierEngine;
use App\Services\VeteranPolicyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingStayExtensionPricingTest extends TestCase
{
    use RefreshDatabase;

    private Accommodation $accommodation;
    private RoomType $roomType;
    private RoomRate $roomRate;
    private Room $room;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $this->accommodation = $this->createTestAccommodation();
        $this->roomType = RoomType::create([
            'accommodation_id' => $this->accommodation->id,
            'name'             => 'دو تخته',
            'capacity'         => 2,
            'room_count'       => 5,
            'is_active'        => true,
        ]);
        $this->roomRate = RoomRate::create([
            'room_type_id'    => $this->roomType->id,
            'name'            => 'استاندارد',
            'price_per_night' => 1_000_000,
            'is_active'       => true,
        ]);
        $this->room = Room::create([
            'room_type_id' => $this->roomType->id,
            'name'         => '۱۰۱',
            'is_active'    => true,
        ]);

        $this->admin = User::create([
            'name'   => 'ادمین تمدید',
            'mobile' => '09120000777',
        ]);
        $this->admin->assignRole('super_admin');
    }

    public function test_extension_recalculates_veteran70_totals_for_added_nights_within_period_cap(): void
    {
        $checkIn = now()->addDays(12)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');
        $booking = $this->createVeteranBooking('veteran_70_spouses', '1111111111', $checkIn, $checkOut);

        $this->assertSame(600_000, $booking->total_price);
        $this->assertSame(2, $booking->nights);

        $newCheckOut = Carbon::parse($checkIn)->addDays(4)->format('Y-m-d');
        $updated = $this->extend($booking, $newCheckOut);

        $expected = $this->expectedPricingForExtendedBooking($booking, $newCheckOut);

        $this->assertSame(4, $updated->nights);
        $this->assertSame($expected['total_price'], $updated->total_price);
        $this->assertSame($expected['discount_amount'], $updated->discount_amount);
        $this->assertSame(1_900_000, $updated->total_price);
    }

    public function test_extension_beyond_period_cap_charges_full_rate_for_extra_nights(): void
    {
        $checkIn = now()->addDays(15)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(3)->format('Y-m-d');
        $booking = $this->createVeteranBooking('veteran_70_spouses', '2222222222', $checkIn, $checkOut);

        $this->assertSame(900_000, $booking->total_price);

        $newCheckOut = Carbon::parse($checkIn)->addDays(5)->format('Y-m-d');
        $updated = $this->extend($booking, $newCheckOut);

        $expected = $this->expectedPricingForExtendedBooking($booking, $newCheckOut);

        $this->assertSame(5, $updated->nights);
        $this->assertSame($expected['total_price'], $updated->total_price);
        $this->assertSame(2_900_000, $updated->total_price);
        $this->assertSame(
            ['veteran_70_spouses' => 3],
            $updated->veteran_accommodation_group_usage,
        );
    }

    public function test_extension_with_prior_period_usage_recalculates_remaining_discounted_nights(): void
    {
        $nationalId = '3333333333';
        $mobile = '09123333333';
        $checkIn = now()->addDays(20)->format('Y-m-d');

        $this->createPriorPeriodBooking($nationalId, $mobile, now()->subMonth()->format('Y-m-d'), 2);

        $checkOut = Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');
        $booking = $this->createVeteranBooking(
            'veteran_70_spouses',
            $nationalId,
            $checkIn,
            $checkOut,
            guestDetails: [[
                'full_name'   => 'مهمان تمدید',
                'national_id' => $nationalId,
                'mobile'      => $mobile,
                'relation'    => 'رزرو‌کننده',
            ]],
        );

        $this->assertSame(1_300_000, $booking->total_price);

        $newCheckOut = Carbon::parse($checkIn)->addDays(4)->format('Y-m-d');
        $updated = $this->extend($booking, $newCheckOut);
        $expected = $this->expectedPricingForExtendedBooking($booking, $newCheckOut);

        $this->assertSame($expected['total_price'], $updated->total_price);
        $this->assertSame(3_300_000, $updated->total_price);
        $this->assertSame(1, $this->discountedNightsFromPricing($expected));
    }

    public function test_extension_rebuilds_period_cap_from_full_stay_not_incremental_usage(): void
    {
        $nationalId = '4444444444';
        $checkIn = now()->addDays(25)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(3)->format('Y-m-d');
        $booking = $this->createVeteranBooking('veteran_70_spouses', $nationalId, $checkIn, $checkOut);

        $this->assertSame(900_000, $booking->total_price);
        $this->assertSame(['veteran_70_spouses' => 3], $booking->veteran_accommodation_group_usage);

        $newCheckOut = Carbon::parse($checkIn)->addDays(5)->format('Y-m-d');
        $updated = $this->extend($booking, $newCheckOut);

        $this->assertSame(2_900_000, $updated->total_price);
        $this->assertSame(['veteran_70_spouses' => 3], $updated->veteran_accommodation_group_usage);
        $this->assertGreaterThan(
            $booking->total_price + (2 * 300_000),
            $updated->total_price,
            'Extension must bill two extra nights at full rate once the period cap is exhausted.',
        );
    }

    public function test_extension_recalculates_veteran50_accommodation_and_pool_service_discounts(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $checkIn = now()->addDays(30)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');

        $booking = $this->createVeteranBooking(
            'veteran_50_69_dependents',
            '5555555555',
            $checkIn,
            $checkOut,
            services: [[
                'service_catalog_id' => $pool->id,
                'name'               => 'استخر',
                'unit_price'         => 200_000,
                'quantity'           => 2,
            ]],
        );

        $poolServiceBefore = $booking->services->first();
        $this->assertSame(65, $poolServiceBefore->discount_percentage);
        $this->assertSame(1_140_000, $booking->total_price);

        $newCheckOut = Carbon::parse($checkIn)->addDays(5)->format('Y-m-d');
        $updated = $this->extend($booking, $newCheckOut);
        $expected = $this->expectedPricingForExtendedBooking($booking, $newCheckOut);

        $poolServiceAfter = $updated->services->first();
        $this->assertSame($expected['total_price'], $updated->total_price);
        $this->assertSame((int) round(2 * 200_000 * 65 / 100), $poolServiceAfter->discount_amount);
        $this->assertSame(400_000 - (int) round(2 * 200_000 * 65 / 100), $poolServiceAfter->total);
        $this->assertGreaterThan($booking->base_price, $updated->base_price);
    }

    public function test_extension_preserves_veteran70_pool_free_sessions_while_increasing_room_total(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $checkIn = now()->addDays(35)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');

        $booking = $this->createVeteranBooking(
            'veteran_70_spouses',
            '6666666666',
            $checkIn,
            $checkOut,
            services: [[
                'service_catalog_id' => $pool->id,
                'name'               => 'استخر',
                'unit_price'         => 150_000,
                'quantity'           => 2,
            ]],
        );

        $serviceBefore = $booking->services->first();
        $this->assertSame(300_000, $serviceBefore->discount_amount);
        $this->assertSame(0, $serviceBefore->total);

        $newCheckOut = Carbon::parse($checkIn)->addDays(4)->format('Y-m-d');
        $updated = $this->extend($booking, $newCheckOut);
        $serviceAfter = $updated->services->first();

        $this->assertSame(300_000, $serviceAfter->discount_amount);
        $this->assertSame(0, $serviceAfter->total);
        $this->assertGreaterThan($booking->total_price, $updated->total_price);
        $this->assertSame(1_900_000, $updated->total_price);
    }

    public function test_extension_preserves_mixed_veteran_quota_and_excluded_services(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $variant = ServiceCatalogVariant::create([
            'service_catalog_id' => $pool->id,
            'key'                => 'pool_extension_mix',
            'name'               => 'استخر ترکیبی',
            'price'              => 200_000,
            'sort_order'         => 1,
            'is_active'          => true,
        ]);
        $checkIn = now()->addDays(40)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');

        $booking = $this->createVeteranBooking(
            'veteran_70_spouses',
            '7777777777',
            $checkIn,
            $checkOut,
            services: [
                [
                    'service_catalog_id'         => $pool->id,
                    'service_catalog_variant_id' => $variant->id,
                    'name'                       => 'استخر',
                    'unit_price'                 => 200_000,
                    'quantity'                   => 1,
                    'guest_sort_order'           => 0,
                ],
                [
                    'service_catalog_id'         => $pool->id,
                    'service_catalog_variant_id' => $variant->id,
                    'name'                       => 'استخر VIP',
                    'unit_price'                 => 200_000,
                    'quantity'                   => 1,
                    'guest_sort_order'           => 0,
                    'excluded_from_veteran_quota' => true,
                    'manual_discount_percentage' => 25,
                    'manual_discount_reason'     => 'پرداخت مستقیم',
                ],
            ],
        );

        [$quotaService, $excludedService] = $booking->services->values()->all();
        $this->assertFalse($quotaService->excluded_from_veteran_quota);
        $this->assertTrue($excludedService->excluded_from_veteran_quota);
        $this->assertSame(200_000, $quotaService->discount_amount);
        $this->assertSame(50_000, $excludedService->discount_amount);

        $newCheckOut = Carbon::parse($checkIn)->addDays(3)->format('Y-m-d');
        $updated = $this->extend($booking, $newCheckOut);
        $expected = $this->expectedPricingForExtendedBooking($booking, $newCheckOut);

        [$quotaAfter, $excludedAfter] = $updated->services->values()->all();
        $expectedLines = $expected['service_lines'];

        $this->assertSame($expected['total_price'], $updated->total_price);
        $this->assertSame($expectedLines[0]['discount_amount'], $quotaAfter->discount_amount);
        $this->assertSame($expectedLines[0]['line_total'], $quotaAfter->total);
        $this->assertSame($expectedLines[1]['discount_amount'], $excludedAfter->discount_amount);
        $this->assertSame($expectedLines[1]['line_total'], $excludedAfter->total);
    }

    public function test_extension_with_excluded_guest_recalculates_multi_guest_accommodation(): void
    {
        $checkIn = now()->addDays(45)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');

        $booking = $this->createVeteranBooking(
            'veteran_70_spouses',
            '8888888888',
            $checkIn,
            $checkOut,
            guests: 2,
            guestDetails: [
                ['full_name' => 'مهمان اصلی', 'national_id' => '8888888888', 'mobile' => '09128888888', 'relation' => 'رزرو‌کننده', 'excluded_from_veteran_discount' => false],
                ['full_name' => 'مهمان دوم', 'national_id' => '8888888889', 'mobile' => '09128888889', 'relation' => 'همراه', 'excluded_from_veteran_discount' => true],
            ],
        );

        $this->assertSame(2_600_000, $booking->total_price);

        $newCheckOut = Carbon::parse($checkIn)->addDays(4)->format('Y-m-d');
        $updated = $this->extend($booking, $newCheckOut);
        $expected = $this->expectedPricingForExtendedBooking($booking, $newCheckOut);

        $this->assertSame($expected['total_price'], $updated->total_price);
        $this->assertGreaterThan($booking->total_price, $updated->total_price);
    }

    public function test_extension_with_children_under_6_recalculates_child_and_veteran_discount(): void
    {
        $this->accommodation->update([
            'children_under_6_allocate_bed' => false,
            'children_under_6_discount_percentage' => 50,
        ]);

        $checkIn = now()->addDays(50)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');

        $booking = $this->createVeteranBooking(
            'veteran_70_spouses',
            '9999999999',
            $checkIn,
            $checkOut,
            guests: 2,
            childrenUnder6: 1,
            guestDetails: [
                ['full_name' => 'مهمان اصلی', 'national_id' => '9999999999', 'mobile' => '09129999999', 'relation' => 'رزرو‌کننده'],
                ['full_name' => '', 'national_id' => '', 'mobile' => '', 'relation' => ''],
            ],
        );

        $this->assertSame(900_000, $booking->total_price);

        $newCheckOut = Carbon::parse($checkIn)->addDays(3)->format('Y-m-d');
        $updated = $this->extend($booking, $newCheckOut);
        $expected = $this->expectedPricingForExtendedBooking($booking, $newCheckOut);

        $this->assertSame($expected['total_price'], $updated->total_price);
        $this->assertGreaterThan($booking->total_price, $updated->total_price);
        $this->assertGreaterThan($booking->discount_amount, $updated->discount_amount);
    }

    public function test_extension_with_manual_guest_discount_recalculates_non_veteran_booking(): void
    {
        $checkIn = now()->addDays(55)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');

        $booking = $this->createVeteranBooking(
            veteranType: null,
            nationalId: '1010101010',
            checkIn: $checkIn,
            checkOut: $checkOut,
            guests: 2,
            guestDetails: [
                ['full_name' => 'مهمان اصلی', 'national_id' => '1010101010', 'mobile' => '09121010101', 'relation' => 'رزرو‌کننده', 'manual_discount_percentage' => 20, 'manual_discount_reason' => 'همکاری'],
                ['full_name' => 'مهمان دوم', 'national_id' => '', 'mobile' => '', 'relation' => ''],
            ],
        );

        $this->assertSame(3_600_000, $booking->total_price);

        $newCheckOut = Carbon::parse($checkIn)->addDays(4)->format('Y-m-d');
        $updated = $this->extend($booking, $newCheckOut);
        $expected = $this->expectedPricingForExtendedBooking($booking, $newCheckOut);

        $this->assertSame($expected['total_price'], $updated->total_price);
        $this->assertSame(7_200_000, $updated->total_price);
        $this->assertSame(800_000, $updated->discount_amount);
    }

    public function test_extension_preserves_dual_veteran_group_service_usage(): void
    {
        $this->enableDualTieredPoolDiscounts();
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $checkIn = now()->addDays(60)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');

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
            'veteran_types'        => ['veteran_70_spouses', 'martyr_children'],
            'booker_national_id'   => '1212121212',
            'payment_method'       => 'cash',
            'guest_contact_name'   => 'مهمان دو گروه',
            'guest_contact_mobile' => '09121212121',
            'services'             => [[
                'service_catalog_id' => $pool->id,
                'name'               => 'استخر',
                'unit_price'         => 500_000,
                'quantity'           => 8,
            ]],
            'guest_details' => [[
                'full_name' => 'مهمان دو گروه',
                'national_id' => '1212121212',
                'mobile' => '09121212121',
                'relation' => 'رزرو‌کننده',
            ]],
        ], $this->admin);

        $serviceBefore = $booking->services->first();
        $this->assertSame(5, $serviceBefore->free_units);
        $this->assertSame(350_000, $serviceBefore->total);
        $this->assertSame(5, $serviceBefore->veteran_group_usage['veteran_70_spouses']);
        $this->assertSame(3, $serviceBefore->veteran_group_usage['martyr_children']);

        $newCheckOut = Carbon::parse($checkIn)->addDays(4)->format('Y-m-d');
        $updated = $this->extend($booking, $newCheckOut);
        $serviceAfter = $updated->services->first();

        $this->assertSame(5, $serviceAfter->free_units);
        $this->assertSame(350_000, $serviceAfter->total);
        $this->assertSame(5, $serviceAfter->veteran_group_usage['veteran_70_spouses']);
        $this->assertSame(3, $serviceAfter->veteran_group_usage['martyr_children']);
        $this->assertGreaterThan($booking->total_price, $updated->total_price);
    }

    public function test_extension_receipt_breakdown_matches_persisted_booking_totals(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $checkIn = now()->addDays(65)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');

        $booking = $this->createVeteranBooking(
            'veteran_70_spouses',
            '1313131313',
            $checkIn,
            $checkOut,
            services: [[
                'service_catalog_id' => $pool->id,
                'name'               => 'استخر',
                'unit_price'         => 100_000,
                'quantity'           => 3,
            ]],
        );

        $newCheckOut = Carbon::parse($checkIn)->addDays(5)->format('Y-m-d');
        $updated = $this->extend($booking, $newCheckOut);
        $breakdown = app(BookingReceiptBreakdownService::class)->pricingForBooking($updated);

        $this->assertSame($updated->total_price, $breakdown['total_price']);
        $this->assertSame($updated->discount_amount, $breakdown['discount_amount']);
        $this->assertSame($updated->nights, $breakdown['nights']);
        $this->assertSame(5, $breakdown['nights']);
        $this->assertCount(1, $breakdown['service_lines']);
        $this->assertSame($updated->services->first()->total, $breakdown['service_lines'][0]['line_total']);
    }

    public function test_extension_updates_nights_on_booking_room_lines_context(): void
    {
        $checkIn = now()->addDays(70)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');
        $booking = $this->createVeteranBooking('veteran_25_49_dependents', '1414141414', $checkIn, $checkOut);

        $this->assertSame(40, $booking->discount_percentage);
        $this->assertSame(1_200_000, $booking->total_price);

        $newCheckOut = Carbon::parse($checkIn)->addDays(3)->format('Y-m-d');
        $updated = $this->extend($booking, $newCheckOut);

        $this->assertSame(3, $updated->nights);
        $this->assertSame($this->expectedPricingForExtendedBooking($booking, $newCheckOut)['total_price'], $updated->total_price);
        $this->assertSame(1_800_000, $updated->total_price);
    }

    public function test_extension_does_not_reprice_program_booking_totals(): void
    {
        $program = app(\App\Services\ProgramBookingService::class)->create(
            $this->accommodation->fresh(),
            [
                'title'               => 'اردوی قیمت ثابت',
                'program_type'        => \App\Models\Program::TYPE_CAMP,
                'program_employer_id' => \App\Models\ProgramEmployer::create([
                    'province_id'             => $this->ensureTestProvinceId(),
                    'name'                    => 'کارفرما',
                    'employer_code'           => '515110',
                    'national_or_economic_id' => '2233445567',
                    'mobile'                  => '09127778890',
                ])->id,
                'guest_count'         => 1,
                'rooms_allocated'     => 1,
                'check_in'            => now()->addDays(75)->format('Y-m-d'),
                'check_out'           => now()->addDays(78)->format('Y-m-d'),
                'base_price'          => 8_000_000,
                'discount_amount'     => 0,
                'deposit_amount'      => 0,
                'room_lines'          => [[
                    'room_type_id' => $this->roomType->id,
                    'room_rate_id' => $this->roomRate->id,
                    'room_id'      => $this->room->id,
                    'adults'       => 1,
                    'guests'       => 1,
                ]],
                'guest_details' => [[
                    'full_name'       => 'مهمان اردو',
                    'national_id'     => '1515151515',
                    'mobile'          => '09121515151',
                    'relation'        => 'مهمان اصلی',
                    'room_line_index' => 0,
                    'sort_order'      => 0,
                ]],
            ],
            $this->admin,
        );

        $booking = $program->booking;
        $originalTotal = $booking->total_price;

        $newCheckOut = $booking->check_out->copy()->addDays(2)->format('Y-m-d');
        $updated = $this->extend($booking, $newCheckOut);

        $this->assertSame(5, $updated->nights);
        $this->assertSame($originalTotal, $updated->total_price);
        $this->assertSame(8_000_000, $program->fresh()->total_amount);
    }

    /**
     * @param  array<int, array<string, mixed>>  $services
     * @param  array<int, array<string, mixed>>  $guestDetails
     */
    private function createVeteranBooking(
        ?string $veteranType,
        string $nationalId,
        string $checkIn,
        string $checkOut,
        array $services = [],
        int $guests = 1,
        int $childrenUnder6 = 0,
        array $guestDetails = [],
    ): Booking {
        if ($guestDetails === []) {
            $guestDetails = [[
                'full_name'   => 'مهمان تمدید',
                'national_id' => $nationalId,
                'mobile'      => '0912' . substr($nationalId, -7),
                'relation'    => 'رزرو‌کننده',
            ]];
        }

        $payload = [
            'room_lines' => [[
                'room_type_id'     => $this->roomType->id,
                'room_rate_id'     => $this->roomRate->id,
                'adults'           => max(1, $guests - $childrenUnder6),
                'children_under_6' => $childrenUnder6,
                'guests'           => $guests,
                'extra_guests'     => 0,
                'bill_full_rooms'  => false,
            ]],
            'check_in'             => $checkIn,
            'check_out'            => $checkOut,
            'guests'               => $guests,
            'children_under_6'     => $childrenUnder6,
            'booker_national_id'   => $nationalId,
            'payment_method'       => 'cash',
            'guest_contact_name'   => $guestDetails[0]['full_name'] ?? 'مهمان تمدید',
            'guest_contact_mobile' => $guestDetails[0]['mobile'] ?? '09120000000',
            'services'             => $services,
            'guest_details'        => $guestDetails,
        ];

        if ($veteranType !== null) {
            $payload['veteran_type'] = $veteranType;
        }

        return app(ManualBookingService::class)->create(
            $this->accommodation,
            $payload,
            $this->admin,
        );
    }

    private function createPriorPeriodBooking(string $nationalId, string $mobile, string $checkIn, int $nights): void
    {
        $guest = User::firstOrCreate(
            ['national_id' => $nationalId],
            ['name' => 'مهمان قبلی', 'mobile' => $mobile],
        );
        $guest->assignRole('guest');

        Booking::create([
            'user_id'                           => $guest->id,
            'accommodation_id'                  => $this->accommodation->id,
            'veteran_type_applied'            => 'veteran_70_spouses',
            'veteran_accommodation_group_usage' => ['veteran_70_spouses' => $nights],
            'booking_source'                  => 'manual',
            'nights'                          => $nights,
            'check_in'                        => $checkIn,
            'check_out'                       => Carbon::parse($checkIn)->addDays($nights)->format('Y-m-d'),
            'status'                          => 'confirmed',
            'guests'                          => 1,
            'base_price'                      => 0,
            'discount_percentage'             => 70,
            'discount_amount'                 => 0,
            'total_price'                     => 0,
            'tracking_code'                   => 'PRIOR' . random_int(1000, 9999),
        ]);
    }

    private function extend(Booking $booking, string $newCheckOut): Booking
    {
        return app(BookingStayExtensionService::class)->extendCheckout($booking, $newCheckOut);
    }

    /**
     * @return array<string, mixed>
     */
    private function expectedPricingForExtendedBooking(Booking $booking, string $newCheckOut): array
    {
        $booking = $booking->fresh([
            'accommodation',
            'services',
            'guestDetails',
            'bookingRooms.roomType',
            'bookingRooms.roomRate',
            'roomType',
            'roomRate',
        ]);

        $services = $booking->services->map(fn ($service) => [
            'name'                        => $service->name,
            'unit_price'                  => $service->unit_price,
            'quantity'                    => $service->quantity,
            'service_catalog_id'          => $service->service_catalog_id,
            'service_catalog_variant_id'  => $service->service_catalog_variant_id,
            'guest_sort_order'            => $service->guest_sort_order,
            'excluded_from_veteran_quota' => $service->excluded_from_veteran_quota,
            'manual_discount_percentage'  => $service->manual_discount_percentage,
            'manual_discount_reason'      => $service->manual_discount_reason,
            'discount_override'           => null,
        ])->all();

        $guestDetails = $booking->guestDetails->map(fn ($guest) => [
            'excluded_from_veteran_discount' => $guest->excluded_from_veteran_discount,
            'manual_discount_percentage'     => $guest->manual_discount_percentage,
            'manual_discount_reason'         => $guest->manual_discount_reason,
        ])->all();

        $billingGuests = max(1, (int) $booking->guests - (int) $booking->extra_guests);
        $veteranTypes = $booking->veteranTypesApplied();
        $veteranDiscountPct = \App\Support\VeteranGroups::accommodationDiscountForTypes(
            $veteranTypes,
            $booking->accommodation_id,
        );

        $perGuestSlots = app(BookingPricingService::class)->buildPerGuestSlotsFromGuestDetails(
            $guestDetails,
            $billingGuests,
            (int) ($booking->children_under_6 ?? 0),
            $veteranTypes[0] ?? null,
            $veteranDiscountPct,
        );

        $params = [
            'check_in'            => $booking->check_in->format('Y-m-d'),
            'check_out'           => $newCheckOut,
            'guests'              => $booking->guests,
            'children_under_6'    => $booking->children_under_6 ?? 0,
            'extra_guests'        => $booking->extra_guests,
            'bill_full_rooms'     => false,
            'veteran_type'        => $veteranTypes[0] ?? null,
            'secondary_veteran_type' => $veteranTypes[1] ?? null,
            'veteran_types'       => $veteranTypes,
            'services'            => $services,
            'accommodation'       => $booking->accommodation,
            'national_id'         => $booking->guestDetails->value('national_id') ?? $booking->user?->national_id,
            'user_id'             => $booking->user_id,
            'exclude_booking_id'  => $booking->id,
            'non_veteran_discount_guests' => $booking->guestDetails
                ->where('excluded_from_veteran_discount', true)
                ->count(),
            'per_guest_slots'     => $perGuestSlots,
        ];

        if ($booking->bookingRooms->isNotEmpty()) {
            $params['room_lines'] = $booking->bookingRooms->map(fn ($line) => [
                'room_type'        => $line->roomType,
                'room_rate'        => $line->roomRate,
                'guests'           => $line->guests,
                'children_under_6' => $line->children_under_6,
                'extra_guests'     => $line->extra_guests,
                'bill_full_rooms'  => $line->bill_full_rooms,
            ])->all();
        } else {
            $params['room_type'] = $booking->roomType;
            $params['room_rate'] = $booking->roomRate;
        }

        return app(BookingPricingService::class)->calculate($params);
    }

    /**
     * @param  array<string, mixed>  $pricing
     */
    private function discountedNightsFromPricing(array $pricing): int
    {
        return count(array_filter(
            $pricing['accommodation_discount_breakdown'] ?? [],
            fn (array $row) => ($row['discount_percentage'] ?? 0) > 0,
        ));
    }

    private function enableDualTieredPoolDiscounts(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $policy = app(VeteranPolicyService::class)->forAccommodation($this->accommodation->id);

        $this->enableTieredDiscount($pool->id, 'veteran_70_spouses', [
            ['type' => ServiceDiscountTierEngine::TYPE_FREE, 'session_count' => 3],
            ['type' => ServiceDiscountTierEngine::TYPE_FIXED_PAY, 'session_count' => 2, 'pay_amount' => 100_000],
            ['type' => ServiceDiscountTierEngine::TYPE_PERCENTAGE, 'session_count' => null, 'discount_percentage' => 65],
        ]);

        $this->enableTieredDiscount($pool->id, 'martyr_children', [
            ['type' => ServiceDiscountTierEngine::TYPE_FREE, 'session_count' => 2],
            ['type' => ServiceDiscountTierEngine::TYPE_FIXED_PAY, 'session_count' => 1, 'pay_amount' => 150_000],
            ['type' => ServiceDiscountTierEngine::TYPE_PERCENTAGE, 'session_count' => null, 'discount_percentage' => 50],
        ]);

        $policy->clearCache($this->accommodation->id);
    }

    /**
     * @param  array<int, array<string, mixed>>  $tiers
     */
    private function enableTieredDiscount(int $serviceId, string $groupKey, array $tiers): void
    {
        $group = VeteranGroup::query()
            ->where('accommodation_id', $this->accommodation->id)
            ->where('key', $groupKey)
            ->firstOrFail();

        $payload = ServiceDiscountTierEngine::matrixRowToPersistence([
            'use_tiered_discount' => true,
            'discount_tiers'      => $tiers,
        ]);

        VeteranGroupServiceDiscount::query()
            ->where('veteran_group_id', $group->id)
            ->where('service_catalog_id', $serviceId)
            ->update($payload);
    }
}
