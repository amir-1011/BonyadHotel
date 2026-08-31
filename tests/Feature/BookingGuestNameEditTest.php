<?php

namespace Tests\Feature;

use App\Livewire\Admin\BookingShow;
use App\Livewire\Host\BookingShow as HostBookingShow;
use App\Models\Booking;
use App\Models\BookingGuestDetail;
use App\Models\BookingRoom;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingGuestNameEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_guest_details_for_unnamed_slot(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $accommodation = $this->createTestAccommodation();
        $admin = User::create(['name' => 'ادمین', 'mobile' => '09120000001']);
        $admin->assignRole('super_admin');

        $roomType = RoomType::create([
            'accommodation_id' => $accommodation->id,
            'name'             => 'چهار تخته',
            'capacity'         => 4,
            'room_count'       => 2,
            'is_active'        => true,
        ]);
        $roomRate = RoomRate::create([
            'room_type_id'    => $roomType->id,
            'name'            => 'استاندارد',
            'price_per_night' => 1_000_000,
            'is_active'       => true,
        ]);

        $guest = User::create(['name' => 'مهمان', 'mobile' => '09121112222']);

        $booking = Booking::create([
            'user_id'           => $guest->id,
            'accommodation_id'  => $accommodation->id,
            'booking_source'    => 'manual',
            'check_in'          => now()->addDays(5)->format('Y-m-d'),
            'check_out'         => now()->addDays(7)->format('Y-m-d'),
            'nights'            => 2,
            'guests'            => 3,
            'base_price'        => 4_000_000,
            'services_subtotal' => 0,
            'discount_amount'   => 0,
            'total_price'       => 4_000_000,
            'status'            => 'confirmed',
            'tracking_code'     => 'GNAMEEDIT1',
        ]);

        $bookingRoom = BookingRoom::create([
            'booking_id'   => $booking->id,
            'room_type_id' => $roomType->id,
            'room_rate_id' => $roomRate->id,
            'adults'       => 3,
            'guests'       => 3,
            'sort_order'   => 0,
        ]);

        BookingGuestDetail::create([
            'booking_id'      => $booking->id,
            'booking_room_id' => $bookingRoom->id,
            'sort_order'      => 0,
            'full_name'       => 'مهمان اصلی',
            'relation'        => BookingGuestDetail::RELATION_MAIN_GUEST,
        ]);

        $this->assertSame(3, $booking->fresh()->allGuestSlotsForDisplay()->count());

        Livewire::actingAs($admin)
            ->test(BookingShow::class, ['booking' => $booking->fresh()])
            ->set('editableGuests.1.full_name', 'علی رضایی')
            ->set('editableGuests.1.national_id', '1234567890')
            ->set('editableGuests.1.mobile', '09123334455')
            ->set('editableGuests.1.relation', 'همسر')
            ->call('saveGuestDetails', 1)
            ->assertHasNoErrors();

        $saved = BookingGuestDetail::where('booking_id', $booking->id)->where('sort_order', 1)->first();
        $this->assertNotNull($saved);
        $this->assertSame('علی رضایی', $saved->full_name);
        $this->assertSame('1234567890', $saved->national_id);
        $this->assertSame('09123334455', $saved->mobile);
        $this->assertSame('همسر', $saved->relation);
    }

    public function test_admin_can_update_existing_secondary_guest_details(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $accommodation = $this->createTestAccommodation();
        $admin = User::create(['name' => 'ادمین', 'mobile' => '09120000002']);
        $admin->assignRole('super_admin');

        $guest = User::create(['name' => 'مهمان', 'mobile' => '09121112222']);

        $booking = Booking::create([
            'user_id'           => $guest->id,
            'accommodation_id'  => $accommodation->id,
            'booking_source'    => 'manual',
            'check_in'          => now()->addDays(5)->format('Y-m-d'),
            'check_out'         => now()->addDays(6)->format('Y-m-d'),
            'nights'            => 1,
            'guests'            => 2,
            'base_price'        => 1_000_000,
            'services_subtotal' => 0,
            'discount_amount'   => 0,
            'total_price'       => 1_000_000,
            'status'            => 'confirmed',
            'tracking_code'     => 'GNAMEEDIT2',
        ]);

        BookingGuestDetail::create([
            'booking_id' => $booking->id,
            'sort_order' => 0,
            'full_name'  => 'مهمان اصلی',
            'relation'   => BookingGuestDetail::RELATION_MAIN_GUEST,
        ]);
        BookingGuestDetail::create([
            'booking_id' => $booking->id,
            'sort_order' => 1,
            'full_name'  => 'مهمان 2',
        ]);

        Livewire::actingAs($admin)
            ->test(BookingShow::class, ['booking' => $booking->fresh()])
            ->set('editableGuests.1.full_name', 'زهرا محمدی')
            ->set('editableGuests.1.relation', 'فرزند')
            ->call('saveGuestDetails', 1)
            ->assertHasNoErrors();

        $saved = BookingGuestDetail::where('booking_id', $booking->id)->where('sort_order', 1)->first();
        $this->assertSame('زهرا محمدی', $saved->full_name);
        $this->assertSame('فرزند', $saved->relation);
    }

    public function test_host_can_save_guest_details_with_permission(): void
    {
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);

        $accommodation = $this->createTestAccommodation();
        $host = User::create(['name' => 'میزبان', 'mobile' => '09120000003']);
        $host->assignRole('host');
        $host->update([
            'host_panel_permissions' => [
                'bookings.show'   => ['read'],
                'bookings.guests' => ['edit'],
            ],
        ]);
        $accommodation->hosts()->attach($host->id);

        $guest = User::create(['name' => 'مهمان', 'mobile' => '09121113333']);

        $booking = Booking::create([
            'user_id'           => $guest->id,
            'accommodation_id'  => $accommodation->id,
            'booking_source'    => 'manual',
            'check_in'          => now()->addDays(5)->format('Y-m-d'),
            'check_out'         => now()->addDays(7)->format('Y-m-d'),
            'nights'            => 2,
            'guests'            => 2,
            'base_price'        => 2_000_000,
            'services_subtotal' => 0,
            'discount_amount'   => 0,
            'total_price'       => 2_000_000,
            'status'            => 'confirmed',
            'tracking_code'     => 'GNAMEEDIT3',
        ]);

        BookingGuestDetail::create([
            'booking_id' => $booking->id,
            'sort_order' => 0,
            'full_name'  => 'مهمان اصلی',
            'relation'   => BookingGuestDetail::RELATION_MAIN_GUEST,
        ]);

        Livewire::actingAs($host)
            ->test(HostBookingShow::class, ['booking' => $booking->fresh()])
            ->set('editableGuests.1.full_name', 'سارا کریمی')
            ->call('saveGuestDetails', 1)
            ->assertHasNoErrors();

        $saved = BookingGuestDetail::where('booking_id', $booking->id)->where('sort_order', 1)->first();
        $this->assertNotNull($saved);
        $this->assertSame('سارا کریمی', $saved->full_name);
    }

    public function test_host_cannot_save_guest_details_without_permission(): void
    {
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);

        $accommodation = $this->createTestAccommodation();
        $host = User::create(['name' => 'میزبان', 'mobile' => '09120000004']);
        $host->assignRole('host');
        $host->update([
            'host_panel_permissions' => [
                'bookings.show' => ['read'],
            ],
        ]);
        $accommodation->hosts()->attach($host->id);

        $guest = User::create(['name' => 'مهمان', 'mobile' => '09121114444']);

        $booking = Booking::create([
            'user_id'           => $guest->id,
            'accommodation_id'  => $accommodation->id,
            'booking_source'    => 'manual',
            'check_in'          => now()->addDays(5)->format('Y-m-d'),
            'check_out'         => now()->addDays(7)->format('Y-m-d'),
            'nights'            => 2,
            'guests'            => 2,
            'base_price'        => 2_000_000,
            'services_subtotal' => 0,
            'discount_amount'   => 0,
            'total_price'       => 2_000_000,
            'status'            => 'confirmed',
            'tracking_code'     => 'GNAMEEDIT4',
        ]);

        BookingGuestDetail::create([
            'booking_id' => $booking->id,
            'sort_order' => 0,
            'full_name'  => 'مهمان اصلی',
            'relation'   => BookingGuestDetail::RELATION_MAIN_GUEST,
        ]);

        Livewire::actingAs($host)
            ->test(HostBookingShow::class, ['booking' => $booking->fresh()])
            ->set('editableGuests.1.full_name', 'سارا کریمی')
            ->call('saveGuestDetails', 1)
            ->assertForbidden();
    }

    public function test_host_with_bookings_show_gets_guest_edit_via_permission_migration(): void
    {
        $grants = \App\Support\HostPermissions::backfillGuestEditGrants([
            'bookings.show' => ['read'],
        ]);

        $this->assertTrue(\App\Support\HostPermissions::grantsAllow('bookings.guests', 'edit', $grants));
    }

    public function test_host_can_save_guest_details_after_check_out(): void
    {
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);

        $accommodation = $this->createTestAccommodation();
        $host = User::create(['name' => 'میزبان', 'mobile' => '09120000005']);
        $host->assignRole('host');
        $host->update([
            'host_panel_permissions' => [
                'bookings.show'   => ['read'],
                'bookings.guests' => ['edit'],
            ],
        ]);
        $accommodation->hosts()->attach($host->id);

        $guest = User::create(['name' => 'مهمان', 'mobile' => '09121115555']);

        $booking = Booking::create([
            'user_id'           => $guest->id,
            'accommodation_id'  => $accommodation->id,
            'booking_source'    => 'manual',
            'check_in'          => '2026-07-01',
            'check_out'         => '2026-07-05',
            'nights'            => 4,
            'guests'            => 2,
            'base_price'        => 2_000_000,
            'services_subtotal' => 0,
            'discount_amount'   => 0,
            'total_price'       => 2_000_000,
            'status'            => 'confirmed',
            'tracking_code'     => 'GNAMEEDIT5',
        ]);

        BookingGuestDetail::create([
            'booking_id' => $booking->id,
            'sort_order' => 0,
            'full_name'  => 'مهمان اصلی',
            'relation'   => BookingGuestDetail::RELATION_MAIN_GUEST,
        ]);

        \Carbon\Carbon::setTestNow('2026-07-10 10:00:00');

        Livewire::actingAs($host)
            ->test(HostBookingShow::class, ['booking' => $booking->fresh()])
            ->set('editableGuests.1.full_name', 'مینا شریفی')
            ->call('saveGuestDetails', 1)
            ->assertHasNoErrors();

        $saved = BookingGuestDetail::where('booking_id', $booking->id)->where('sort_order', 1)->first();
        $this->assertNotNull($saved);
        $this->assertSame('مینا شریفی', $saved->full_name);

        \Carbon\Carbon::setTestNow();
    }
}
