<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Support\StayDurationPicker;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManualBookingDurationPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_booking_page_includes_duration_picker_markup(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $accommodation = $this->createTestAccommodation();
        $roomType = RoomType::create([
            'accommodation_id' => $accommodation->id,
            'name'             => 'اتاق تست',
            'capacity'         => 2,
            'room_count'       => 3,
            'is_active'        => true,
        ]);
        RoomRate::create([
            'room_type_id'    => $roomType->id,
            'name'            => 'نرخ پایه',
            'price_per_night' => 500_000,
            'is_active'       => true,
        ]);

        $admin = User::create([
            'name'   => 'ادمین',
            'mobile' => '09000001111',
        ]);
        $admin->assignRole('super_admin');

        $response = $this->actingAs($admin)
            ->get(route('admin.accommodations.manual-booking', $accommodation));

        $response->assertOk();
        $response->assertSee('bnb-cal-duration-panel', false);
        $response->assertSee('bnb-cal-duration-toggle', false);
        $response->assertSee('data-bnb-stay-checkout-input', false);
        $response->assertSee('cal-duration-entry', false);
        $response->assertSee('تعداد شب را در همان روز انتخاب‌شده وارد کنید', false);
        $response->assertSee('تاریخ خروج را وارد کنید یا روز خروج را در تقویم بزنید', false);
        $response->assertSee('data-bnb-mode="manual"', false);
        $response->assertSee('mbf-step-viewport', false);
        $response->assertSee('mbf-step-pane', false);
        $response->assertSee('data-mbf-step="1"', false);
        $response->assertSee('data-mbf-slide="1"', false);
        $response->assertSee('id="manual-booking-nav"', false);
    }

    public function test_manual_booking_scripts_expose_stay_duration_helpers(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $accommodation = $this->createTestAccommodation();
        $roomType = RoomType::create([
            'accommodation_id' => $accommodation->id,
            'name'             => 'اتاق تست',
            'capacity'         => 2,
            'room_count'       => 3,
            'is_active'        => true,
        ]);
        RoomRate::create([
            'room_type_id'    => $roomType->id,
            'name'            => 'نرخ پایه',
            'price_per_night' => 500_000,
            'is_active'       => true,
        ]);

        $admin = User::create([
            'name'   => 'ادمین',
            'mobile' => '09000001112',
        ]);
        $admin->assignRole('super_admin');

        $response = $this->actingAs($admin)
            ->get(route('admin.accommodations.manual-booking', $accommodation));

        $response->assertOk();
        $response->assertSee('checkOutFromNights', false);
        $response->assertSee('confirmStayDuration', false);
        $response->assertSee('clearDatesSelection', false);
        $response->assertSee('_scrollToTodayInCalendar', false);
    }

    public function test_physical_rooms_api_accepts_long_stays_up_to_max_nights(): void
    {
        $accommodation = $this->createTestAccommodation();
        $roomType = RoomType::create([
            'accommodation_id' => $accommodation->id,
            'name'             => 'اتاق تست',
            'capacity'         => 2,
            'room_count'       => 3,
            'is_active'        => true,
        ]);
        Room::create([
            'room_type_id' => $roomType->id,
            'name'         => '۱۰۱',
            'is_active'    => true,
        ]);

        $checkIn = Carbon::today()->addDays(3)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(120)->format('Y-m-d');

        $response = $this->getJson(route('api.room-types.physical-rooms', $roomType) . '?' . http_build_query([
            'check_in'  => $checkIn,
            'check_out' => $checkOut,
        ]));

        $response->assertOk();
        $response->assertJsonPath('rooms.0.name', '۱۰۱');
    }

    public function test_physical_rooms_api_rejects_stays_beyond_max_nights(): void
    {
        $accommodation = $this->createTestAccommodation();
        $roomType = RoomType::create([
            'accommodation_id' => $accommodation->id,
            'name'             => 'اتاق تست',
            'capacity'         => 2,
            'room_count'       => 3,
            'is_active'        => true,
        ]);

        $checkIn = Carbon::today()->addDays(3)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(StayDurationPicker::MAX_NIGHTS + 1)->format('Y-m-d');

        $response = $this->getJson(route('api.room-types.physical-rooms', $roomType) . '?' . http_build_query([
            'check_in'  => $checkIn,
            'check_out' => $checkOut,
        ]));

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'range_too_long');
    }
}
