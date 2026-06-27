<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingGuestDetail;
use App\Models\BookingService;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use App\Models\VeteranGroup;
use App\Models\VeteranGroupServiceDiscount;
use App\Services\BookingPricingService;
use App\Services\ManualBookingService;
use App\Services\ServiceDiscountTierEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MultiGroupTieredDiscountTest extends TestCase
{
    use RefreshDatabase;

    private $accommodation;
    private RoomType $roomType;
    private RoomRate $roomRate;
    private User $adminUser;

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
            'name'   => 'ادمین پله‌ای',
            'mobile' => '09000000111',
        ]);
        $this->adminUser->assignRole('super_admin');

        $this->enableDualTieredPoolDiscounts();
    }

    public function test_pricing_combines_five_free_sessions_from_dual_tiered_groups(): void
    {
        $pricing = $this->calculateDualGroupPoolPricing(5);

        $line = $pricing['service_lines'][0];
        $this->assertSame(5, $line['free_units']);
        $this->assertSame(0, $line['line_total']);
        $this->assertTrue($line['use_tiered_discount']);
        $this->assertSame(3, $line['veteran_group_usage']['veteran_70_spouses']);
        $this->assertSame(2, $line['veteran_group_usage']['martyr_children']);
    }

    public function test_pricing_applies_best_tier_after_combined_free_sessions(): void
    {
        $pricing = $this->calculateDualGroupPoolPricing(8);

        $line = $pricing['service_lines'][0];
        $this->assertSame(5, $line['free_units']);
        $this->assertSame(3_650_000, $line['discount_amount']);
        $this->assertSame(350_000, $line['line_total']);
        $this->assertSame(5, $line['veteran_group_usage']['veteran_70_spouses']);
        $this->assertSame(3, $line['veteran_group_usage']['martyr_children']);

        $breakdownTypes = collect($line['discount_breakdown'])->pluck('type')->all();
        $this->assertContains(ServiceDiscountTierEngine::TYPE_FREE, $breakdownTypes);
        $this->assertContains(ServiceDiscountTierEngine::TYPE_FIXED_PAY, $breakdownTypes);

        $secondaryFixed = collect($line['discount_breakdown'])
            ->first(fn ($row) => ($row['veteran_group_key'] ?? null) === 'martyr_children'
                && ($row['type'] ?? '') === ServiceDiscountTierEngine::TYPE_FIXED_PAY);
        $this->assertNotNull($secondaryFixed);
    }

    public function test_cross_booking_continues_each_groups_tier_ladder_independently(): void
    {
        $nationalId = '6060606060';
        $checkIn = now()->startOfWeek(Carbon::SATURDAY)->addDays(2)->format('Y-m-d');

        $this->createPriorDualTieredBooking($nationalId, $checkIn, 4, 500_000);

        $pricing = $this->calculateDualGroupPoolPricing(3, $nationalId, $checkIn);

        $line = $pricing['service_lines'][0];
        // Prior booking consumed 3 primary + 1 secondary sessions (4 free total).
        // New booking: 1 remaining secondary free + 2 primary fixed-pay sessions.
        $this->assertSame(1, $line['free_units']);
        $this->assertSame(1_300_000, $line['discount_amount']);
        $this->assertSame(200_000, $line['line_total']);
        $this->assertSame(2, $line['veteran_group_usage']['veteran_70_spouses']);
        $this->assertSame(1, $line['veteran_group_usage']['martyr_children']);
    }

    public function test_cross_booking_when_primary_free_exhausted_uses_secondary_tiered_free(): void
    {
        $nationalId = '7070707070';
        $checkIn = now()->startOfWeek(Carbon::SATURDAY)->addDays(2)->format('Y-m-d');

        $this->createPriorDualTieredBooking($nationalId, $checkIn, 3, 500_000);

        $pricing = $this->calculateDualGroupPoolPricing(2, $nationalId, $checkIn);

        $line = $pricing['service_lines'][0];
        $this->assertSame(2, $line['free_units']);
        $this->assertSame(0, $line['line_total']);
        $this->assertSame(2, $line['veteran_group_usage']['martyr_children']);
    }

    public function test_manual_booking_persists_dual_tiered_group_usage(): void
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
            'check_in'             => now()->addDays(4)->format('Y-m-d'),
            'check_out'            => now()->addDays(6)->format('Y-m-d'),
            'guests'               => 1,
            'veteran_types'        => ['veteran_70_spouses', 'martyr_children'],
            'booker_national_id'   => '8080808080',
            'payment_method'       => 'cash',
            'guest_contact_name'   => 'مهمان پله‌ای دو گروه',
            'guest_contact_mobile' => '09808080808',
            'services'             => [[
                'service_catalog_id' => $pool->id,
                'name'               => 'استخر',
                'unit_price'         => 500_000,
                'quantity'           => 8,
            ]],
            'guest_details' => [[
                'full_name' => 'مهمان پله‌ای دو گروه',
                'national_id' => '8080808080',
                'mobile' => '09808080808',
                'relation' => 'رزرو‌کننده',
                'excluded_from_veteran_discount' => false,
                'manual_discount_percentage' => '',
                'manual_discount_reason' => '',
            ]],
        ], $this->adminUser);

        $service = $booking->services->first();
        $this->assertSame(5, $service->free_units);
        $this->assertSame(350_000, $service->total);
        $this->assertSame(5, $service->veteran_group_usage['veteran_70_spouses']);
        $this->assertSame(3, $service->veteran_group_usage['martyr_children']);
    }

    public function test_recalculate_totals_preserves_dual_tiered_multi_group_pricing(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $policy = $this->veteranPolicyFor($this->accommodation);

        $services = $policy->enrichServicesWithDiscountsForTypes(
            ['veteran_70_spouses', 'martyr_children'],
            [[
                'service_catalog_id' => $pool->id,
                'name'               => 'استخر',
                'unit_price'         => 500_000,
                'quantity'           => 8,
            ]],
        );

        $pricing = app(BookingPricingService::class)->calculate([
            'check_in'      => now()->addDays(4)->format('Y-m-d'),
            'check_out'     => now()->addDays(6)->format('Y-m-d'),
            'guests'        => 1,
            'veteran_types' => ['veteran_70_spouses', 'martyr_children'],
            'services'      => $services,
            'accommodation' => $this->accommodation,
        ]);

        $guest = User::create([
            'name'        => 'مهمان',
            'mobile'      => '09707070707',
            'national_id' => '9090909090',
        ]);
        $guest->assignRole('guest');

        $booking = Booking::create([
            'user_id'                        => $guest->id,
            'accommodation_id'               => $this->accommodation->id,
            'veteran_type_applied'           => 'veteran_70_spouses',
            'secondary_veteran_type_applied' => 'martyr_children',
            'booking_source'                 => 'manual',
            'check_in'                       => now()->addDays(4),
            'check_out'                      => now()->addDays(6),
            'nights'                         => 2,
            'guests'                         => 1,
            'base_price'                     => 2_000_000,
            'services_subtotal'              => 4_000_000,
            'discount_amount'                => 0,
            'total_price'                    => 6_000_000,
            'status'                         => 'confirmed',
            'tracking_code'                  => 'DUALTIER01',
        ]);

        $line = $pricing['service_lines'][0];
        BookingService::create([
            'booking_id'          => $booking->id,
            'service_catalog_id'  => $pool->id,
            'name'                => 'استخر',
            'unit_price'          => 500_000,
            'quantity'            => 8,
            'free_units'          => $line['free_units'],
            'discount_percentage' => $line['discount_percentage'],
            'discount_amount'     => $line['discount_amount'],
            'total'               => $line['line_total'],
            'veteran_group_usage' => $line['veteran_group_usage'],
            'sort_order'          => 0,
        ]);

        app(ManualBookingService::class)->recalculateTotals($booking->fresh());

        $service = $booking->fresh()->services->first();
        $this->assertSame(5, $service->free_units);
        $this->assertSame(350_000, $service->total);
        $this->assertSame(5, $service->veteran_group_usage['veteran_70_spouses']);
        $this->assertSame(3, $service->veteran_group_usage['martyr_children']);
    }

    public function test_weekly_usage_summary_reflects_dual_tiered_free_quotas(): void
    {
        $policy = $this->veteranPolicyFor($this->accommodation);

        $usage = $policy->weeklyFreeUsageByServiceForTypes(
            ['veteran_70_spouses', 'martyr_children'],
            '9191919191',
            null,
        );

        $this->assertSame(5, $usage['pool']['quota']);
        $this->assertCount(2, $usage['pool']['group_details']);
        $this->assertSame(3, $usage['pool']['group_details'][0]['quota']);
        $this->assertSame(2, $usage['pool']['group_details'][1]['quota']);
    }

    /**
     * @return array<string, mixed>
     */
    private function calculateDualGroupPoolPricing(
        int $quantity,
        ?string $nationalId = null,
        ?string $checkIn = null,
    ): array {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $policy = $this->veteranPolicyFor($this->accommodation);
        $checkIn ??= now()->addDays(5)->format('Y-m-d');

        $services = $policy->enrichServicesWithDiscountsForTypes(
            ['veteran_70_spouses', 'martyr_children'],
            [[
                'service_catalog_id' => $pool->id,
                'name'               => 'استخر',
                'unit_price'         => 500_000,
                'quantity'           => $quantity,
            ]],
        );

        return app(BookingPricingService::class)->calculate([
            'check_in'      => $checkIn,
            'check_out'     => Carbon::parse($checkIn)->addDays(2)->format('Y-m-d'),
            'guests'        => 1,
            'veteran_types' => ['veteran_70_spouses', 'martyr_children'],
            'services'      => $services,
            'accommodation' => $this->accommodation,
            'national_id'   => $nationalId,
        ]);
    }

    private function enableDualTieredPoolDiscounts(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $policy = $this->veteranPolicyFor($this->accommodation);

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

    private function createPriorDualTieredBooking(
        string $nationalId,
        string $checkIn,
        int $qty,
        int $unitPrice,
    ): void {
        $pricing = $this->calculateDualGroupPoolPricing($qty, $nationalId, $checkIn);

        $guest = User::firstOrCreate(
            ['national_id' => $nationalId],
            ['name' => 'مهمان قبلی', 'mobile' => '09606060606'],
        );

        $booking = Booking::create([
            'user_id'                        => $guest->id,
            'accommodation_id'               => $this->accommodation->id,
            'veteran_type_applied'           => 'veteran_70_spouses',
            'secondary_veteran_type_applied' => 'martyr_children',
            'booking_source'                 => 'manual',
            'check_in'                       => $checkIn,
            'check_out'                      => Carbon::parse($checkIn)->addDay(),
            'nights'                         => 1,
            'guests'                         => 1,
            'base_price'                     => 1_000_000,
            'services_subtotal'              => $unitPrice * $qty,
            'discount_amount'                => $pricing['services_discount_amount'],
            'total_price'                    => 1_000_000,
            'status'                         => 'confirmed',
            'tracking_code'                  => strtoupper(substr(md5(uniqid($nationalId, true)), 0, 10)),
        ]);

        $line = $pricing['service_lines'][0];
        BookingService::create([
            'booking_id'          => $booking->id,
            'service_catalog_id'  => $this->veteranCatalog($this->accommodation, 'pool')->id,
            'name'                => 'استخر',
            'unit_price'          => $unitPrice,
            'quantity'            => $qty,
            'free_units'          => $line['free_units'],
            'discount_percentage' => $line['discount_percentage'],
            'discount_amount'     => $line['discount_amount'],
            'total'               => $line['line_total'],
            'veteran_group_usage' => $line['veteran_group_usage'],
            'sort_order'          => 0,
        ]);

        BookingGuestDetail::create([
            'booking_id'  => $booking->id,
            'sort_order'  => 0,
            'full_name'   => 'مهمان',
            'national_id' => $nationalId,
            'mobile'      => '09606060606',
        ]);
    }
}
