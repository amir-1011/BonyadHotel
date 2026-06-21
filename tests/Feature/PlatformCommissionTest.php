<?php

namespace Tests\Feature;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\BookingService;
use App\Models\PlatformCommissionEntry;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Services\ManualBookingService;
use App\Services\PlatformCommissionService;
use App\Support\PlatformCommissionEntryFilter;
use Carbon\Carbon;
use Database\Seeders\VeteranPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlatformCommissionTest extends TestCase
{
    use RefreshDatabase;

    private Accommodation $accommodation;
    private User $adminUser;
    private PlatformCommissionService $commission;
    private ServiceCatalog $pool;
    private ServiceCatalog $gym;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VeteranPolicySeeder::class);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $this->adminUser = User::create([
            'name'   => 'ادمین پورسانت',
            'mobile' => '09000000111',
        ]);
        $this->adminUser->assignRole('super_admin');

        $provinceId = DB::table('provinces')->insertGetId(['name' => 'استان تست', 'created_at' => now(), 'updated_at' => now()]);
        $cityId = DB::table('cities')->insertGetId(['province_id' => $provinceId, 'name' => 'شهر تست', 'created_at' => now(), 'updated_at' => now()]);

        $this->accommodation = Accommodation::create([
            'city_id'         => $cityId,
            'name'            => 'اقامتگاه پورسانت',
            'price_per_night' => 10_000_000,
            'capacity'        => 10,
            'rooms'           => 5,
            'is_active'       => true,
        ]);

        $this->pool = ServiceCatalog::where('key', 'pool')->firstOrFail();
        $this->gym = ServiceCatalog::where('key', 'gym')->firstOrFail();
        $this->commission = app(PlatformCommissionService::class);
    }

    public function test_commission_calculation_respects_percentage_and_cap(): void
    {
        $this->assertSame(50_000, $this->commission->calculateCommission(10_000_000));
        $this->assertSame(15_000, $this->commission->calculateCommission(300_000));
        $this->assertSame(50_000, $this->commission->calculateCommission(2_000_000));
        $this->assertSame(0, $this->commission->calculateCommission(0));
    }

    public function test_manual_booking_credits_accommodation_commission_capped(): void
    {
        $booking = $this->createManualBooking(services: []);

        $entry = PlatformCommissionEntry::query()
            ->where('booking_id', $booking->id)
            ->where('category_key', 'accommodation')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame(PlatformCommissionEntry::TYPE_CREDIT, $entry->entry_type);
        $this->assertSame(10_000_000, $entry->transaction_amount);
        $this->assertSame(50_000, $entry->commission_amount);
        $this->assertSame($booking->tracking_code, $entry->meta['tracking_code']);
    }

    public function test_pool_service_commission_is_fifteen_thousand_for_three_hundred_thousand(): void
    {
        $booking = $this->createManualBooking(services: [
            [
                'name'               => 'استخر',
                'unit_price'         => 100_000,
                'quantity'           => 3,
                'service_catalog_id' => $this->pool->id,
            ],
        ]);

        $entry = PlatformCommissionEntry::query()
            ->where('booking_id', $booking->id)
            ->where('category', PlatformCommissionEntry::CATEGORY_SERVICE)
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame(300_000, $entry->transaction_amount);
        $this->assertSame(15_000, $entry->commission_amount);
        $this->assertSame('استخر', $entry->service_name);
    }

    public function test_room_and_services_create_separate_commission_records(): void
    {
        $booking = $this->createManualBooking(services: [
            [
                'name'               => 'استخر',
                'unit_price'         => 100_000,
                'quantity'           => 3,
                'service_catalog_id' => $this->pool->id,
            ],
            [
                'name'               => 'بدنسازی',
                'unit_price'         => 200_000,
                'quantity'           => 1,
                'service_catalog_id' => $this->gym->id,
            ],
        ]);

        $keys = PlatformCommissionEntry::query()
            ->where('booking_id', $booking->id)
            ->where('entry_type', PlatformCommissionEntry::TYPE_CREDIT)
            ->pluck('category_key')
            ->sort()
            ->values()
            ->all();

        $this->assertCount(3, $keys);
        $this->assertContains('accommodation', $keys);
        $this->assertContains('service:catalog:' . $this->pool->id, $keys);
        $this->assertContains('service:catalog:' . $this->gym->id, $keys);

        $wallet = PlatformCommissionEntry::query()
            ->where('booking_id', $booking->id)
            ->sum('commission_amount');

        // 50k room + 15k pool + 10k gym
        $this->assertSame(75_000, (int) $wallet);
    }

    public function test_multiple_pool_lines_are_grouped_into_one_service_commission(): void
    {
        $booking = $this->createBareBooking(totalPrice: 10_600_000, roomAmount: 10_000_000);

        BookingService::create([
            'booking_id'         => $booking->id,
            'service_catalog_id' => $this->pool->id,
            'name'               => 'استخر',
            'unit_price'         => 100_000,
            'quantity'           => 2,
            'total'              => 200_000,
            'sort_order'         => 0,
        ]);
        BookingService::create([
            'booking_id'         => $booking->id,
            'service_catalog_id' => $this->pool->id,
            'name'               => 'استخر',
            'unit_price'         => 100_000,
            'quantity'           => 1,
            'total'              => 100_000,
            'sort_order'         => 1,
        ]);

        $this->commission->syncBookingCommissions($booking);

        $poolEntries = PlatformCommissionEntry::query()
            ->where('booking_id', $booking->id)
            ->where('category_key', 'service:catalog:' . $this->pool->id)
            ->get();

        $this->assertCount(1, $poolEntries);
        $this->assertSame(300_000, $poolEntries->first()->transaction_amount);
        $this->assertSame(15_000, $poolEntries->first()->commission_amount);
        $this->assertSame(3, $poolEntries->first()->meta['quantity']);
    }

    public function test_cancelling_booking_reverses_all_commissions(): void
    {
        $booking = $this->createManualBooking(services: [
            [
                'name'               => 'استخر',
                'unit_price'         => 100_000,
                'quantity'           => 3,
                'service_catalog_id' => $this->pool->id,
            ],
        ]);

        $this->assertSame(65_000, $this->commission->walletBalance());

        $booking->update(['status' => 'cancelled']);

        $this->assertSame(0, $this->commission->walletBalance());

        $reversals = PlatformCommissionEntry::query()
            ->where('booking_id', $booking->id)
            ->where('entry_type', PlatformCommissionEntry::TYPE_REVERSAL)
            ->get();

        $this->assertGreaterThanOrEqual(2, $reversals->count());
        $this->assertTrue($reversals->every(fn ($e) => $e->commission_amount < 0));
    }

    public function test_adding_service_after_booking_creates_adjustment_credit(): void
    {
        $booking = $this->createManualBooking(services: []);
        $this->assertSame(50_000, $this->commission->walletBalance());

        BookingService::create([
            'booking_id'         => $booking->id,
            'service_catalog_id' => $this->pool->id,
            'name'               => 'استخر',
            'unit_price'         => 100_000,
            'quantity'           => 3,
            'total'              => 300_000,
            'sort_order'         => 0,
        ]);

        $booking->update(['total_price' => $booking->total_price + 300_000]);
        app(ManualBookingService::class)->recalculateTotals($booking->fresh());

        $poolEntry = PlatformCommissionEntry::query()
            ->where('booking_id', $booking->id)
            ->where('category_key', 'service:catalog:' . $this->pool->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($poolEntry);
        $this->assertSame(15_000, $poolEntry->commission_amount);
        $this->assertSame(65_000, $this->commission->walletBalance());
    }

    public function test_removing_service_creates_adjustment_debit(): void
    {
        $booking = $this->createManualBooking(services: [
            [
                'name'               => 'استخر',
                'unit_price'         => 100_000,
                'quantity'           => 3,
                'service_catalog_id' => $this->pool->id,
            ],
        ]);

        $this->assertSame(65_000, $this->commission->walletBalance());

        $booking->services()->delete();
        $booking->update([
            'total_price'       => 10_000_000,
            'services_subtotal' => 0,
        ]);

        $this->commission->syncBookingCommissions($booking->fresh());

        $this->assertSame(50_000, $this->commission->walletBalance());

        $debit = PlatformCommissionEntry::query()
            ->where('booking_id', $booking->id)
            ->where('category_key', 'service:catalog:' . $this->pool->id)
            ->where('commission_amount', '<', 0)
            ->latest('id')
            ->first();

        $this->assertNotNull($debit);
        $this->assertSame(-15_000, $debit->commission_amount);
        $this->assertSame(PlatformCommissionEntry::REASON_AMOUNT_ADJUSTED, $debit->reason);
    }

    public function test_reducing_room_total_creates_negative_adjustment(): void
    {
        $booking = $this->createBareBooking(totalPrice: 1_000_000, roomAmount: 1_000_000);
        $this->commission->syncBookingCommissions($booking);
        $this->assertSame(50_000, $this->commission->walletBalance());

        $booking->update(['total_price' => 500_000]);
        $this->commission->syncBookingCommissions($booking->fresh());

        $adjustment = PlatformCommissionEntry::query()
            ->where('booking_id', $booking->id)
            ->where('category_key', 'accommodation')
            ->where('entry_type', PlatformCommissionEntry::TYPE_ADJUSTMENT)
            ->latest('id')
            ->first();

        $this->assertNotNull($adjustment);
        $this->assertSame(500_000, $adjustment->transaction_amount);
        $this->assertSame(-25_000, $adjustment->commission_amount);
        $this->assertSame(25_000, $this->commission->walletBalance());
    }

    public function test_pending_booking_does_not_accrue_commission(): void
    {
        $booking = $this->createBareBooking(totalPrice: 1_000_000, roomAmount: 1_000_000, status: 'pending');
        $this->commission->syncBookingCommissions($booking);

        $this->assertSame(0, PlatformCommissionEntry::where('booking_id', $booking->id)->count());

        $booking->update(['status' => 'confirmed']);
        $this->assertGreaterThan(0, PlatformCommissionEntry::where('booking_id', $booking->id)->count());
    }

    public function test_commission_entry_contains_full_metadata(): void
    {
        $booking = $this->createManualBooking(services: [
            [
                'name'               => 'استخر',
                'unit_price'         => 100_000,
                'quantity'           => 1,
                'service_catalog_id' => $this->pool->id,
            ],
        ]);

        $entry = PlatformCommissionEntry::query()
            ->where('booking_id', $booking->id)
            ->where('category_key', 'accommodation')
            ->first();

        $this->assertSame($booking->tracking_code, $entry->meta['tracking_code']);
        $this->assertSame($this->accommodation->name, $entry->meta['accommodation_name']);
        $this->assertSame('manual', $entry->meta['booking_source']);
        $this->assertNotEmpty($entry->meta['booker_name']);
        $this->assertSame($this->accommodation->id, $entry->accommodation_id);
        $this->assertNotEmpty($entry->fullExplanation());
        $this->assertNotEmpty($entry->commissionCalculationSteps());
    }

    public function test_admin_can_view_commission_entry_detail_page(): void
    {
        $booking = $this->createManualBooking(services: []);
        $entry = PlatformCommissionEntry::query()
            ->where('booking_id', $booking->id)
            ->firstOrFail();

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.commission-wallet.show', $entry));

        $response->assertOk();
        $response->assertSee('توضیح کامل این تراکنش');
        $response->assertSee('کد پیگیری');
        $response->assertSee('نحوه محاسبه کارمزد');
        $response->assertSee('تاریخچه کامل تغییرات کارمزد');
    }

    public function test_commission_wallet_filters_by_category_and_entry_type(): void
    {
        $this->createManualBooking(services: [
            [
                'name'               => 'استخر',
                'unit_price'         => 100_000,
                'quantity'           => 1,
                'service_catalog_id' => $this->pool->id,
            ],
        ]);

        $filter = PlatformCommissionEntryFilter::make([
            'category'   => 'service',
            'entry_type' => 'credit',
        ]);

        $query = PlatformCommissionEntry::query();
        $filter->apply($query);

        $this->assertSame(1, $query->count());
        $this->assertSame('service', $query->first()->category);
    }

    public function test_admin_can_export_commission_wallet_with_filters(): void
    {
        $this->createManualBooking(services: []);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.commission-wallet.export', [
                'category'   => 'accommodation',
                'entry_type' => 'credit',
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_commission_wallet_page_shows_filters(): void
    {
        $this->createManualBooking(services: []);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.commission-wallet', ['category' => 'accommodation']));

        $response->assertOk();
        $response->assertSee('جستجو و فیلتر');
        $response->assertSee('خروجی اکسل');
        $response->assertSee('نتیجه فیلتر');
    }

  /** @param  array<int, array<string, mixed>>  $services */
    private function createManualBooking(array $services): Booking
    {
        $checkIn = now()->addDays(14)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDay()->format('Y-m-d');

        return app(ManualBookingService::class)->create(
            $this->accommodation,
            [
                'check_in'             => $checkIn,
                'check_out'            => $checkOut,
                'guests'               => 1,
                'children_under_6'     => 0,
                'veteran_type'         => null,
                'booker_national_id'   => '1234567890',
                'guest_contact_name'   => 'مهمان پورسانت',
                'guest_contact_mobile' => '09121234567',
                'payment_method'       => 'cash',
                'services'             => $services,
                'guest_details'        => [
                    ['full_name' => 'مهمان پورسانت', 'national_id' => '1234567890', 'mobile' => '09121234567', 'relation' => ''],
                ],
            ],
            $this->adminUser,
        );
    }

    private function createBareBooking(int $totalPrice, int $roomAmount, string $status = 'confirmed'): Booking
    {
        $guest = User::create([
            'name'        => 'مهمان',
            'mobile'      => '0912' . rand(1000000, 9999999),
            'national_id' => (string) rand(1000000000, 9999999999),
        ]);
        $guest->assignRole('guest');

        $checkIn = now()->addDays(20)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDay()->format('Y-m-d');

        return Booking::create([
            'user_id'           => $guest->id,
            'accommodation_id'  => $this->accommodation->id,
            'check_in'          => $checkIn,
            'check_out'         => $checkOut,
            'nights'            => 1,
            'guests'            => 1,
            'base_price'        => $totalPrice,
            'services_subtotal' => $totalPrice - $roomAmount,
            'total_price'       => $totalPrice,
            'status'            => $status,
            'booking_source'    => 'manual',
            'tracking_code'     => strtoupper(substr(md5(uniqid()), 0, 10)),
        ]);
    }
}
