<?php

namespace Tests\Feature;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\RoomTypeBlockedDate;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\VeteranPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BlockedDatesBookingConflictTest extends TestCase
{
    use RefreshDatabase;

    private Accommodation $accommodation;
    private RoomType $roomType;
    private Room $roomBooked;
    private Room $roomFree;
    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VeteranPolicySeeder::class);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->adminUser = User::create([
            'name'   => 'ادمین مسدودسازی',
            'mobile' => '09000000111',
        ]);
        $this->adminUser->assignRole('super_admin');

        $provinceId = DB::table('provinces')->insertGetId(['name' => 'استان تست', 'created_at' => now(), 'updated_at' => now()]);
        $cityId = DB::table('cities')->insertGetId(['province_id' => $provinceId, 'name' => 'شهر تست', 'created_at' => now(), 'updated_at' => now()]);

        $this->accommodation = Accommodation::create([
            'city_id'         => $cityId,
            'name'            => 'اقامتگاه HTTP تست',
            'price_per_night' => 500_000,
            'capacity'        => 10,
            'rooms'           => 5,
            'is_active'       => true,
        ]);

        $this->roomType = RoomType::create([
            'accommodation_id' => $this->accommodation->id,
            'name'             => 'اتاق تست',
            'capacity'         => 2,
            'room_count'       => 2,
            'is_active'        => true,
        ]);

        RoomRate::create([
            'room_type_id'    => $this->roomType->id,
            'name'            => 'نرخ',
            'price_per_night' => 1_000_000,
        ]);

        $this->roomBooked = Room::create([
            'room_type_id' => $this->roomType->id,
            'name'         => 'اتاق رزروی',
            'sort_order'   => 1,
            'is_active'    => true,
        ]);
        $this->roomFree = Room::create([
            'room_type_id' => $this->roomType->id,
            'name'         => 'اتاق آزاد',
            'sort_order'   => 2,
            'is_active'    => true,
        ]);
    }

    public function test_admin_store_rejects_booked_room(): void
    {
        $checkIn = now()->addDays(12)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(3)->format('Y-m-d');
        $this->seedBooking($checkIn, $checkOut, $this->roomBooked);

        $from = Jalalian::fromCarbon(Carbon::parse($checkIn)->addDay())->format('Y/m/d');
        $to = Jalalian::fromCarbon(Carbon::parse($checkIn)->addDays(2))->format('Y/m/d');

        $response = $this->actingAs($this->adminUser)->post(
            route('admin.room-types.blocked-dates.store', [$this->accommodation, $this->roomType]),
            [
                'date_from' => $from,
                'date_to'   => $to,
                'room_ids'  => [$this->roomBooked->id],
                'reason'    => 'تست',
            ],
        );

        $response->assertSessionHasErrors('room_ids');
        $this->assertSame(0, RoomTypeBlockedDate::count());
    }

    public function test_admin_store_allows_free_room_in_same_range(): void
    {
        $checkIn = now()->addDays(18)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(3)->format('Y-m-d');
        $this->seedBooking($checkIn, $checkOut, $this->roomBooked);

        $from = Jalalian::fromCarbon(Carbon::parse($checkIn)->addDay())->format('Y/m/d');
        $to = Jalalian::fromCarbon(Carbon::parse($checkIn)->addDays(2))->format('Y/m/d');

        $response = $this->actingAs($this->adminUser)->post(
            route('admin.room-types.blocked-dates.store', [$this->accommodation, $this->roomType]),
            [
                'date_from' => $from,
                'date_to'   => $to,
                'room_ids'  => [$this->roomFree->id],
            ],
        );

        $response->assertRedirect(route('admin.room-types.blocked-dates', [$this->accommodation, $this->roomType]));
        $response->assertSessionHasNoErrors();
        $this->assertGreaterThan(0, RoomTypeBlockedDate::where('room_id', $this->roomFree->id)->count());
    }

    public function test_preview_endpoint_lists_conflicting_rooms(): void
    {
        $checkIn = now()->addDays(22)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');
        $this->seedBooking($checkIn, $checkOut, $this->roomBooked);

        $from = Jalalian::fromCarbon(Carbon::parse($checkIn))->format('Y/m/d');
        $to = Jalalian::fromCarbon(Carbon::parse($checkIn)->addDay())->format('Y/m/d');

        $response = $this->actingAs($this->adminUser)->getJson(
            route('admin.room-types.blocked-dates.preview', [$this->accommodation, $this->roomType]) .
            '?' . http_build_query(['date_from' => $from, 'date_to' => $to]),
        );

        $response->assertOk();
        $response->assertJsonFragment(['room_id' => $this->roomBooked->id]);
        $response->assertJsonMissing(['room_id' => $this->roomFree->id]);
    }

    public function test_host_store_rejects_booked_room(): void
    {
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        $host = User::create(['name' => 'میزبان', 'mobile' => '09000000222']);
        $host->assignRole('host');
        $this->accommodation->hosts()->attach($host->id);

        $checkIn = now()->addDays(28)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');
        $this->seedBooking($checkIn, $checkOut, $this->roomBooked);

        $from = Jalalian::fromCarbon(Carbon::parse($checkIn))->format('Y/m/d');
        $to = Jalalian::fromCarbon(Carbon::parse($checkIn)->addDay())->format('Y/m/d');

        $response = $this->actingAs($host)->post(
            route('host.room-types.blocked-dates.store', [$this->accommodation, $this->roomType]),
            [
                'date_from' => $from,
                'date_to'   => $to,
                'room_ids'  => [$this->roomBooked->id],
            ],
        );

        $response->assertSessionHasErrors('room_ids');
    }

    private function seedBooking(string $checkIn, string $checkOut, Room $room): void
    {
        $guest = User::create(['name' => 'مهمان', 'mobile' => '09' . random_int(100000000, 999999999)]);

        $booking = Booking::create([
            'user_id'          => $guest->id,
            'accommodation_id' => $this->accommodation->id,
            'room_type_id'     => $this->roomType->id,
            'check_in'         => $checkIn,
            'check_out'        => $checkOut,
            'guests'           => 2,
            'rooms_consumed'   => 1,
            'nights'           => max(1, Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut))),
            'base_price'       => 1_000_000,
            'total_price'      => 1_000_000,
            'status'           => 'confirmed',
            'tracking_code'    => 'BK' . random_int(1000, 9999),
            'booking_source'   => 'manual',
        ]);

        BookingRoom::create([
            'booking_id'     => $booking->id,
            'room_type_id'   => $this->roomType->id,
            'room_id'        => $room->id,
            'adults'         => 2,
            'guests'         => 2,
            'rooms_consumed' => 1,
            'sort_order'     => 0,
        ]);
    }
}
