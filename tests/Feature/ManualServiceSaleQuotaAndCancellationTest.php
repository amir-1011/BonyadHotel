<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingService;
use App\Models\ServiceCatalogVariant;
use App\Models\User;
use App\Models\VeteranGroup;
use App\Models\VeteranGroupServiceDiscount;
use App\Services\BookingPricingService;
use App\Services\CancellationRequestService;
use App\Services\ManualBookingService;
use App\Services\RefundPolicyService;
use App\Services\ServiceDiscountTierEngine;
use App\Services\VeteranPolicyService;
use Carbon\Carbon;
use Database\Seeders\VeteranPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManualServiceSaleQuotaAndCancellationTest extends TestCase
{
    use RefreshDatabase;

    private $accommodation;

    private $pool;

    private User $staff;

    private ServiceCatalogVariant $poolVariant;

    private int $guestSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VeteranPolicySeeder::class);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $this->accommodation = $this->createTestAccommodation();
        $this->pool = $this->veteranCatalog($this->accommodation, 'pool');
        $this->poolVariant = ServiceCatalogVariant::create([
            'service_catalog_id' => $this->pool->id,
            'key'                => 'session',
            'name'               => 'ورود',
            'price'              => 500_000,
            'sort_order'         => 1,
            'is_active'          => true,
        ]);

        $this->enableTieredPoolDiscount();

        $this->staff = User::create([
            'name'   => 'کاربر تست',
            'mobile' => '09120000777',
        ]);
        $this->staff->assignRole('super_admin');
    }

    /** @return array{user: User, national_id: string} */
    private function veteranGuest(): array
    {
        $this->guestSequence++;
        $nationalId = '4440' . str_pad((string) $this->guestSequence, 6, '0', STR_PAD_LEFT);

        $user = User::create([
            'name'         => 'مهمان ' . $this->guestSequence,
            'mobile'       => '0913' . str_pad((string) $this->guestSequence, 7, '0', STR_PAD_LEFT),
            'national_id'  => $nationalId,
            'veteran_type' => 'veteran_70_spouses',
        ]);

        return ['user' => $user, 'national_id' => $nationalId];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_second_manual_service_sale_same_week_continues_tier_ladder(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');
        $saleDate = '2026-07-22';

        $guest = $this->veteranGuest();
        $first = $this->createServiceSale(3, false, $saleDate, $guest);
        $firstLine = $first->services->first();
        $this->assertSame(3, (int) $firstLine->free_units);

        $policy = $this->veteranPolicyFor($this->accommodation);
        $this->assertSame(3, $policy->usedGroupSessionsInWeek(
            'veteran_70_spouses',
            $guest['national_id'],
            $guest['user']->id,
            $this->pool->id,
            $saleDate,
        ));

        $preview = $this->previewServiceSale(2, $saleDate, $guest);
        $this->assertSame(0, $preview['service_lines'][0]['free_units']);
        $this->assertSame(800_000, $preview['service_lines'][0]['discount_amount']);
        $this->assertSame(200_000, $preview['service_lines'][0]['line_total']);
    }

    public function test_manual_service_sale_counts_prior_room_booking_pool_usage_same_week(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');
        $saleDate = '2026-07-22';

        $guest = $this->veteranGuest();
        $this->createPriorManualStayWithPoolSessions(4, $saleDate, $guest);

        $preview = $this->previewServiceSale(2, $saleDate, $guest);
        $this->assertSame(0, $preview['service_lines'][0]['free_units']);
        $this->assertSame(275_000, $preview['service_lines'][0]['line_total']);
    }

    public function test_manual_service_sale_prior_sale_counts_toward_room_booking_pricing_same_week(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');
        $saleDate = '2026-07-22';

        $guest = $this->veteranGuest();
        $this->createServiceSale(4, false, $saleDate, $guest);

        $policy = $this->veteranPolicyFor($this->accommodation);
        $services = $policy->enrichServicesWithDiscounts('veteran_70_spouses', [[
            'service_catalog_id' => $this->pool->id,
            'name'               => 'استخر',
            'unit_price'         => 500_000,
            'quantity'           => 2,
        ]]);

        $pricing = app(BookingPricingService::class)->calculate([
            'check_in'      => $saleDate,
            'check_out'     => Carbon::parse($saleDate)->addDay()->format('Y-m-d'),
            'guests'        => 1,
            'veteran_type'  => 'veteran_70_spouses',
            'services'      => $services,
            'accommodation' => $this->accommodation,
            'national_id'   => $guest['national_id'],
            'user_id'       => $guest['user']->id,
        ]);

        $this->assertSame(275_000, $pricing['service_lines'][0]['line_total']);
    }

    public function test_cancelled_manual_service_sale_frees_weekly_quota(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');
        $saleDate = '2026-07-22';

        $guest = $this->veteranGuest();
        $booking = $this->createServiceSale(3, false, $saleDate, $guest);
        $booking->update(['status' => 'cancelled']);

        $policy = $this->veteranPolicyFor($this->accommodation);
        $used = $policy->usedFreeSessionsInWeek(
            'veteran_70_spouses',
            $guest['national_id'],
            $guest['user']->id,
            $this->pool->id,
            $saleDate,
        );

        $this->assertSame(0, $used);

        $preview = $this->previewServiceSale(3, $saleDate, $guest);
        $this->assertSame(3, $preview['service_lines'][0]['free_units']);
    }

    public function test_manual_service_sale_next_week_resets_tier_ladder(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');
        $guest = $this->veteranGuest();
        $this->createServiceSale(4, false, '2026-07-22', $guest);

        Carbon::setTestNow('2026-07-28 10:00:00');
        $nextWeek = '2026-07-28';

        $preview = $this->previewServiceSale(3, $nextWeek, $guest);
        $this->assertSame(3, $preview['service_lines'][0]['free_units']);
    }

    public function test_excluded_manual_service_sale_does_not_consume_weekly_quota(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');
        $saleDate = '2026-07-22';

        $guest = $this->veteranGuest();
        $this->createServiceSale(2, true, $saleDate, $guest);

        $policy = $this->veteranPolicyFor($this->accommodation);
        $used = $policy->usedFreeSessionsInWeek(
            'veteran_70_spouses',
            $guest['national_id'],
            $guest['user']->id,
            $this->pool->id,
            $saleDate,
        );

        $this->assertSame(0, $used);

        $preview = $this->previewServiceSale(3, $saleDate, $guest);
        $this->assertSame(3, $preview['service_lines'][0]['free_units']);
    }

    public function test_second_persisted_manual_service_sale_same_week_matches_preview(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');
        $saleDate = '2026-07-22';

        $guest = $this->veteranGuest();
        $this->createServiceSale(3, false, $saleDate, $guest);

        $preview = $this->previewServiceSale(2, $saleDate, $guest);
        $second = $this->createServiceSale(2, false, $saleDate, $guest);

        $line = $second->services->first();
        $this->assertSame($preview['service_lines'][0]['line_total'], $line->total);
        $this->assertSame($preview['service_lines'][0]['discount_amount'], $line->discount_amount);
    }

    public function test_cancellation_request_stores_full_refund_for_manual_service_sale(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');

        $guest = $this->veteranGuest();
        $booking = $this->createServiceSale(1, true, '2026-07-22', $guest);
        $total = (int) $booking->total_price;

        $preview = app(RefundPolicyService::class)->previewForBooking($booking);
        $this->assertSame(100, $preview['percentage']);
        $this->assertSame($total, $preview['amount']);

        $reason = $this->cancellationReason();
        $request = app(CancellationRequestService::class)->create($booking, [
            'cancellation_reason_id' => $reason->id,
            'refund_account_number'  => '6104337812345678',
            'refund_amount'          => $total,
        ], $this->staff);

        $this->assertSame($total, $request->refund_amount);
        $this->assertSame(100, $request->refund_percentage);
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  array{user: User, national_id: string}  $guest
     * @return array<string, mixed>
     */
    private function previewServiceSale(int $quantity, string $referenceDate, array $guest): array
    {
        $policy = $this->veteranPolicyFor($this->accommodation);
        $services = $policy->enrichServicesWithDiscounts('veteran_70_spouses', [[
            'service_catalog_id'         => $this->pool->id,
            'service_catalog_variant_id' => $this->poolVariant->id,
            'name'                       => 'استخر — ورود',
            'unit_price'                 => 500_000,
            'quantity'                   => $quantity,
            'excluded_from_veteran_quota' => false,
        ]]);

        return app(BookingPricingService::class)->calculateManualServiceSale([
            'veteran_type'    => 'veteran_70_spouses',
            'veteran_types'   => ['veteran_70_spouses'],
            'services'        => $services,
            'accommodation'   => $this->accommodation,
            'national_id'     => $guest['national_id'],
            'user_id'         => $guest['user']->id,
            'reference_date'  => $referenceDate,
        ]);
    }

    /**
     * @param  array{user: User, national_id: string}  $guest
     */
    private function createServiceSale(int $quantity, bool $excludedFromQuota, string $referenceDate, array $guest): Booking
    {
        Carbon::setTestNow(Carbon::parse($referenceDate)->setTime(10, 0));

        return app(ManualBookingService::class)->create($this->accommodation, [
            'service_sale_only'      => true,
            'booker_national_id'     => $guest['national_id'],
            'user_id'                => $guest['user']->id,
            'profile_veteran_types'  => ['veteran_70_spouses'],
            'guest_contact_name'     => $guest['user']->name,
            'guest_contact_mobile'   => $guest['user']->mobile,
            'payment_method'         => 'cash',
            'services'               => [[
                'service_catalog_id'          => $this->pool->id,
                'service_catalog_variant_id'  => $this->poolVariant->id,
                'name'                        => 'استخر — ورود',
                'unit_price'                  => 500_000,
                'quantity'                    => $quantity,
                'excluded_from_veteran_quota' => $excludedFromQuota,
                'manual_discount_percentage'  => $excludedFromQuota ? 0 : null,
                'manual_discount_reason'      => $excludedFromQuota ? '' : null,
            ]],
            'guest_details'          => [[
                'full_name'                      => $guest['user']->name,
                'national_id'                    => $guest['national_id'],
                'mobile'                         => $guest['user']->mobile,
                'relation'                       => 'self',
                'excluded_from_veteran_discount' => false,
                'services'                       => [],
            ]],
        ], $this->staff);
    }

    /**
     * @param  array{user: User, national_id: string}  $guest
     */
    private function createPriorManualStayWithPoolSessions(int $quantity, string $checkIn, array $guest): void
    {
        $policy = $this->veteranPolicyFor($this->accommodation);
        $services = $policy->enrichServicesWithDiscounts('veteran_70_spouses', [[
            'service_catalog_id' => $this->pool->id,
            'name'               => 'استخر',
            'unit_price'         => 500_000,
            'quantity'           => $quantity,
        ]]);

        $pricing = app(BookingPricingService::class)->calculate([
            'check_in'      => $checkIn,
            'check_out'     => Carbon::parse($checkIn)->addDay()->format('Y-m-d'),
            'guests'        => 1,
            'veteran_type'  => 'veteran_70_spouses',
            'services'      => $services,
            'accommodation' => $this->accommodation,
            'national_id'   => $guest['national_id'],
            'user_id'       => $guest['user']->id,
        ]);

        $booking = Booking::create([
            'user_id'              => $guest['user']->id,
            'accommodation_id'     => $this->accommodation->id,
            'veteran_type_applied' => 'veteran_70_spouses',
            'booking_source'       => 'manual',
            'check_in'             => $checkIn,
            'check_out'            => Carbon::parse($checkIn)->addDay(),
            'nights'               => 1,
            'guests'               => 1,
            'base_price'           => 1_000_000,
            'services_subtotal'    => 500_000 * $quantity,
            'discount_amount'      => $pricing['services_discount_amount'],
            'total_price'          => 1_000_000,
            'status'               => 'confirmed',
            'tracking_code'        => 'PRIORPOOL1',
        ]);

        $line = $pricing['service_lines'][0];
        BookingService::create([
            'booking_id'         => $booking->id,
            'service_catalog_id' => $this->pool->id,
            'name'               => 'استخر',
            'unit_price'         => 500_000,
            'quantity'           => $quantity,
            'free_units'         => $line['free_units'],
            'discount_amount'    => $line['discount_amount'],
            'total'              => $line['line_total'],
        ]);

        \App\Models\BookingGuestDetail::create([
            'booking_id'  => $booking->id,
            'sort_order'  => 0,
            'full_name'   => 'مهمان',
            'national_id' => $guest['national_id'],
            'mobile'      => $guest['user']->mobile,
        ]);
    }

    private function cancellationReason(): \App\Models\CancellationReason
    {
        return \App\Models\CancellationReason::query()
            ->forAccommodation($this->accommodation->id)
            ->active()
            ->ordered()
            ->firstOrFail();
    }

    private function enableTieredPoolDiscount(): void
    {
        $group = VeteranGroup::query()
            ->where('accommodation_id', $this->accommodation->id)
            ->where('key', 'veteran_70_spouses')
            ->firstOrFail();

        $payload = ServiceDiscountTierEngine::matrixRowToPersistence([
            'use_tiered_discount' => true,
            'discount_tiers'      => [
                ['type' => ServiceDiscountTierEngine::TYPE_FREE, 'session_count' => 3],
                ['type' => ServiceDiscountTierEngine::TYPE_FIXED_PAY, 'session_count' => 2, 'pay_amount' => 100_000],
                ['type' => ServiceDiscountTierEngine::TYPE_PERCENTAGE, 'session_count' => null, 'discount_percentage' => 65],
            ],
        ]);

        VeteranGroupServiceDiscount::query()
            ->where('veteran_group_id', $group->id)
            ->where('service_catalog_id', $this->pool->id)
            ->update($payload);

        $this->veteranPolicyFor($this->accommodation)->clearCache($this->accommodation->id);
    }
}
