<?php

namespace Tests\Feature;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\BookingService;
use App\Models\PlatformCommissionEntry;
use App\Models\Program;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Services\ManualBookingService;
use App\Services\PlatformCommissionService;
use App\Services\ProgramBookingService;
use App\Support\PlatformCommissionEntryFilter;
use Carbon\Carbon;
use Database\Seeders\VeteranPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);

        $this->adminUser = User::create([
            'name'   => 'ادمین پورسانت',
            'mobile' => '09000000111',
        ]);
        $this->adminUser->assignRole('super_admin');

        $this->accommodation = $this->createTestAccommodation([
            'name'            => 'اقامتگاه پورسانت',
            'price_per_night' => 10_000_000,
            'capacity'        => 10,
            'rooms'           => 5,
            'is_active'       => true,
        ]);

        $this->pool = $this->veteranCatalog($this->accommodation, 'pool');
        $this->gym = $this->veteranCatalog($this->accommodation, 'gym');
        $this->commission = app(PlatformCommissionService::class);
    }

    public function test_booking_commission_is_fixed_amount_regardless_of_price(): void
    {
        $cheap = $this->createBareBooking(totalPrice: 100_000, roomAmount: 100_000);
        $expensive = $this->createBareBooking(totalPrice: 50_000_000, roomAmount: 50_000_000);

        $this->commission->syncBookingCommissions($cheap);
        $this->commission->syncBookingCommissions($expensive);

        $this->assertSame(50_000, $this->commission->calculateBookingCommission($cheap));
        $this->assertSame(50_000, $this->commission->calculateBookingCommission($expensive));
        $this->assertSame(100_000, $this->commission->walletBalance());
    }

    public function test_zero_total_booking_has_no_commission(): void
    {
        $booking = $this->createBareBooking(totalPrice: 0, roomAmount: 0);
        $this->commission->syncBookingCommissions($booking);

        $this->assertSame(0, $this->commission->calculateBookingCommission($booking));
        $this->assertSame(0, PlatformCommissionEntry::where('booking_id', $booking->id)->count());
    }

    public function test_program_booking_has_no_commission_even_with_positive_total(): void
    {
        $booking = $this->createBareBooking(totalPrice: 5_000_000, roomAmount: 5_000_000);
        $this->assertSame(50_000, $this->commission->walletBalance());

        $booking->update(['booking_source' => 'program']);
        $this->commission->syncBookingCommissions($booking->fresh());

        $this->assertSame(0, $this->commission->calculateBookingCommission($booking->fresh()));
        $this->assertSame(0, $this->commission->walletBalance());
        $this->assertSame(
            0,
            (int) PlatformCommissionEntry::where('booking_id', $booking->id)->sum('commission_amount')
        );
    }

    public function test_manual_booking_credits_flat_booking_commission(): void
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
        $this->assertTrue($entry->usesFlatBookingFee());
        $this->assertSame($booking->tracking_code, $entry->meta['tracking_code']);
    }

    public function test_services_do_not_create_commission_entries(): void
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

        $entries = PlatformCommissionEntry::query()
            ->where('booking_id', $booking->id)
            ->where('entry_type', PlatformCommissionEntry::TYPE_CREDIT)
            ->get();

        $this->assertCount(1, $entries);
        $this->assertSame('accommodation', $entries->first()->category_key);
        $this->assertSame(50_000, (int) $entries->sum('commission_amount'));
    }

    public function test_multiple_service_lines_still_yield_single_flat_commission(): void
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

        $this->assertSame(1, PlatformCommissionEntry::where('booking_id', $booking->id)->count());
        $entry = PlatformCommissionEntry::where('booking_id', $booking->id)->first();
        $this->assertSame(10_600_000, $entry->transaction_amount);
        $this->assertSame(50_000, $entry->commission_amount);
    }

    public function test_cancelling_booking_reverses_commission(): void
    {
        $booking = $this->createManualBooking(services: [
            [
                'name'               => 'استخر',
                'unit_price'         => 100_000,
                'quantity'           => 3,
                'service_catalog_id' => $this->pool->id,
            ],
        ]);

        $this->assertSame(50_000, $this->commission->walletBalance());

        $booking->update(['status' => 'cancelled']);

        $this->assertSame(0, $this->commission->walletBalance());

        $reversals = PlatformCommissionEntry::query()
            ->where('booking_id', $booking->id)
            ->where('entry_type', PlatformCommissionEntry::TYPE_REVERSAL)
            ->get();

        $this->assertCount(1, $reversals);
        $this->assertSame(-50_000, $reversals->first()->commission_amount);
    }

    public function test_adding_service_after_booking_does_not_change_commission(): void
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

        $this->assertSame(50_000, $this->commission->walletBalance());
        $this->assertSame(
            0,
            PlatformCommissionEntry::where('booking_id', $booking->id)
                ->where('category', PlatformCommissionEntry::CATEGORY_SERVICE)
                ->count()
        );
    }

    public function test_removing_service_reverses_legacy_service_commission_but_keeps_flat_booking_fee(): void
    {
        $booking = $this->createBareBooking(totalPrice: 10_300_000, roomAmount: 10_000_000);

        BookingService::create([
            'booking_id'         => $booking->id,
            'service_catalog_id' => $this->pool->id,
            'name'               => 'استخر',
            'unit_price'         => 100_000,
            'quantity'           => 3,
            'total'              => 300_000,
            'sort_order'         => 0,
        ]);

        PlatformCommissionEntry::create([
            'booking_id'            => $booking->id,
            'accommodation_id'      => $booking->accommodation_id,
            'category'              => PlatformCommissionEntry::CATEGORY_ACCOMMODATION,
            'category_key'          => 'accommodation',
            'entry_type'            => PlatformCommissionEntry::TYPE_CREDIT,
            'reason'                => PlatformCommissionEntry::REASON_BOOKING_CONFIRMED,
            'transaction_amount'    => 10_000_000,
            'commission_percentage' => 5,
            'commission_cap'        => 50_000,
            'commission_amount'     => 50_000,
            'meta'                  => [],
        ]);
        PlatformCommissionEntry::create([
            'booking_id'            => $booking->id,
            'accommodation_id'      => $booking->accommodation_id,
            'category'              => PlatformCommissionEntry::CATEGORY_SERVICE,
            'category_key'          => 'service:catalog:' . $this->pool->id,
            'service_catalog_id'    => $this->pool->id,
            'service_name'          => 'استخر',
            'entry_type'            => PlatformCommissionEntry::TYPE_CREDIT,
            'reason'                => PlatformCommissionEntry::REASON_BOOKING_CONFIRMED,
            'transaction_amount'    => 300_000,
            'commission_percentage' => 5,
            'commission_cap'        => 50_000,
            'commission_amount'     => 15_000,
            'meta'                  => [],
        ]);

        $booking->services()->delete();
        $booking->update([
            'total_price'       => 10_000_000,
            'services_subtotal' => 0,
        ]);

        $this->commission->syncBookingCommissions($booking->fresh());

        $this->assertSame(50_000, $this->commission->walletBalance());

        $serviceReversal = PlatformCommissionEntry::query()
            ->where('booking_id', $booking->id)
            ->where('category_key', 'service:catalog:' . $this->pool->id)
            ->where('commission_amount', '<', 0)
            ->latest('id')
            ->first();

        $this->assertNotNull($serviceReversal);
        $this->assertSame(-15_000, $serviceReversal->commission_amount);
    }

    public function test_reducing_non_zero_room_total_does_not_change_flat_commission(): void
    {
        $booking = $this->createBareBooking(totalPrice: 1_000_000, roomAmount: 1_000_000);
        $this->commission->syncBookingCommissions($booking);
        $this->assertSame(50_000, $this->commission->walletBalance());

        $booking->update(['total_price' => 500_000]);
        $this->commission->syncBookingCommissions($booking->fresh());

        $this->assertSame(50_000, $this->commission->walletBalance());
        $this->assertSame(
            1,
            PlatformCommissionEntry::where('booking_id', $booking->id)
                ->where('entry_type', PlatformCommissionEntry::TYPE_CREDIT)
                ->count()
        );
    }

    public function test_reducing_booking_total_to_zero_reverses_commission(): void
    {
        $booking = $this->createBareBooking(totalPrice: 1_000_000, roomAmount: 1_000_000);
        $this->commission->syncBookingCommissions($booking);
        $this->assertSame(50_000, $this->commission->walletBalance());

        $booking->update(['total_price' => 0]);
        $this->commission->syncBookingCommissions($booking->fresh());

        $this->assertSame(0, $this->commission->walletBalance());

        $adjustment = PlatformCommissionEntry::query()
            ->where('booking_id', $booking->id)
            ->where('category_key', 'accommodation')
            ->where('entry_type', PlatformCommissionEntry::TYPE_ADJUSTMENT)
            ->latest('id')
            ->first();

        $this->assertNotNull($adjustment);
        $this->assertSame(-50_000, $adjustment->commission_amount);
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
        $this->assertSame('fixed_per_booking', $entry->meta['commission_model']);
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
            'category'   => 'accommodation',
            'entry_type' => 'credit',
        ]);

        $query = PlatformCommissionEntry::query();
        $filter->apply($query);

        $this->assertSame(1, $query->count());
        $this->assertSame('accommodation', $query->first()->category);
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

    public function test_program_booking_service_does_not_accrue_commission(): void
    {
        $roomType = \App\Models\RoomType::create([
            'accommodation_id' => $this->accommodation->id,
            'name'             => 'اتاق اردو',
            'capacity'         => 4,
            'room_count'       => 1,
            'is_active'        => true,
        ]);
        $roomRate = \App\Models\RoomRate::create([
            'room_type_id'    => $roomType->id,
            'name'            => 'نرخ اردو',
            'price_per_night' => 500_000,
            'is_active'       => true,
        ]);
        $room = \App\Models\Room::create([
            'room_type_id' => $roomType->id,
            'name'         => '۱۰۱',
            'is_active'    => true,
        ]);

        $checkIn = now()->addDays(5)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(3)->format('Y-m-d');

        $program = app(ProgramBookingService::class)->create(
            $this->accommodation->fresh(),
            [
                'title'           => 'اردوی تست کارمزد',
                'program_type'    => Program::TYPE_CAMP,
                'guest_count'     => 10,
                'rooms_allocated' => 1,
                'check_in'        => $checkIn,
                'check_out'       => $checkOut,
                'room_lines'      => [[
                    'room_type_id' => $roomType->id,
                    'room_rate_id' => $roomRate->id,
                    'room_id'      => $room->id,
                    'room_name'    => $room->name,
                ]],
                'services' => [[
                    'service_catalog_id' => $this->pool->id,
                    'name'               => 'استخر',
                    'unit_price'         => 100_000,
                    'quantity'           => 2,
                ]],
                'guest_details' => [
                    ['full_name' => 'مهمان اردو', 'national_id' => '1234567890', 'mobile' => '09121111111', 'relation' => ''],
                ],
            ],
            $this->adminUser,
        );

        $booking = $program->booking;
        $this->assertTrue($booking->isProgram());
        $this->assertGreaterThan(0, $booking->total_price);
        $this->assertSame(0, PlatformCommissionEntry::where('booking_id', $booking->id)->count());
        $this->assertSame(0, $this->commission->walletBalance());
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

        $booking = Booking::create([
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

        $this->commission->syncBookingCommissions($booking);

        return $booking;
    }
}
