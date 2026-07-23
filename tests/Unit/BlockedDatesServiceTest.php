<?php

namespace Tests\Unit;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\RoomTypeBlockedDate;
use App\Models\User;
use App\Services\BlockedDatesService;
use Carbon\Carbon;
use Database\Seeders\VeteranPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Morilog\Jalali\Jalalian;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BlockedDatesServiceTest extends TestCase
{
    use RefreshDatabase;

    private Accommodation $accommodation;
    private RoomType $roomType;
    private Room $roomBooked;
    private Room $roomFree;
    private BlockedDatesService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VeteranPolicySeeder::class);

        $provinceId = DB::table('provinces')->insertGetId(['name' => 'استان تست', 'created_at' => now(), 'updated_at' => now()]);
        $cityId = DB::table('cities')->insertGetId(['province_id' => $provinceId, 'name' => 'شهر تست', 'created_at' => now(), 'updated_at' => now()]);

        $this->accommodation = Accommodation::create([
            'city_id'         => $cityId,
            'name'            => 'اقامتگاه مسدودسازی',
            'price_per_night' => 500_000,
            'capacity'        => 10,
            'rooms'           => 5,
            'is_active'       => true,
        ]);

        $this->roomType = RoomType::create([
            'accommodation_id' => $this->accommodation->id,
            'name'             => 'اتاق دو تخته',
            'capacity'         => 2,
            'room_count'       => 2,
            'is_active'        => true,
        ]);

        RoomRate::create([
            'room_type_id'    => $this->roomType->id,
            'name'            => 'نرخ استاندارد',
            'price_per_night' => 1_000_000,
        ]);

        $this->roomBooked = Room::create([
            'room_type_id' => $this->roomType->id,
            'name'         => 'اتاق ۱۰۱',
            'sort_order'   => 1,
            'is_active'    => true,
        ]);
        $this->roomFree = Room::create([
            'room_type_id' => $this->roomType->id,
            'name'         => 'اتاق ۱۰۲',
            'sort_order'   => 2,
            'is_active'    => true,
        ]);

        $this->service = app(BlockedDatesService::class);
    }

    public function test_blocks_free_room_in_date_range(): void
    {
        $from = now()->addDays(10)->format('Y-m-d');
        $to = Carbon::parse($from)->addDays(2)->format('Y-m-d');

        $created = $this->service->store($this->roomType, $from, $to, [$this->roomFree->id], 'تعمیرات');

        $this->assertSame(3, $created);
        $this->assertSame(3, RoomTypeBlockedDate::where('room_id', $this->roomFree->id)->count());
    }

    public function test_rejects_blocking_room_with_active_booking(): void
    {
        $checkIn = now()->addDays(15)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(3)->format('Y-m-d');

        $this->createBookingWithRoom($checkIn, $checkOut, $this->roomBooked, 'confirmed');

        $errors = $this->service->validateNoBookingConflicts(
            $this->roomType,
            Carbon::parse($checkIn)->addDay()->format('Y-m-d'),
            Carbon::parse($checkIn)->addDays(2)->format('Y-m-d'),
            [$this->roomBooked->id],
        );

        $this->assertNotNull($errors);
        $this->assertStringContainsString('اتاق ۱۰۱', $errors['room_ids']);
    }

    public function test_allows_blocking_when_booking_is_cancelled(): void
    {
        $checkIn = now()->addDays(20)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');

        $this->createBookingWithRoom($checkIn, $checkOut, $this->roomBooked, 'cancelled');

        $errors = $this->service->validateNoBookingConflicts(
            $this->roomType,
            $checkIn,
            Carbon::parse($checkIn)->addDay()->format('Y-m-d'),
            [$this->roomBooked->id],
        );

        $this->assertNull($errors);
        $this->service->store($this->roomType, $checkIn, Carbon::parse($checkIn)->addDay()->format('Y-m-d'), [$this->roomBooked->id], null);
        $this->assertSame(2, RoomTypeBlockedDate::where('room_id', $this->roomBooked->id)->count());
    }

    public function test_partial_overlap_on_checkout_day_is_allowed_for_blocking(): void
    {
        $checkIn = now()->addDays(25)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(3)->format('Y-m-d');

        $this->createBookingWithRoom($checkIn, $checkOut, $this->roomBooked, 'confirmed');

        // Booking occupies nights checkIn, checkIn+1, checkIn+2 — not checkout day itself.
        $blockFrom = $checkOut;
        $blockTo = $checkOut;

        $errors = $this->service->validateNoBookingConflicts(
            $this->roomType,
            $blockFrom,
            $blockTo,
            [$this->roomBooked->id],
        );

        $this->assertNull($errors);
    }

    public function test_overlap_on_last_booked_night_blocks(): void
    {
        $checkIn = now()->addDays(30)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(3)->format('Y-m-d');
        $lastNight = Carbon::parse($checkOut)->subDay()->format('Y-m-d');

        $this->createBookingWithRoom($checkIn, $checkOut, $this->roomBooked, 'pending');

        $errors = $this->service->validateNoBookingConflicts(
            $this->roomType,
            $lastNight,
            $lastNight,
            [$this->roomBooked->id],
        );

        $this->assertNotNull($errors);
    }

    public function test_mixed_selection_only_flags_booked_rooms(): void
    {
        $checkIn = now()->addDays(35)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');

        $this->createBookingWithRoom($checkIn, $checkOut, $this->roomBooked, 'confirmed');

        $conflicts = $this->service->findBookingConflicts(
            $this->roomType,
            $checkIn,
            Carbon::parse($checkIn)->addDay()->format('Y-m-d'),
            [$this->roomBooked->id, $this->roomFree->id],
        );

        $this->assertCount(1, $conflicts);
        $this->assertSame($this->roomBooked->id, $conflicts[0]['room_id']);

        $this->service->store(
            $this->roomType,
            $checkIn,
            Carbon::parse($checkIn)->addDay()->format('Y-m-d'),
            [$this->roomFree->id],
            null,
        );

        $this->assertGreaterThan(0, RoomTypeBlockedDate::where('room_id', $this->roomFree->id)->count());
    }

    public function test_store_throws_validation_exception_on_conflict(): void
    {
        $checkIn = now()->addDays(40)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');

        $this->createBookingWithRoom($checkIn, $checkOut, $this->roomBooked, 'confirmed');

        $this->expectException(ValidationException::class);
        $this->service->store($this->roomType, $checkIn, Carbon::parse($checkIn)->addDay()->format('Y-m-d'), [$this->roomBooked->id], null);
    }

    public function test_preview_conflicts_returns_unavailable_room_ids(): void
    {
        $checkIn = now()->addDays(45)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');

        $this->createBookingWithRoom($checkIn, $checkOut, $this->roomBooked, 'confirmed');

        $jalaliFrom = Jalalian::fromCarbon(Carbon::parse($checkIn))->format('Y/m/d');
        $jalaliTo = Jalalian::fromCarbon(Carbon::parse($checkIn)->addDay())->format('Y/m/d');

        $preview = $this->service->previewConflicts($this->roomType, $jalaliFrom, $jalaliTo);

        $this->assertContains($this->roomBooked->id, $preview['unavailable_room_ids']);
        $this->assertNotContains($this->roomFree->id, $preview['unavailable_room_ids']);
    }

    public function test_destroy_range_deletes_matching_records_in_one_query(): void
    {
        $from = now()->addDays(10)->format('Y-m-d');
        $to = now()->addDays(12)->format('Y-m-d');

        $this->service->store($this->roomType, $from, $to, [$this->roomBooked->id, $this->roomFree->id], 'تعمیرات');

        $this->assertSame(6, RoomTypeBlockedDate::query()->where('room_type_id', $this->roomType->id)->count());

        $deleted = $this->service->destroyRange(
            $this->roomType,
            $from,
            $to,
            [$this->roomBooked->id, $this->roomFree->id],
            'تعمیرات',
        );

        $this->assertSame(6, $deleted);
        $this->assertSame(0, RoomTypeBlockedDate::query()->where('room_type_id', $this->roomType->id)->count());
    }

    public function test_destroy_range_respects_reason_filter(): void
    {
        $date = now()->addDays(15)->format('Y-m-d');

        RoomTypeBlockedDate::create([
            'room_type_id' => $this->roomType->id,
            'room_id'      => $this->roomBooked->id,
            'date'         => $date,
            'reason'       => 'دلیل الف',
        ]);
        RoomTypeBlockedDate::create([
            'room_type_id' => $this->roomType->id,
            'room_id'      => $this->roomBooked->id,
            'date'         => Carbon::parse($date)->addDay()->format('Y-m-d'),
            'reason'       => 'دلیل ب',
        ]);

        $deleted = $this->service->destroyRange(
            $this->roomType,
            $date,
            Carbon::parse($date)->addDay()->format('Y-m-d'),
            [$this->roomBooked->id],
            'دلیل الف',
        );

        $this->assertSame(1, $deleted);
        $this->assertSame(1, RoomTypeBlockedDate::query()->where('room_type_id', $this->roomType->id)->count());
    }

    private function createBookingWithRoom(string $checkIn, string $checkOut, Room $room, string $status): Booking
    {
        $user = User::create(['name' => 'مهمان', 'mobile' => '09' . random_int(100000000, 999999999)]);

        $booking = Booking::create([
            'user_id'          => $user->id,
            'accommodation_id' => $this->accommodation->id,
            'room_type_id'     => $this->roomType->id,
            'check_in'         => $checkIn,
            'check_out'        => $checkOut,
            'guests'           => 2,
            'rooms_consumed'   => 1,
            'nights'           => max(1, Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut))),
            'base_price'       => 1_000_000,
            'total_price'      => 1_000_000,
            'status'           => $status,
            'tracking_code'    => 'T' . random_int(10000, 99999),
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

        return $booking;
    }
}
