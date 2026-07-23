<?php

namespace Tests\Feature;

use App\Livewire\Admin\BookingShow;
use App\Models\Booking;
use App\Models\BookingGuestDetail;
use App\Models\BookingRoom;
use App\Models\BookingService;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\ServiceCatalogVariant;
use App\Models\User;
use App\Services\ManualBookingService;
use Database\Seeders\VeteranPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingShowDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_show_displays_per_guest_services_with_free_session_label(): void
    {
        $this->seed(VeteranPolicySeeder::class);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $accommodation = $this->createTestAccommodation();
        $admin = User::create(['name' => 'ادمین', 'mobile' => '09120000001']);
        $admin->assignRole('super_admin');

        $roomType = RoomType::create([
            'accommodation_id' => $accommodation->id,
            'name'             => 'دو تخته',
            'capacity'         => 2,
            'room_count'       => 2,
            'is_active'        => true,
        ]);
        $roomRate = RoomRate::create([
            'room_type_id'    => $roomType->id,
            'name'            => 'استاندارد',
            'price_per_night' => 1_000_000,
            'is_active'       => true,
        ]);
        $physicalRoom = Room::create([
            'room_type_id' => $roomType->id,
            'name'         => '۱۰۱',
            'sort_order'   => 1,
            'is_active'    => true,
        ]);

        $guest = User::create(['name' => 'مهمان', 'mobile' => '09121112222', 'national_id' => '4440123456']);
        $checkIn = now()->addDays(5)->format('Y-m-d');
        $checkOut = now()->addDays(7)->format('Y-m-d');

        $booking = Booking::create([
            'user_id'              => $guest->id,
            'accommodation_id'     => $accommodation->id,
            'veteran_type_applied' => 'veteran_70_spouses',
            'discount_percentage'  => 70,
            'booking_source'       => 'manual',
            'check_in'             => $checkIn,
            'check_out'            => $checkOut,
            'nights'               => 2,
            'guests'               => 1,
            'base_price'           => 2_000_000,
            'services_subtotal'    => 0,
            'discount_amount'      => 0,
            'total_price'          => 2_000_000,
            'status'               => 'confirmed',
            'tracking_code'        => 'SHOWTEST01',
        ]);

        $bookingRoom = BookingRoom::create([
            'booking_id'   => $booking->id,
            'room_type_id' => $roomType->id,
            'room_rate_id' => $roomRate->id,
            'room_id'      => $physicalRoom->id,
            'adults'       => 1,
            'guests'       => 1,
            'sort_order'   => 0,
        ]);

        BookingGuestDetail::create([
            'booking_id'      => $booking->id,
            'booking_room_id' => $bookingRoom->id,
            'sort_order'      => 0,
            'full_name'       => 'جانباز تست',
            'national_id'     => '4440123456',
            'relation'        => 'رزرو‌کننده',
        ]);

        $pool = $this->veteranCatalog($accommodation, 'pool');
        $variant = ServiceCatalogVariant::create([
            'service_catalog_id' => $pool->id,
            'key'                => 'pool_show',
            'name'               => 'استخر',
            'price'              => 100_000,
            'sort_order'         => 1,
            'is_active'          => true,
        ]);

        BookingService::create([
            'booking_id'                 => $booking->id,
            'guest_sort_order'           => 0,
            'service_catalog_id'         => $pool->id,
            'service_catalog_variant_id' => $variant->id,
            'name'                       => 'استخر — استاندارد',
            'unit_price'                 => 100_000,
            'quantity'                   => 3,
            'total'                      => 300_000,
            'excluded_from_veteran_quota' => false,
            'sort_order'                 => 0,
        ]);

        app(ManualBookingService::class)->recalculateTotals($booking->fresh());
        $booking->refresh();

        Livewire::actingAs($admin)
            ->test(BookingShow::class, ['booking' => $booking->fresh()])
            ->assertSee('جانباز تست')
            ->assertSee('اتاق ۱۰۱')
            ->assertSee('سهمیه مهمان اصلی')
            ->assertSee('3 جلسه رایگان')
            ->assertSee('بدون تخفیف: 300,000')
            ->assertSee('با تخفیف: 0')
            ->assertDontSee('65٪ ایثارگری');
    }
}
