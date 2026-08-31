<?php

namespace Tests\Feature;

use App\Livewire\Admin\BookingShow;
use App\Livewire\Admin\ProgramShow as AdminProgramShow;
use App\Livewire\Host\BookingShow as HostBookingShow;
use App\Livewire\Host\ProgramShow as HostProgramShow;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\CancellationRequest;
use App\Models\Program;
use App\Models\ProgramEmployer;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use App\Services\BookingStayExtensionService;
use App\Services\ProgramBookingService;
use App\Support\HostPermissions;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Morilog\Jalali\Jalalian;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingStayExtensionTest extends TestCase
{
    use RefreshDatabase;

    private $accommodation;
    private RoomType $roomType;
    private RoomRate $roomRate;
    private Room $room;
    private User $host;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $this->accommodation = $this->createTestAccommodation();
        $this->roomType = RoomType::create([
            'accommodation_id' => $this->accommodation->id,
            'name'             => 'دو تخته',
            'capacity'         => 2,
            'room_count'       => 2,
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
        Room::create([
            'room_type_id' => $this->roomType->id,
            'name'         => '۱۰۲',
            'is_active'    => true,
        ]);

        $this->host = User::create(['name' => 'میزبان', 'mobile' => '09120000999']);
        $this->host->assignRole('host');
        $this->host->update([
            'host_panel_permissions' => [
                'bookings.show'  => ['read'],
                'bookings.dates' => ['edit'],
            ],
        ]);
        $this->accommodation->hosts()->attach($this->host->id);

        $this->admin = User::create(['name' => 'ادمین', 'mobile' => '09120000888']);
        $this->admin->assignRole('super_admin');
    }

    public function test_admin_can_extend_manual_booking_and_recalculate_totals(): void
    {
        $booking = $this->createManualBookingWithRoom('2026-08-01', '2026-08-04', 3_000_000);

        $updated = app(BookingStayExtensionService::class)->extendCheckout($booking, '2026-08-07');

        $this->assertSame('2026-08-07', $updated->check_out->format('Y-m-d'));
        $this->assertSame(6, $updated->nights);
        $this->assertGreaterThan(3_000_000, $updated->total_price);
    }

    public function test_host_can_extend_booking_via_livewire(): void
    {
        $booking = $this->createManualBookingWithRoom('2026-09-01', '2026-09-03', 2_000_000);
        $newJalali = Jalalian::fromCarbon(Carbon::parse('2026-09-06'))->format('Y/m/d');

        Livewire::actingAs($this->host)
            ->test(HostBookingShow::class, ['booking' => $booking])
            ->set('extendCheckOutJalali', $newJalali)
            ->call('extendStayCheckout')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertSame('2026-09-06', $booking->fresh()->check_out->format('Y-m-d'));
        $this->assertSame(5, $booking->fresh()->nights);
    }

    public function test_host_without_permission_cannot_extend_booking(): void
    {
        $booking = $this->createManualBookingWithRoom('2026-09-10', '2026-09-12', 2_000_000);

        $this->host->update([
            'host_panel_permissions' => ['bookings.show' => ['read']],
        ]);

        Livewire::actingAs($this->host)
            ->test(HostBookingShow::class, ['booking' => $booking])
            ->set('extendCheckOutJalali', Jalalian::fromCarbon(Carbon::parse('2026-09-15'))->format('Y/m/d'))
            ->call('extendStayCheckout')
            ->assertForbidden();
    }

    public function test_extension_rejects_date_not_after_current_check_out(): void
    {
        $booking = $this->createManualBookingWithRoom('2026-10-01', '2026-10-05', 4_000_000);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('یکسان است');

        app(BookingStayExtensionService::class)->extendCheckout($booking, '2026-10-05');
    }

    public function test_extension_rejects_cancelled_booking(): void
    {
        $booking = $this->createManualBookingWithRoom('2026-10-10', '2026-10-12', 2_000_000);
        $booking->update(['status' => 'cancelled']);

        $this->expectException(\RuntimeException::class);

        app(BookingStayExtensionService::class)->extendCheckout($booking->fresh(), '2026-10-15');
    }

    public function test_extension_rejects_when_pending_cancellation_exists(): void
    {
        $booking = $this->createManualBookingWithRoom('2026-10-20', '2026-10-22', 2_000_000);

        CancellationRequest::create([
            'booking_id'              => $booking->id,
            'requested_by'            => $this->admin->id,
            'status'                  => 'pending',
            'refund_account_number'   => '1234567890123456',
            'days_before_checkin'     => 1,
            'refund_percentage'       => 50,
            'refund_amount'           => 1_000_000,
            'reason_text'             => 'تست',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('درخواست کنسلی');

        app(BookingStayExtensionService::class)->extendCheckout($booking->fresh(), '2026-10-25');
    }

    public function test_extension_fails_when_physical_room_is_booked_on_extension_nights(): void
    {
        $bookingA = $this->createManualBookingWithRoom('2026-11-01', '2026-11-05', 4_000_000);
        $this->createManualBookingWithRoom('2026-11-05', '2026-11-08', 3_000_000, $this->room->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('در شب‌های تمدید');

        app(BookingStayExtensionService::class)->extendCheckout($bookingA, '2026-11-07');
    }

    public function test_host_can_extend_after_original_check_out_day(): void
    {
        Carbon::setTestNow('2026-11-10 12:00:00');

        $booking = $this->createManualBookingWithRoom('2026-11-01', '2026-11-05', 4_000_000);

        Livewire::actingAs($this->host)
            ->test(HostBookingShow::class, ['booking' => $booking])
            ->call('addStayExtensionNights', 3)
            ->call('extendStayCheckout')
            ->assertHasNoErrors();

        $this->assertSame('2026-11-08', $booking->fresh()->check_out->format('Y-m-d'));

        Carbon::setTestNow();
    }

    public function test_program_booking_extends_dates_without_changing_manual_total(): void
    {
        $program = $this->createProgramBooking('2026-12-01', '2026-12-04', 5_000_000);
        $booking = $program->booking;
        $originalTotal = $booking->total_price;

        $updated = app(BookingStayExtensionService::class)->extendCheckout($booking, '2026-12-07');

        $this->assertSame('2026-12-07', $updated->check_out->format('Y-m-d'));
        $this->assertSame(6, $updated->nights);
        $this->assertSame($originalTotal, $updated->total_price);
        $this->assertSame(5_000_000, $program->fresh()->total_amount);
    }

    public function test_host_can_extend_program_via_livewire(): void
    {
        $program = $this->createProgramBooking('2026-12-10', '2026-12-13', 4_000_000);

        $this->host->update([
            'host_panel_permissions' => [
                'programs.show'  => ['read'],
                'programs.dates' => ['edit'],
            ],
        ]);

        Livewire::actingAs($this->host)
            ->test(HostProgramShow::class, ['program' => $program])
            ->call('addStayExtensionNights', 2)
            ->call('extendStayCheckout')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertSame('2026-12-15', $program->booking->fresh()->check_out->format('Y-m-d'));
    }

    public function test_admin_can_extend_program_via_livewire(): void
    {
        $program = $this->createProgramBooking('2026-12-20', '2026-12-23', 4_000_000);
        $newJalali = Jalalian::fromCarbon(Carbon::parse('2026-12-25'))->format('Y/m/d');

        Livewire::actingAs($this->admin)
            ->test(AdminProgramShow::class, ['program' => $program])
            ->set('extendCheckOutJalali', $newJalali)
            ->call('extendStayCheckout')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertSame('2026-12-25', $program->booking->fresh()->check_out->format('Y-m-d'));
    }

    public function test_backfill_grants_stay_date_permissions(): void
    {
        $grants = HostPermissions::backfillGuestEditGrants([
            'bookings.show' => ['read'],
            'programs.show' => ['read'],
        ]);

        $this->assertTrue(HostPermissions::grantsAllow('bookings.dates', 'edit', $grants));
        $this->assertTrue(HostPermissions::grantsAllow('programs.dates', 'edit', $grants));
    }

    public function test_admin_livewire_extend_updates_booking_modal_data(): void
    {
        $booking = $this->createManualBookingWithRoom('2027-01-01', '2027-01-03', 2_000_000);

        Livewire::actingAs($this->admin)
            ->test(BookingShow::class, ['booking' => $booking])
            ->call('addStayExtensionNights', 1)
            ->call('extendStayCheckout')
            ->assertHasNoErrors();

        $this->assertSame('2027-01-04', $booking->fresh()->check_out->format('Y-m-d'));
    }

    private function createManualBookingWithRoom(
        string $checkIn,
        string $checkOut,
        int $totalPrice,
        ?int $roomId = null,
    ): Booking {
        $guest = User::create(['name' => 'مهمان', 'mobile' => '0912' . random_int(1000000, 9999999)]);
        $guest->assignRole('guest');

        $booking = Booking::create([
            'user_id'           => $guest->id,
            'accommodation_id'  => $this->accommodation->id,
            'room_type_id'      => $this->roomType->id,
            'room_rate_id'      => $this->roomRate->id,
            'booking_source'    => 'manual',
            'check_in'          => $checkIn,
            'check_out'         => $checkOut,
            'nights'            => Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut)),
            'guests'            => 2,
            'rooms_consumed'    => 1,
            'base_price'        => $totalPrice,
            'services_subtotal' => 0,
            'discount_amount'   => 0,
            'total_price'       => $totalPrice,
            'status'            => 'confirmed',
            'tracking_code'     => strtoupper(substr(md5(uniqid('', true)), 0, 10)),
        ]);

        BookingRoom::create([
            'booking_id'   => $booking->id,
            'room_type_id' => $this->roomType->id,
            'room_rate_id' => $this->roomRate->id,
            'room_id'      => $roomId ?? $this->room->id,
            'adults'       => 2,
            'guests'       => 2,
            'rooms_consumed' => 1,
            'sort_order'   => 0,
        ]);

        return $booking->fresh(['bookingRooms.room', 'accommodation']);
    }

    private function createProgramBooking(string $checkIn, string $checkOut, int $totalAmount): Program
    {
        $employer = ProgramEmployer::create([
            'province_id'             => $this->ensureTestProvinceId(),
            'name'                    => 'کارفرمای تمدید',
            'employer_code'           => '515109',
            'national_or_economic_id' => '2233445566',
            'mobile'                  => '09127778899',
        ]);

        return app(ProgramBookingService::class)->create(
            $this->accommodation->fresh(),
            [
                'title'               => 'اردوی تمدید',
                'program_type'        => Program::TYPE_CAMP,
                'program_employer_id' => $employer->id,
                'guest_count'         => 2,
                'rooms_allocated'     => 1,
                'check_in'            => $checkIn,
                'check_out'           => $checkOut,
                'base_price'          => $totalAmount,
                'discount_amount'     => 0,
                'deposit_amount'      => 0,
                'room_lines'          => [[
                    'room_type_id' => $this->roomType->id,
                    'room_rate_id' => $this->roomRate->id,
                    'room_id'      => $this->room->id,
                    'room_name'    => $this->room->name,
                ]],
                'guest_details' => [[
                    'full_name'       => 'مهمان اصلی',
                    'national_id'     => '1234567890',
                    'mobile'          => '09121111111',
                    'relation'        => 'مهمان اصلی',
                    'room_line_index' => 0,
                    'sort_order'      => 0,
                ]],
            ],
            $this->admin,
        );
    }
}
