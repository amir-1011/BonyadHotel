<?php

namespace Tests\Feature;

use App\Livewire\ManualBookingForm;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use App\Services\BookingPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Host manual booking: charge only actual guests when bill_full_rooms=false,
 * even if reserved rooms have empty beds.
 */
class PartialOccupancyBillingTest extends TestCase
{
    use RefreshDatabase;

    private $accommodation;
    private RoomType $roomType;
    private RoomRate $roomRate;
    private BookingPricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->accommodation = $this->createTestAccommodation();
        $this->roomType = RoomType::create([
            'accommodation_id' => $this->accommodation->id,
            'name'             => 'اتاق دو تخته',
            'capacity'         => 2,
            'room_count'       => 5,
            'is_active'        => true,
        ]);
        $this->roomRate = RoomRate::create([
            'room_type_id'    => $this->roomType->id,
            'name'            => 'نرخ استاندارد',
            'price_per_night' => 1_000_000,
            'is_active'       => true,
        ]);
        $this->pricing = app(BookingPricingService::class);
    }

    public function test_billing_guests_partial_single_room_one_guest_in_two_bed_room(): void
    {
        $billing = $this->pricing->billingGuests(
            guests: 1,
            extraGuests: 0,
            billFullRooms: false,
            roomsNeeded: 1,
            roomType: $this->roomType,
        );

        $this->assertSame(1, $billing);
    }

    public function test_billing_guests_full_room_charges_all_beds(): void
    {
        $billing = $this->pricing->billingGuests(
            guests: 1,
            extraGuests: 0,
            billFullRooms: true,
            roomsNeeded: 1,
            roomType: $this->roomType,
        );

        $this->assertSame(2, $billing);
    }

    public function test_partial_occupancy_pricing_single_guest_two_nights(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(2);

        $partial = $this->pricing->calculate([
            'check_in'        => $checkIn,
            'check_out'       => $checkOut,
            'guests'          => 1,
            'bill_full_rooms' => false,
            'accommodation'   => $this->accommodation,
            'room_type'       => $this->roomType,
            'room_rate'       => $this->roomRate,
        ]);

        $full = $this->pricing->calculate([
            'check_in'        => $checkIn,
            'check_out'       => $checkOut,
            'guests'          => 1,
            'bill_full_rooms' => true,
            'accommodation'   => $this->accommodation,
            'room_type'       => $this->roomType,
            'room_rate'       => $this->roomRate,
        ]);

        $this->assertSame(1, $partial['billing_guests']);
        $this->assertSame(2_000_000, $partial['room_subtotal']);
        $this->assertSame(2, $full['billing_guests']);
        $this->assertSame(4_000_000, $full['room_subtotal']);
    }

    public function test_partial_occupancy_multi_room_three_guests_in_two_bed_rooms(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(1);

        $partial = $this->pricing->calculateMultiRoom([
            'check_in'      => $checkIn,
            'check_out'     => $checkOut,
            'accommodation' => $this->accommodation,
        ], [
            [
                'guests'           => 2,
                'children_under_6' => 0,
                'extra_guests'     => 0,
                'bill_full_rooms'  => false,
                'room_type'        => $this->roomType,
                'room_rate'        => $this->roomRate,
            ],
            [
                'guests'           => 1,
                'children_under_6' => 0,
                'extra_guests'     => 0,
                'bill_full_rooms'  => false,
                'room_type'        => $this->roomType,
                'room_rate'        => $this->roomRate,
            ],
        ]);

        $full = $this->pricing->calculateMultiRoom([
            'check_in'      => $checkIn,
            'check_out'     => $checkOut,
            'accommodation' => $this->accommodation,
        ], [
            [
                'guests'           => 2,
                'children_under_6' => 0,
                'extra_guests'     => 0,
                'bill_full_rooms'  => true,
                'room_type'        => $this->roomType,
                'room_rate'        => $this->roomRate,
            ],
            [
                'guests'           => 1,
                'children_under_6' => 0,
                'extra_guests'     => 0,
                'bill_full_rooms'  => true,
                'room_type'        => $this->roomType,
                'room_rate'        => $this->roomRate,
            ],
        ]);

        $this->assertSame(3, $partial['billing_guests']);
        $this->assertSame(3_000_000, $partial['room_subtotal']);
        $this->assertSame(4, $full['billing_guests']);
        $this->assertSame(4_000_000, $full['room_subtotal']);
    }

    public function test_partial_occupancy_pricing_with_per_guest_slots_charges_full_room(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(2);

        $roomLines = [[
            'room_type'        => $this->roomType,
            'room_rate'        => $this->roomRate,
            'guests'           => 1,
            'children_under_6' => 0,
            'extra_guests'     => 0,
            'bill_full_rooms'  => true,
        ]];

        $billingGuests = $this->pricing->totalBillingGuestsForRoomLines($roomLines, $this->accommodation);
        $perGuestSlots = $this->pricing->buildPerGuestSlotsFromGuestDetails(
            [['excluded_from_veteran_discount' => false]],
            $billingGuests,
            0,
            null,
            0,
        );

        $pricing = $this->pricing->calculate([
            'check_in'        => $checkIn,
            'check_out'       => $checkOut,
            'guests'          => 1,
            'children_under_6'=> 0,
            'extra_guests'    => 0,
            'bill_full_rooms' => false,
            'accommodation'   => $this->accommodation,
            'room_lines'      => $roomLines,
            'per_guest_slots' => $perGuestSlots,
        ]);

        $this->assertSame(2, $pricing['billing_guests']);
        $this->assertSame(4_000_000, $pricing['room_subtotal']);
    }

    public function test_livewire_dispatch_commit_stores_full_billing_flag_and_pricing(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(2);

        $admin = User::create(['name' => 'ادمین', 'mobile' => '09000000444']);
        $admin->assignRole('super_admin');

        $component = Livewire::actingAs($admin)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->dispatch('manual-booking-commit-room', checkIn: $checkIn, checkOut: $checkOut, guests: 1, adults: 1, roomTypeId: $this->roomType->id, roomRateId: $this->roomRate->id, extraGuests: 0, billFullRooms: true, childrenUnder6: 0)
            ->assertCount('roomLines', 1)
            ->assertSet('roomLines.0.bill_full_rooms', true);

        $pricing = $component->get('pricingPreview');
        $this->assertSame(2, $pricing['billing_guests']);
        $this->assertSame(4_000_000, $pricing['room_subtotal']);
    }

    public function test_livewire_commit_stores_full_billing_flag_and_pricing(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(2);

        $admin = User::create(['name' => 'ادمین', 'mobile' => '09000000333']);
        $admin->assignRole('super_admin');

        $component = Livewire::actingAs($admin)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 1, $this->roomType->id, $this->roomRate->id, 0, true, 0, 1)
            ->assertCount('roomLines', 1)
            ->assertSet('roomLines.0.bill_full_rooms', true);

        $pricing = $component->get('pricingPreview');
        $this->assertSame(2, $pricing['billing_guests']);
        $this->assertSame(4_000_000, $pricing['room_subtotal']);
    }

    public function test_livewire_commit_stores_partial_billing_flag_and_pricing(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(2);

        $admin = User::create(['name' => 'ادمین', 'mobile' => '09000000111']);
        $admin->assignRole('super_admin');

        $component = Livewire::actingAs($admin)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 1, $this->roomType->id, $this->roomRate->id, 0, false, 0, 1)
            ->assertCount('roomLines', 1)
            ->assertSet('roomLines.0.bill_full_rooms', false)
            ->assertSet('roomLines.0.adults', 1);

        $pricing = $component->get('pricingPreview');
        $this->assertSame(2_000_000, $pricing['room_subtotal']);
        $this->assertSame(1, $pricing['billing_guests']);
    }

    public function test_livewire_three_guests_two_rooms_partial_splits_correctly(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(1);

        $admin = User::create(['name' => 'ادمین', 'mobile' => '09000000222']);
        $admin->assignRole('super_admin');

        $component = Livewire::actingAs($admin)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 3, $this->roomType->id, $this->roomRate->id, 0, false, 0, 3);

        $component
            ->assertCount('roomLines', 2)
            ->assertSet('roomLines.0.adults', 2)
            ->assertSet('roomLines.1.adults', 1)
            ->assertSet('roomLines.0.bill_full_rooms', false)
            ->assertSet('roomLines.1.bill_full_rooms', false);

        $pricing = $component->get('pricingPreview');
        $this->assertSame(3_000_000, $pricing['room_subtotal']);
    }

    /** @return array{0:string,1:string} */
    private function futureStay(int $nights): array
    {
        $checkIn = now()->addDays(3)->toDateString();
        $checkOut = now()->addDays(3 + $nights)->toDateString();

        return [$checkIn, $checkOut];
    }
}
