<?php

namespace Tests\Feature;

use App\Livewire\Admin\BookingShow;
use App\Models\Booking;
use App\Models\BookingGuestDetail;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use App\Services\BookingRoomModificationService;
use App\Services\ManualBookingService;
use Carbon\Carbon;
use Database\Seeders\VeteranPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingRoomModificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private RoomType $roomTypeA;
    private RoomType $roomTypeB;
    private RoomRate $rateA;
    private RoomRate $rateB;
    private Room $physicalA;
    private Room $physicalB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VeteranPolicySeeder::class);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $this->admin = User::create([
            'name'   => 'ادمین',
            'mobile' => '09000001111',
        ]);
        $this->admin->assignRole('super_admin');

        $accommodation = $this->createTestAccommodation(['name' => 'اقامتگاه تست رزرو']);

        $this->roomTypeA = RoomType::create([
            'accommodation_id' => $accommodation->id,
            'name'             => 'دو تخته',
            'capacity'         => 2,
            'extra_capacity'   => 1,
            'extra_capacity_price' => 150_000,
            'room_count'       => 4,
            'is_active'        => true,
        ]);
        $this->rateA = RoomRate::create([
            'room_type_id'    => $this->roomTypeA->id,
            'name'            => 'نرخ A',
            'price_per_night' => 1_000_000,
            'is_active'       => true,
        ]);
        $this->physicalA = Room::create([
            'room_type_id' => $this->roomTypeA->id,
            'name'         => 'A-101',
            'is_active'    => true,
        ]);

        $this->roomTypeB = RoomType::create([
            'accommodation_id' => $accommodation->id,
            'name'             => 'سه تخته',
            'capacity'         => 3,
            'room_count'       => 2,
            'is_active'        => true,
        ]);
        $this->rateB = RoomRate::create([
            'room_type_id'    => $this->roomTypeB->id,
            'name'            => 'نرخ B',
            'price_per_night' => 1_500_000,
            'is_active'       => true,
        ]);
        $this->physicalB = Room::create([
            'room_type_id' => $this->roomTypeB->id,
            'name'         => 'B-201',
            'is_active'    => true,
        ]);
    }

    public function test_add_room_line_increases_totals_and_reprices_manual_booking(): void
    {
        $booking = $this->createManualBookingWithOneRoom(guests: 1);
        $originalTotal = (int) $booking->total_price;

        $updated = app(BookingRoomModificationService::class)->addRoomLine($booking, [
            'room_type_id'     => $this->roomTypeB->id,
            'room_rate_id'     => $this->rateB->id,
            'room_id'          => $this->physicalB->id,
            'adults'           => 2,
            'children_under_6' => 0,
            'guests'           => 2,
            'extra_guests'     => 0,
            'bill_full_rooms'  => false,
        ], $this->admin);

        $this->assertCount(2, $updated->bookingRooms);
        $this->assertSame(3, (int) $updated->guests);
        $this->assertSame(2, (int) $updated->rooms_consumed);
        $this->assertGreaterThan($originalTotal, (int) $updated->total_price);
        $this->assertSame($this->physicalB->id, $updated->bookingRooms->sortByDesc('sort_order')->first()->room_id);
    }

    public function test_add_guest_to_partially_occupied_room_increases_billing_and_price(): void
    {
        $booking = $this->createManualBookingWithOneRoom(guests: 1);
        $line = $booking->bookingRooms->first();
        $originalTotal = (int) $booking->total_price;

        $updated = app(BookingRoomModificationService::class)->addGuestToRoom(
            $booking,
            $line->id,
            $this->admin,
        );

        $line = $updated->bookingRooms->first();
        $this->assertSame(2, (int) $line->guests);
        $this->assertSame(2, (int) $updated->guests);
        $this->assertGreaterThan(1, $updated->guestDetails()->count());
        $this->assertGreaterThan($originalTotal, (int) $updated->total_price);
    }

    public function test_add_guest_fails_when_room_capacity_is_full(): void
    {
        $booking = $this->createManualBookingWithOneRoom(guests: 2);
        $line = $booking->bookingRooms()->first();

        app(BookingRoomModificationService::class)->addGuestToRoom($booking->fresh(), $line->id, $this->admin);
        $booking = $booking->fresh()->load('bookingRooms');
        $line = $booking->bookingRooms->first();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ظرفیت');

        app(BookingRoomModificationService::class)->addGuestToRoom($booking, $line->id, $this->admin);
    }

    public function test_add_guest_uses_floor_capacity_when_beds_are_full(): void
    {
        $booking = $this->createManualBookingWithOneRoom(guests: 2);
        $line = $booking->bookingRooms()->first();
        $originalExtra = (int) $booking->extra_guests;

        $updated = app(BookingRoomModificationService::class)->addGuestToRoom(
            $booking->fresh(),
            $line->id,
            $this->admin,
        );

        $this->assertSame(2, (int) $updated->bookingRooms->first()->guests);
        $this->assertSame($originalExtra + 1, (int) $updated->extra_guests);
        $this->assertSame(1, (int) $updated->bookingRooms->first()->extra_guests);
    }

    public function test_add_room_blocked_when_pending_cancellation_exists(): void
    {
        $booking = $this->createManualBookingWithOneRoom(guests: 1);
        \App\Models\CancellationRequest::create([
            'booking_id'              => $booking->id,
            'requested_by'            => $this->admin->id,
            'status'                  => 'pending',
            'refund_account_number'   => '1234567890123456',
            'refund_account_holder_name'=> 'مهمان',
            'days_before_checkin'     => 1,
            'refund_percentage'       => 50,
            'refund_amount'           => 1_000_000,
            'reason_text'             => 'test',
        ]);

        $this->expectException(\RuntimeException::class);

        app(BookingRoomModificationService::class)->addRoomLine($booking, [
            'room_type_id' => $this->roomTypeB->id,
            'room_rate_id' => $this->rateB->id,
            'adults'       => 1,
            'guests'       => 1,
        ], $this->admin);
    }

    public function test_livewire_commit_add_room_line_from_booking_show(): void
    {
        $booking = $this->createManualBookingWithOneRoom(guests: 1);

        Livewire::actingAs($this->admin)
            ->test(BookingShow::class, ['booking' => $booking])
            ->set('addRoomRoomTypeId', (string) $this->roomTypeB->id)
            ->set('addRoomRoomRateId', (string) $this->rateB->id)
            ->set('addRoomAdults', 1)
            ->set('addRoomPhysicalRoomId', $this->physicalB->id)
            ->call('commitAddRoomLine')
            ->assertHasNoErrors();

        $fresh = $booking->fresh()->load('bookingRooms');
        $this->assertCount(2, $fresh->bookingRooms);
    }

    public function test_livewire_add_guest_to_sold_room_from_booking_show(): void
    {
        $booking = $this->createManualBookingWithOneRoom(guests: 1);
        $lineId = $booking->bookingRooms->first()->id;

        Livewire::actingAs($this->admin)
            ->test(BookingShow::class, ['booking' => $booking])
            ->call('addGuestToSoldRoom', $lineId)
            ->assertHasNoErrors();

        $fresh = $booking->fresh()->load(['bookingRooms', 'guestDetails']);
        $this->assertSame(2, (int) $fresh->bookingRooms->first()->guests);
        $this->assertGreaterThanOrEqual(2, $fresh->guestDetails->count());
    }

    public function test_legacy_single_room_booking_is_materialized_before_adding_room(): void
    {
        $accommodation = $this->roomTypeA->accommodation;
        $guest = User::create(['name' => 'مهمان', 'mobile' => '09123334444']);
        $checkIn = now()->addDays(10)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');

        $booking = Booking::create([
            'user_id'          => $guest->id,
            'created_by'       => $this->admin->id,
            'accommodation_id' => $accommodation->id,
            'room_type_id'     => $this->roomTypeA->id,
            'room_rate_id'     => $this->rateA->id,
            'booking_source'   => 'manual',
            'check_in'         => $checkIn,
            'check_out'        => $checkOut,
            'nights'           => 2,
            'guests'           => 1,
            'rooms_consumed'   => 1,
            'base_price'       => 2_000_000,
            'services_subtotal'=> 0,
            'discount_amount'  => 0,
            'total_price'      => 2_000_000,
            'status'           => 'confirmed',
            'tracking_code'    => 'LEGACY001',
        ]);

        BookingGuestDetail::create([
            'booking_id' => $booking->id,
            'sort_order' => 0,
            'full_name'  => 'مهمان',
        ]);

        app(BookingRoomModificationService::class)->addRoomLine($booking, [
            'room_type_id' => $this->roomTypeB->id,
            'room_rate_id' => $this->rateB->id,
            'adults'       => 1,
            'guests'       => 1,
        ], $this->admin);

        $fresh = $booking->fresh()->load('bookingRooms');
        $this->assertCount(2, $fresh->bookingRooms);
        $this->assertSame(2, (int) $fresh->guests);
    }

    public function test_physical_rooms_api_includes_room_type_id(): void
    {
        $checkIn = now()->addDays(14)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');

        $response = $this->getJson(route('api.room-types.physical-rooms', $this->roomTypeA) . '?' . http_build_query([
            'check_in'  => $checkIn,
            'check_out' => $checkOut,
        ]));

        $response->assertOk();
        $rooms = $response->json('rooms');
        $this->assertNotEmpty($rooms);
        $this->assertSame($this->roomTypeA->id, (int) $rooms[0]['room_type_id']);
        $this->assertSame($this->roomTypeA->name, $rooms[0]['room_type_name']);
    }

    public function test_on_add_room_physical_selected_accepts_payload_without_room_type_id(): void
    {
        $booking = $this->createManualBookingWithOneRoom(guests: 1);

        Livewire::actingAs($this->admin)
            ->test(BookingShow::class, ['booking' => $booking])
            ->set('addRoomRoomTypeId', (string) $this->roomTypeB->id)
            ->call('onAddRoomPhysicalSelected', [[
                'roomId'   => $this->physicalB->id,
                'roomName' => $this->physicalB->name,
            ]])
            ->assertHasNoErrors()
            ->assertSet('addRoomPhysicalRoomId', $this->physicalB->id)
            ->assertSet('addRoomPhysicalRoomName', $this->physicalB->name);
    }

    public function test_on_add_room_physical_selected_rejects_mismatched_room_type(): void
    {
        $booking = $this->createManualBookingWithOneRoom(guests: 1);

        Livewire::actingAs($this->admin)
            ->test(BookingShow::class, ['booking' => $booking])
            ->set('addRoomRoomTypeId', (string) $this->roomTypeA->id)
            ->call('onAddRoomPhysicalSelected', [[
                'roomId'     => $this->physicalB->id,
                'roomName'   => $this->physicalB->name,
                'roomTypeId' => $this->roomTypeB->id,
            ]])
            ->assertHasErrors(['addRoomRoomTypeId']);
    }

    public function test_commit_add_room_persists_physical_room_name_in_database(): void
    {
        $booking = $this->createManualBookingWithOneRoom(guests: 1);

        Livewire::actingAs($this->admin)
            ->test(BookingShow::class, ['booking' => $booking])
            ->set('addRoomRoomTypeId', (string) $this->roomTypeB->id)
            ->set('addRoomRoomRateId', (string) $this->rateB->id)
            ->call('onAddRoomPhysicalSelected', [[
                'roomId'   => $this->physicalB->id,
                'roomName' => $this->physicalB->name,
            ]])
            ->assertSet('addRoomPhysicalRoomId', $this->physicalB->id)
            ->call('commitAddRoomLine')
            ->assertHasNoErrors();

        $lines = $booking->fresh()->bookingRooms()->with('room')->orderBy('sort_order')->get();
        $this->assertCount(2, $lines);

        $newLine = $lines->last();
        $this->assertSame($this->physicalB->id, (int) $newLine->room_id);
        $this->assertSame('B-201', $newLine->room?->name);
    }

    public function test_add_room_reduces_room_type_availability(): void
    {
        $checkIn = now()->addDays(14)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');

        $availableBefore = $this->minAvailableRooms($this->roomTypeB, $checkIn, $checkOut);
        $this->assertGreaterThanOrEqual(1, $availableBefore);

        $booking = $this->createManualBookingWithOneRoom(guests: 1);

        app(BookingRoomModificationService::class)->addRoomLine($booking, [
            'room_type_id' => $this->roomTypeB->id,
            'room_rate_id' => $this->rateB->id,
            'room_id'      => $this->physicalB->id,
            'adults'       => 1,
            'guests'       => 1,
        ], $this->admin);

        $availableAfter = $this->minAvailableRooms($this->roomTypeB, $checkIn, $checkOut);
        $this->assertSame($availableBefore - 1, $availableAfter);
    }

    public function test_guest_addition_options_reflect_remaining_capacity(): void
    {
        $booking = $this->createManualBookingWithOneRoom(guests: 1);
        $service = app(BookingRoomModificationService::class);
        $lineId = $booking->bookingRooms->first()->id;

        $before = collect($service->roomLinesForGuestAddition($booking))->first();
        $this->assertTrue($before['can_add_guest']);
        $this->assertSame(1, $before['guests']);
        $this->assertSame(3, $before['capacity']);

        $booking = $service->addGuestToRoom($booking, $lineId, $this->admin);

        $mid = collect($service->roomLinesForGuestAddition($booking))->first();
        $this->assertTrue($mid['can_add_guest']);
        $this->assertSame(2, $mid['guests']);

        $booking = $service->addGuestToRoom($booking, $lineId, $this->admin);

        $full = collect($service->roomLinesForGuestAddition($booking))->first();
        $this->assertFalse($full['can_add_guest']);
        $this->assertSame(3, $full['guests']);
        $this->assertSame(3, $full['capacity']);

        $this->expectException(\RuntimeException::class);
        $service->addGuestToRoom($booking, $lineId, $this->admin);
    }

    public function test_add_room_line_updates_booking_occupancy_totals(): void
    {
        $booking = $this->createManualBookingWithOneRoom(guests: 1);

        $updated = app(BookingRoomModificationService::class)->addRoomLine($booking, [
            'room_type_id'     => $this->roomTypeB->id,
            'room_rate_id'     => $this->rateB->id,
            'room_id'          => $this->physicalB->id,
            'adults'           => 2,
            'children_under_6' => 1,
            'guests'           => 3,
            'extra_guests'     => 0,
            'bill_full_rooms'  => false,
        ], $this->admin);

        $this->assertSame(4, (int) $updated->guests);
        $this->assertSame(1, (int) $updated->children_under_6);
        $this->assertSame(2, (int) $updated->rooms_consumed);
        $this->assertSame(2, $updated->bookingRooms()->count());
    }

    private function minAvailableRooms(RoomType $roomType, string $checkIn, string $checkOut): int
    {
        $map = $roomType->availabilityMap($checkIn, $checkOut);

        return (int) collect($map)->min(fn ($day) => (int) ($day['available_rooms'] ?? 0));
    }

    private function createManualBookingWithOneRoom(int $guests = 1): Booking
    {
        $checkIn = now()->addDays(14)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');

        return app(ManualBookingService::class)->create(
            $this->roomTypeA->accommodation,
            [
                'check_in'   => $checkIn,
                'check_out'  => $checkOut,
                'room_lines' => [
                    [
                        'room_type_id'     => $this->roomTypeA->id,
                        'room_rate_id'     => $this->rateA->id,
                        'room_id'          => $this->physicalA->id,
                        'adults'           => $guests,
                        'children_under_6' => 0,
                        'guests'           => $guests,
                        'extra_guests'     => 0,
                        'bill_full_rooms'  => false,
                    ],
                ],
                'booker_national_id'   => '1234567890',
                'guest_contact_name'   => 'مهمان اصلی',
                'guest_contact_mobile' => '09120000000',
                'payment_method'       => 'cash',
                'services'             => [],
                'guest_details'        => [
                    [
                        'full_name'   => 'مهمان اصلی',
                        'national_id' => '1234567890',
                        'mobile'      => '09120000000',
                        'relation'    => '',
                    ],
                ],
            ],
            $this->admin,
        )->load(['bookingRooms', 'guestDetails']);
    }
}
