<?php

namespace Tests\Feature;

use App\Livewire\BookingServicesEditor;
use App\Livewire\RoomStatusBoard;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingService;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use App\Services\ManualBookingService;
use Carbon\Carbon;
use Database\Seeders\VeteranPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoomStatusBoardBookingServicesTest extends TestCase
{
    use RefreshDatabase;

    private $accommodation;
    private RoomType $roomType;
    private RoomRate $roomRate;
    private Room $physicalRoom;
    private User $host;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VeteranPolicySeeder::class);

        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $this->accommodation = $this->createTestAccommodation();
        $this->roomType = RoomType::create([
            'accommodation_id' => $this->accommodation->id,
            'name'             => 'اتاق دو تخته',
            'capacity'         => 2,
            'room_count'       => 3,
            'is_active'        => true,
        ]);
        $this->roomRate = RoomRate::create([
            'room_type_id'    => $this->roomType->id,
            'name'            => 'نرخ استاندارد',
            'price_per_night' => 1_000_000,
            'is_active'       => true,
        ]);
        $this->physicalRoom = Room::create([
            'room_type_id' => $this->roomType->id,
            'name'         => '۱۰۱',
            'sort_order'   => 1,
            'is_active'    => true,
        ]);

        $this->host = User::create(['name' => 'میزبان', 'mobile' => '09120000999']);
        $this->host->assignRole('host');
        $this->accommodation->hosts()->attach($this->host->id);

        $guest = User::create(['name' => 'مهمان', 'mobile' => '09121112222', 'national_id' => '1234567890']);
        $guest->assignRole('guest');

        $checkIn = now()->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');

        $this->booking = Booking::create([
            'user_id'              => $guest->id,
            'accommodation_id'     => $this->accommodation->id,
            'booking_source'       => 'manual',
            'check_in'             => $checkIn,
            'check_out'            => $checkOut,
            'nights'               => 2,
            'guests'               => 2,
            'base_price'           => 2_000_000,
            'services_subtotal'    => 300_000,
            'discount_amount'      => 0,
            'total_price'          => 2_300_000,
            'status'               => 'confirmed',
            'tracking_code'        => 'TESTBOARD01',
            'guest_contact_name'   => 'مهمان',
            'guest_contact_mobile' => '09121112222',
        ]);

        BookingRoom::create([
            'booking_id'   => $this->booking->id,
            'room_type_id' => $this->roomType->id,
            'room_rate_id' => $this->roomRate->id,
            'room_id'      => $this->physicalRoom->id,
            'adults'       => 2,
            'guests'       => 2,
            'sort_order'   => 0,
        ]);

        BookingService::create([
            'booking_id'  => $this->booking->id,
            'name'        => 'صبحانه',
            'unit_price'  => 100_000,
            'quantity'    => 3,
            'total'       => 300_000,
            'sort_order'  => 0,
        ]);
    }

    public function test_room_status_board_opens_services_for_occupied_room(): void
    {
        Livewire::actingAs($this->host)
            ->test(RoomStatusBoard::class, ['panel' => 'host'])
            ->call('selectRoom', $this->accommodation->id, $this->physicalRoom->id)
            ->assertSet('servicesBookingId', $this->booking->id)
            ->assertSee('مدیریت خدمات رزرو')
            ->assertSee('صبحانه');
    }

    public function test_booking_services_editor_adjusts_quantity_and_recalculates(): void
    {
        Livewire::actingAs($this->host)
            ->test(BookingServicesEditor::class, [
                'bookingId' => $this->booking->id,
                'panel'     => 'host',
            ])
            ->call('adjustServiceQuantity', $this->booking->services()->first()->id, 1)
            ->assertDispatched('booking-services-updated');

        $service = $this->booking->fresh()->services()->first();
        $this->assertSame(4, $service->quantity);
        $this->assertSame(400_000, $service->total);
        $this->assertSame(400_000, $this->booking->fresh()->services_subtotal);
    }

    public function test_booking_services_editor_adds_custom_service(): void
    {
        Livewire::actingAs($this->host)
            ->test(BookingServicesEditor::class, [
                'bookingId' => $this->booking->id,
                'panel'     => 'host',
            ])
            ->set('newServiceCatalogId', 'custom')
            ->set('newServiceName', 'لباسشویی')
            ->set('newServicePrice', 50_000)
            ->set('newServiceQty', 2)
            ->call('addServiceLine')
            ->assertDispatched('booking-services-updated');

        $booking = $this->booking->fresh();
        $this->assertCount(2, $booking->services);
        $laundry = $booking->services()->where('name', 'لباسشویی')->first();
        $this->assertNotNull($laundry);
        $this->assertSame(2, $laundry->quantity);
        $this->assertSame(100_000, $laundry->total);
    }

    public function test_booking_services_editor_removes_service_line(): void
    {
        $serviceId = $this->booking->services()->first()->id;

        Livewire::actingAs($this->host)
            ->test(BookingServicesEditor::class, [
                'bookingId' => $this->booking->id,
                'panel'     => 'host',
            ])
            ->call('removeServiceLine', $serviceId);

        $this->assertCount(0, $this->booking->fresh()->services);
        $this->assertSame(0, $this->booking->fresh()->services_subtotal);
    }

    public function test_unauthorized_host_cannot_edit_booking_services(): void
    {
        $otherHost = User::create(['name' => 'میزبان دیگر', 'mobile' => '09123334444']);
        $otherHost->assignRole('host');

        Livewire::actingAs($otherHost)
            ->test(BookingServicesEditor::class, [
                'bookingId' => $this->booking->id,
                'panel'     => 'host',
            ])
            ->assertForbidden();
    }
}
