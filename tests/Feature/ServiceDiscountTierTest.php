<?php

namespace Tests\Feature;

use App\Models\ServiceCatalog;
use App\Models\VeteranGroup;
use App\Models\VeteranGroupServiceDiscount;
use App\Services\BookingPricingService;
use App\Services\ServiceDiscountTierEngine;
use App\Services\VeteranPolicyProvisioner;
use App\Services\VeteranPolicyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServiceDiscountTierTest extends TestCase
{
    use RefreshDatabase;

    private $accommodation;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $provinceId = DB::table('provinces')->insertGetId(['name' => 'استان تست', 'created_at' => now(), 'updated_at' => now()]);
        $cityId = DB::table('cities')->insertGetId(['province_id' => $provinceId, 'name' => 'شهر تست', 'created_at' => now(), 'updated_at' => now()]);

        $this->accommodation = $this->createTestAccommodation(['city_id' => $cityId]);
    }

    public function test_tiered_pool_pricing_with_three_steps(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $this->enableTieredDiscount($pool->id, 'veteran_70_spouses', [
            ['type' => ServiceDiscountTierEngine::TYPE_FREE, 'session_count' => 3],
            ['type' => ServiceDiscountTierEngine::TYPE_FIXED_PAY, 'session_count' => 2, 'pay_amount' => 100_000],
            ['type' => ServiceDiscountTierEngine::TYPE_PERCENTAGE, 'session_count' => null, 'discount_percentage' => 65],
        ]);

        $policy = $this->veteranPolicyFor($this->accommodation);
        $policy->clearCache($this->accommodation->id);

        $services = $policy->enrichServicesWithDiscounts('veteran_70_spouses', [[
            'service_catalog_id' => $pool->id,
            'name'               => 'استخر — نشاط',
            'unit_price'         => 500_000,
            'quantity'           => 6,
        ]]);

        $pricing = app(BookingPricingService::class)->calculate([
            'check_in'      => now()->addDays(5)->format('Y-m-d'),
            'check_out'     => now()->addDays(7)->format('Y-m-d'),
            'guests'        => 1,
            'veteran_type'  => 'veteran_70_spouses',
            'services'      => $services,
            'accommodation' => $this->accommodation,
        ]);

        $line = $pricing['service_lines'][0];
        $this->assertSame(3, $line['free_units']);
        $this->assertSame(2_625_000, $line['discount_amount']);
        $this->assertSame(375_000, $line['line_total']);
    }

    public function test_tiered_fixed_pay_same_for_different_variant_prices(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $this->enableTieredDiscount($pool->id, 'veteran_50_69_dependents', [
            ['type' => ServiceDiscountTierEngine::TYPE_FIXED_PAY, 'session_count' => null, 'pay_amount' => 100_000],
        ]);

        $policy = $this->veteranPolicyFor($this->accommodation);
        $policy->clearCache($this->accommodation->id);

        foreach ([500_000, 600_000] as $unitPrice) {
            $services = $policy->enrichServicesWithDiscounts('veteran_50_69_dependents', [[
                'service_catalog_id' => $pool->id,
                'name'               => 'استخر',
                'unit_price'         => $unitPrice,
                'quantity'           => 1,
            ]]);

            $pricing = app(BookingPricingService::class)->calculate([
                'check_in'      => now()->addDays(5)->format('Y-m-d'),
                'check_out'     => now()->addDays(7)->format('Y-m-d'),
                'guests'        => 1,
                'veteran_type'  => 'veteran_50_69_dependents',
                'services'      => $services,
                'accommodation' => $this->accommodation,
            ]);

            $this->assertSame(100_000, $pricing['service_lines'][0]['line_total']);
        }
    }

    public function test_tiered_cross_booking_continues_ladder(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $this->enableTieredDiscount($pool->id, 'veteran_70_spouses', [
            ['type' => ServiceDiscountTierEngine::TYPE_FREE, 'session_count' => 3],
            ['type' => ServiceDiscountTierEngine::TYPE_FIXED_PAY, 'session_count' => 2, 'pay_amount' => 100_000],
            ['type' => ServiceDiscountTierEngine::TYPE_PERCENTAGE, 'session_count' => null, 'discount_percentage' => 65],
        ]);

        $policy = $this->veteranPolicyFor($this->accommodation);
        $policy->clearCache($this->accommodation->id);

        $nationalId = '3030303030';
        $checkIn = now()->startOfWeek(Carbon::SATURDAY)->addDays(2)->format('Y-m-d');

        // Simulate 4 prior sessions this week (3 free + 1 fixed)
        $this->createPriorTieredPoolBooking($nationalId, $checkIn, 4, 500_000);

        $services = $policy->enrichServicesWithDiscounts('veteran_70_spouses', [[
            'service_catalog_id' => $pool->id,
            'name'               => 'استخر',
            'unit_price'         => 500_000,
            'quantity'           => 2,
        ]]);

        $pricing = app(BookingPricingService::class)->calculate([
            'check_in'      => $checkIn,
            'check_out'     => Carbon::parse($checkIn)->addDay()->format('Y-m-d'),
            'guests'        => 1,
            'veteran_type'  => 'veteran_70_spouses',
            'services'      => $services,
            'accommodation' => $this->accommodation,
            'national_id'   => $nationalId,
        ]);

        // Sessions 5 fixed + session 6 at 65%
        $this->assertSame(0, $pricing['service_lines'][0]['free_units']);
        $this->assertSame(725_000, $pricing['service_lines'][0]['discount_amount']);
        $this->assertSame(275_000, $pricing['service_lines'][0]['line_total']);
    }

    public function test_legacy_mode_still_works_when_tiered_disabled(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');

        $pricing = app(BookingPricingService::class)->calculate([
            'check_in'      => now()->addDays(5)->format('Y-m-d'),
            'check_out'     => now()->addDays(7)->format('Y-m-d'),
            'guests'        => 1,
            'veteran_type'  => 'veteran_50_69',
            'services'      => $this->veteranPolicyFor($this->accommodation)->enrichServicesWithDiscounts('veteran_50_69', [[
                'service_catalog_id' => $pool->id,
                'name'               => 'استخر',
                'unit_price'         => 100_000,
                'quantity'           => 1,
            ]]),
            'accommodation' => $this->accommodation,
        ]);

        $this->assertSame(65_000, $pricing['service_lines'][0]['discount_amount']);
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

    private function createPriorTieredPoolBooking(string $nationalId, string $checkIn, int $qty, int $unitPrice): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $policy = $this->veteranPolicyFor($this->accommodation);
        $policy->clearCache($this->accommodation->id);

        $services = $policy->enrichServicesWithDiscounts('veteran_70_spouses', [[
            'service_catalog_id' => $pool->id,
            'name'               => 'استخر',
            'unit_price'         => $unitPrice,
            'quantity'           => $qty,
        ]]);

        $pricing = app(BookingPricingService::class)->calculate([
            'check_in'      => $checkIn,
            'check_out'     => Carbon::parse($checkIn)->addDay()->format('Y-m-d'),
            'guests'        => 1,
            'veteran_type'  => 'veteran_70_spouses',
            'services'      => $services,
            'accommodation' => $this->accommodation,
            'national_id'   => $nationalId,
        ]);

        $guest = \App\Models\User::firstOrCreate(
            ['national_id' => $nationalId],
            ['name' => 'مهمان پله‌ای', 'mobile' => '09303030303'],
        );

        $booking = \App\Models\Booking::create([
            'user_id'              => $guest->id,
            'accommodation_id'     => $this->accommodation->id,
            'veteran_type_applied' => 'veteran_70_spouses',
            'booking_source'       => 'manual',
            'check_in'             => $checkIn,
            'check_out'            => Carbon::parse($checkIn)->addDay(),
            'nights'               => 1,
            'guests'               => 1,
            'base_price'           => 1_000_000,
            'services_subtotal'    => $unitPrice * $qty,
            'discount_amount'      => $pricing['services_discount_amount'],
            'total_price'          => 1_000_000,
            'status'               => 'confirmed',
            'tracking_code'        => strtoupper(substr(md5(uniqid($nationalId, true)), 0, 10)),
        ]);

        $line = $pricing['service_lines'][0];
        \App\Models\BookingService::create([
            'booking_id'          => $booking->id,
            'service_catalog_id'  => $pool->id,
            'name'                => 'استخر',
            'unit_price'          => $unitPrice,
            'quantity'            => $qty,
            'free_units'          => $line['free_units'],
            'discount_percentage' => $line['discount_percentage'],
            'discount_amount'     => $line['discount_amount'],
            'total'               => $line['line_total'],
            'sort_order'          => 0,
        ]);

        \App\Models\BookingGuestDetail::create([
            'booking_id'  => $booking->id,
            'sort_order'  => 0,
            'full_name'   => 'مهمان',
            'national_id' => $nationalId,
            'mobile'      => '09303030303',
        ]);
    }
}
