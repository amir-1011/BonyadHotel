<?php

namespace Tests\Feature;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use App\Services\BookingPricingService;
use App\Services\ManualBookingService;
use Carbon\Carbon;
use Database\Seeders\VeteranPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MultiRoomManualBookingTest extends TestCase
{
    use RefreshDatabase;

    private Accommodation $accommodation;
    private RoomType $roomTypeA;
    private RoomType $roomTypeB;
    private RoomRate $rateA;
    private RoomRate $rateB;
    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VeteranPolicySeeder::class);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $this->adminUser = User::create([
            'name'   => 'ادمین تست',
            'mobile' => '09000000099',
        ]);
        $this->adminUser->assignRole('super_admin');

        $provinceId = DB::table('provinces')->insertGetId(['name' => 'استان تست', 'created_at' => now(), 'updated_at' => now()]);
        $cityId = DB::table('cities')->insertGetId(['province_id' => $provinceId, 'name' => 'شهر تست', 'created_at' => now(), 'updated_at' => now()]);

        $this->accommodation = Accommodation::create([
            'city_id'         => $cityId,
            'name'            => 'اقامتگاه چنداتاق',
            'price_per_night' => 500_000,
            'capacity'        => 20,
            'rooms'           => 10,
            'is_active'       => true,
        ]);

        $this->roomTypeA = RoomType::create([
            'accommodation_id' => $this->accommodation->id,
            'name'             => 'اتاق دو تخته',
            'capacity'         => 2,
            'room_count'       => 5,
            'is_active'        => true,
        ]);
        $this->rateA = RoomRate::create([
            'room_type_id'   => $this->roomTypeA->id,
            'name'           => 'نرخ استاندارد A',
            'price_per_night'=> 1_000_000,
        ]);

        $this->roomTypeB = RoomType::create([
            'accommodation_id' => $this->accommodation->id,
            'name'             => 'سوئیت خانوادگی',
            'capacity'         => 4,
            'room_count'       => 3,
            'is_active'        => true,
        ]);
        $this->rateB = RoomRate::create([
            'room_type_id'   => $this->roomTypeB->id,
            'name'           => 'نرخ استاندارد B',
            'price_per_night'=> 2_000_000,
        ]);
    }

    public function test_multi_room_pricing_sums_each_room_line(): void
    {
        $checkIn = now()->addDays(7)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');

        $pricing = app(BookingPricingService::class)->calculate([
            'check_in'      => $checkIn,
            'check_out'     => $checkOut,
            'accommodation' => $this->accommodation,
            'veteran_type'  => null,
            'services'      => [],
            'room_lines'    => [
                [
                    'room_type'        => $this->roomTypeA,
                    'room_rate'        => $this->rateA,
                    'guests'           => 2,
                    'children_under_6' => 0,
                    'extra_guests'     => 0,
                    'bill_full_rooms'  => false,
                ],
                [
                    'room_type'        => $this->roomTypeB,
                    'room_rate'        => $this->rateB,
                    'guests'           => 3,
                    'children_under_6' => 0,
                    'extra_guests'     => 0,
                    'bill_full_rooms'  => false,
                ],
            ],
        ]);

        $this->assertSame(2, $pricing['nights']);
        $this->assertCount(2, $pricing['room_lines']);
        $this->assertSame(2, $pricing['rooms_needed']);
        $this->assertSame(5, $pricing['billing_guests']);
        // A: 2 guests × 1M × 2 nights = 4M, B: 3 guests × 2M × 2 nights = 12M
        $this->assertSame(16_000_000, $pricing['room_subtotal']);
        $this->assertSame(16_000_000, $pricing['total_price']);
    }

    public function test_create_multi_room_manual_booking_persists_room_lines(): void
    {
        $checkIn = now()->addDays(10)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');

        $booking = app(ManualBookingService::class)->create(
            $this->accommodation,
            [
                'check_in'             => $checkIn,
                'check_out'            => $checkOut,
                'room_lines'           => [
                    [
                        'room_type_id'     => $this->roomTypeA->id,
                        'room_rate_id'     => $this->rateA->id,
                        'adults'           => 2,
                        'children_under_6' => 0,
                        'guests'           => 2,
                        'extra_guests'     => 0,
                        'bill_full_rooms'  => false,
                    ],
                    [
                        'room_type_id'     => $this->roomTypeB->id,
                        'room_rate_id'     => $this->rateB->id,
                        'adults'           => 2,
                        'children_under_6' => 1,
                        'guests'           => 3,
                        'extra_guests'     => 0,
                        'bill_full_rooms'  => false,
                    ],
                ],
                'guests'               => 5,
                'children_under_6'     => 1,
                'veteran_type'         => null,
                'booker_national_id'   => '5566778899',
                'guest_contact_name'   => 'مهمان چنداتاق',
                'guest_contact_mobile' => '09155667788',
                'payment_method'       => 'cash',
                'user_id'              => null,
                'services'             => [],
                'guest_details'        => [
                    ['full_name' => 'مهمان چنداتاق', 'national_id' => '5566778899', 'mobile' => '09155667788', 'relation' => ''],
                ],
            ],
            $this->adminUser,
        );

        $this->assertSame(5, $booking->guests);
        $this->assertSame(1, $booking->children_under_6);
        $this->assertSame($this->roomTypeA->id, $booking->room_type_id);
        $this->assertCount(2, $booking->bookingRooms);

        $lines = $booking->bookingRooms()->orderBy('sort_order')->get();
        $this->assertSame(2, $lines[0]->guests);
        $this->assertSame(3, $lines[1]->guests);
        $this->assertSame(1, $lines[1]->children_under_6);
        $this->assertSame(2, $booking->rooms_consumed);
    }

    public function test_availability_counts_multi_room_consumption_per_type(): void
    {
        $checkIn = now()->addDays(14)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');

        app(ManualBookingService::class)->create(
            $this->accommodation,
            [
                'check_in'   => $checkIn,
                'check_out'  => $checkOut,
                'room_lines' => [
                    [
                        'room_type_id' => $this->roomTypeA->id,
                        'room_rate_id' => $this->rateA->id,
                        'adults'       => 4,
                        'guests'       => 4,
                    ],
                    [
                        'room_type_id' => $this->roomTypeB->id,
                        'room_rate_id' => $this->rateB->id,
                        'adults'       => 4,
                        'guests'       => 4,
                    ],
                ],
                'guests'               => 8,
                'booker_national_id'   => '1122334455',
                'guest_contact_name'   => 'تست ظرفیت',
                'guest_contact_mobile' => '09112233445',
                'payment_method'       => 'cash',
                'services'             => [],
                'guest_details'        => [
                    ['full_name' => 'تست ظرفیت', 'national_id' => '1122334455', 'mobile' => '09112233445', 'relation' => ''],
                ],
            ],
            $this->adminUser,
        );

        $mapA = $this->roomTypeA->fresh()->availabilityMap($checkIn, $checkOut);
        $mapB = $this->roomTypeB->fresh()->availabilityMap($checkIn, $checkOut);
        $night = $checkIn;

        // 4 guests in capacity-2 room → 2 rooms consumed from type A (5 total)
        $this->assertSame(3, $mapA[$night]['available_rooms']);
        // 4 guests in capacity-4 room → 1 room consumed from type B (3 total)
        $this->assertSame(2, $mapB[$night]['available_rooms']);
    }

    public function test_rejects_booking_when_room_type_capacity_exceeded(): void
    {
        $checkIn = now()->addDays(20)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(1)->format('Y-m-d');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ظرفیت کافی');

        app(ManualBookingService::class)->create(
            $this->accommodation,
            [
                'check_in'   => $checkIn,
                'check_out'  => $checkOut,
                'room_lines' => [
                    [
                        'room_type_id' => $this->roomTypeB->id,
                        'room_rate_id' => $this->rateB->id,
                        'adults'       => 13,
                        'guests'       => 13,
                    ],
                ],
                'guests'               => 13,
                'booker_national_id'   => '9988776655',
                'guest_contact_name'   => 'تست رد',
                'guest_contact_mobile' => '09199887766',
                'payment_method'       => 'cash',
                'services'             => [],
                'guest_details'        => [
                    ['full_name' => 'تست رد', 'national_id' => '9988776655', 'mobile' => '09199887766', 'relation' => ''],
                ],
            ],
            $this->adminUser,
        );
    }

    public function test_legacy_single_room_booking_still_works(): void
    {
        $checkIn = now()->addDays(25)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(1)->format('Y-m-d');

        $booking = app(ManualBookingService::class)->create(
            $this->accommodation,
            [
                'room_type_id'         => $this->roomTypeA->id,
                'room_rate_id'         => $this->rateA->id,
                'check_in'             => $checkIn,
                'check_out'            => $checkOut,
                'guests'               => 2,
                'booker_national_id'   => '4433221100',
                'guest_contact_name'   => 'تست قدیمی',
                'guest_contact_mobile' => '09144332211',
                'payment_method'       => 'cash',
                'services'             => [],
                'guest_details'        => [
                    ['full_name' => 'تست قدیمی', 'national_id' => '4433221100', 'mobile' => '09144332211', 'relation' => ''],
                ],
            ],
            $this->adminUser,
        );

        $this->assertCount(1, $booking->bookingRooms);
        $this->assertSame(2_000_000, $booking->total_price);
    }

    public function test_livewire_commit_and_remove_room_lines(): void
    {
        $host = User::create(['name' => 'میزبان', 'mobile' => '09120000001']);
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        $host->assignRole('host');
        $this->accommodation->hosts()->attach($host->id);

        $checkIn = now()->addDays(30)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');

        \Livewire\Livewire::actingAs($host)
            ->test(\App\Livewire\ManualBookingForm::class, [
                'accommodation' => $this->accommodation,
                'panel'           => 'host',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 2, $this->roomTypeA->id, $this->rateA->id, 0, false, 0, 2)
            ->assertSet('roomLines', fn ($lines) => count($lines) === 1)
            ->assertSet('checkIn', $checkIn)
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 3, $this->roomTypeB->id, $this->rateB->id, 0, false, 1, 2)
            ->assertSet('roomLines', fn ($lines) => count($lines) === 2)
            ->assertSet('totalGuests', 5)
            ->call('removeRoomLine', 0)
            ->assertSet('roomLines', fn ($lines) => count($lines) === 1)
            ->assertSet('totalGuests', 3);
    }
}
