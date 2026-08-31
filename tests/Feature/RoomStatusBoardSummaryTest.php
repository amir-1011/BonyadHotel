<?php

namespace Tests\Feature;

use App\Livewire\RoomStatusBoard;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\RoomTypeBlockedDate;
use App\Models\User;
use App\Services\RoomStatusBoardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoomStatusBoardSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summarize_rooms_counts_each_legend_status(): void
    {
        $summary = app(RoomStatusBoardService::class)->summarizeRooms([
            [
                'status' => 'available',
                'future_bookings' => [['is_program' => false]],
            ],
            [
                'status' => 'occupied',
                'current_booking' => ['guests' => 3],
                'future_bookings' => [['is_program' => true]],
            ],
            [
                'status' => 'program_occupied',
                'current_booking' => ['guests' => 10],
                'future_bookings' => [],
            ],
            [
                'status' => 'capacity_closed',
                'future_bookings' => [],
            ],
            [
                'status' => 'blocked',
                'future_bookings' => [],
            ],
        ]);

        $this->assertSame(5, $summary['total']);
        $this->assertSame(1, $summary['available']);
        $this->assertSame(1, $summary['occupied']);
        $this->assertSame(3, $summary['occupied_guests']);
        $this->assertSame(1, $summary['program']);
        $this->assertSame(10, $summary['program_guests']);
        $this->assertSame(2, $summary['future']);
        $this->assertSame(1, $summary['future_program']);
        $this->assertSame(1, $summary['capacity_closed']);
        $this->assertSame(1, $summary['blocked']);
    }

    public function test_board_summary_matches_physical_room_statuses_for_the_day(): void
    {
        [$host, $accommodation, $rooms] = $this->seedMixedStatusBoard();

        $board = app(RoomStatusBoardService::class)->buildForHost($host, now()->toDateString());
        $this->assertCount(1, $board);
        $this->assertSame($accommodation->name, $board[0]['accommodation_name']);

        $summary = $board[0]['summary'];
        $this->assertSame(5, $summary['total']);
        $this->assertSame(1, $summary['available']);
        $this->assertSame(1, $summary['occupied']);
        $this->assertSame(2, $summary['occupied_guests']);
        $this->assertSame(1, $summary['program']);
        $this->assertSame(4, $summary['program_guests']);
        $this->assertSame(2, $summary['future']);
        $this->assertSame(1, $summary['future_program']);
        $this->assertSame(1, $summary['capacity_closed']);
        $this->assertSame(1, $summary['blocked']);

        $byId = collect($board[0]['rooms'])->keyBy('id');
        $this->assertSame('occupied', $byId[$rooms['occupied']->id]['status']);
        $this->assertSame('program_occupied', $byId[$rooms['program']->id]['status']);
        $this->assertSame('available', $byId[$rooms['available']->id]['status']);
        $this->assertSame('blocked', $byId[$rooms['blocked']->id]['status']);
        $this->assertSame('capacity_closed', $byId[$rooms['closed']->id]['status']);
    }

    public function test_host_board_shows_kpi_cards_and_hides_physical_rooms_until_toggled(): void
    {
        [$host, $accommodation, $rooms] = $this->seedMixedStatusBoard();

        $component = Livewire::withoutLazyLoading()
            ->actingAs($host)
            ->test(RoomStatusBoard::class, ['panel' => 'host'])
            ->assertSee($accommodation->name)
            ->assertSee('مجموع اتاق')
            ->assertSee('مهمان فعلی')
            ->assertSee('اردو / برنامه')
            ->assertSee('رزرو آینده')
            ->assertSee('بسته (سیاست قیمتی)')
            ->assertSee('مسدود')
            ->assertSeeHtml('data-rsb-kpi="total"')
            ->assertSee('نمایش اتاق‌های فیزیکی')
            ->assertDontSeeHtml('data-rsb-physical-rooms="'.$accommodation->id.'"')
            ->assertDontSeeHtml('data-rsb-hide-rooms="'.$accommodation->id.'"')
            ->assertDontSeeHtml('<div class="room-status-box__name">'.$rooms['available']->name.'</div>');

        $component
            ->call('showPhysicalRooms', $accommodation->id)
            ->assertSet('expandedPhysicalRoomIds', [$accommodation->id])
            ->assertSeeHtml('data-rsb-physical-rooms="'.$accommodation->id.'"')
            ->assertSeeHtml('<div class="room-status-box__name">'.$rooms['available']->name.'</div>')
            ->assertSeeHtml('<div class="room-status-box__name">'.$rooms['occupied']->name.'</div>')
            ->assertSee('عدم نمایش')
            ->assertDontSeeHtml('data-rsb-show-rooms="'.$accommodation->id.'"');

        $component
            ->call('hidePhysicalRooms', $accommodation->id)
            ->assertSet('expandedPhysicalRoomIds', [])
            ->assertDontSeeHtml('data-rsb-physical-rooms="'.$accommodation->id.'"')
            ->assertSee('نمایش اتاق‌های فیزیکی')
            ->assertDontSeeHtml('<div class="room-status-box__name">'.$rooms['available']->name.'</div>');
    }

    public function test_physical_room_toggle_is_independent_per_accommodation(): void
    {
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);

        $host = User::create(['name' => 'میزبان', 'mobile' => '09120000881']);
        $host->assignRole('host');

        $first = $this->createTestAccommodation(['name' => 'نارنجستان ساری']);
        $second = $this->createTestAccommodation(['name' => 'اقامتگاه رشت']);
        $first->hosts()->attach($host->id);
        $second->hosts()->attach($host->id);

        $firstRoom = $this->createActiveRoom($first->id, 'اتاق-ساری-۱۰۱');
        $secondRoom = $this->createActiveRoom($second->id, 'اتاق-رشت-۲۰۱');

        Livewire::withoutLazyLoading()
            ->actingAs($host)
            ->test(RoomStatusBoard::class, ['panel' => 'host'])
            ->assertSee('نارنجستان ساری')
            ->assertSee('اقامتگاه رشت')
            ->call('showPhysicalRooms', $first->id)
            ->assertSeeHtml('<div class="room-status-box__name">'.$firstRoom->name.'</div>')
            ->assertDontSeeHtml('<div class="room-status-box__name">'.$secondRoom->name.'</div>')
            ->assertSeeHtml('data-rsb-physical-rooms="'.$first->id.'"')
            ->assertDontSeeHtml('data-rsb-physical-rooms="'.$second->id.'"')
            ->call('showPhysicalRooms', $second->id)
            ->assertSeeHtml('<div class="room-status-box__name">'.$firstRoom->name.'</div>')
            ->assertSeeHtml('<div class="room-status-box__name">'.$secondRoom->name.'</div>')
            ->call('hidePhysicalRooms', $first->id)
            ->assertDontSeeHtml('<div class="room-status-box__name">'.$firstRoom->name.'</div>')
            ->assertSeeHtml('<div class="room-status-box__name">'.$secondRoom->name.'</div>');
    }

    public function test_hiding_physical_rooms_closes_that_accommodation_room_detail(): void
    {
        [$host, $accommodation, $rooms] = $this->seedMixedStatusBoard();

        Livewire::actingAs($host)
            ->test(RoomStatusBoard::class, ['panel' => 'host'])
            ->call('showPhysicalRooms', $accommodation->id)
            ->call('selectRoom', $accommodation->id, $rooms['occupied']->id)
            ->assertNotSet('selectedRoom', null)
            ->call('hidePhysicalRooms', $accommodation->id)
            ->assertSet('selectedRoom', null)
            ->assertDontSeeHtml('modal fade show d-block room-status-detail-modal');
    }

    public function test_host_cannot_expand_rooms_of_unmanaged_accommodation(): void
    {
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);

        $host = User::create(['name' => 'میزبان', 'mobile' => '09120000882']);
        $host->assignRole('host');
        $owned = $this->createTestAccommodation(['name' => 'اقامتگاه خودم']);
        $other = $this->createTestAccommodation(['name' => 'اقامتگاه دیگران']);
        $owned->hosts()->attach($host->id);
        $this->createActiveRoom($owned->id, 'اتاق-خودم');
        $this->createActiveRoom($other->id, 'اتاق-دیگران');

        Livewire::actingAs($host)
            ->test(RoomStatusBoard::class, ['panel' => 'host'])
            ->call('showPhysicalRooms', $other->id)
            ->assertSet('expandedPhysicalRoomIds', [])
            ->assertDontSeeHtml('<div class="room-status-box__name">اتاق-دیگران</div>');
    }

    public function test_layout_edit_still_shows_physical_rooms_without_manual_expand(): void
    {
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);

        $accommodation = $this->createTestAccommodation();
        $this->createActiveRoom($accommodation->id, '۱۰۱');
        $host = User::create(['name' => 'میزبان', 'mobile' => '09120000883']);
        $host->assignRole('host');
        $accommodation->hosts()->attach($host->id);

        Livewire::actingAs($host)
            ->test(RoomStatusBoard::class, ['panel' => 'host'])
            ->assertDontSeeHtml('room-status-row__drag')
            ->call('toggleLayoutEdit')
            ->assertSet('layoutEditMode', true)
            ->assertSee('room-status-row__drag', escape: false)
            ->assertSeeHtml('<span class="room-status-box__name">۱۰۱</span>');
    }

    /**
     * @return array{0: User, 1: \App\Models\Accommodation, 2: array<string, Room>}
     */
    private function seedMixedStatusBoard(): array
    {
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $accommodation = $this->createTestAccommodation(['name' => 'نارنجستان ساری']);
        $roomType = RoomType::create([
            'accommodation_id' => $accommodation->id,
            'name'             => 'دو تخته',
            'capacity'         => 2,
            'room_count'       => 4,
            'is_active'        => true,
        ]);
        $roomRate = RoomRate::create([
            'room_type_id'    => $roomType->id,
            'name'            => 'نرخ استاندارد',
            'price_per_night' => 1_000_000,
            'is_active'       => true,
        ]);

        $rooms = [
            'occupied'  => Room::create(['room_type_id' => $roomType->id, 'name' => 'اتاق-اشغال', 'sort_order' => 1, 'is_active' => true]),
            'program'   => Room::create(['room_type_id' => $roomType->id, 'name' => 'اتاق-اردو', 'sort_order' => 2, 'is_active' => true]),
            'available' => Room::create(['room_type_id' => $roomType->id, 'name' => 'اتاق-آزاد', 'sort_order' => 3, 'is_active' => true]),
            'blocked'   => Room::create(['room_type_id' => $roomType->id, 'name' => 'اتاق-مسدود', 'sort_order' => 4, 'is_active' => true]),
            'closed'    => Room::create(['room_type_id' => $roomType->id, 'name' => 'اتاق-بسته', 'sort_order' => 5, 'is_active' => true]),
        ];

        $host = User::create(['name' => 'میزبان', 'mobile' => '09120000880']);
        $host->assignRole('host');
        $accommodation->hosts()->attach($host->id);

        $guest = User::create(['name' => 'مهمان', 'mobile' => '09121110001', 'national_id' => '0011111111']);
        $guest->assignRole('guest');

        $today = now()->toDateString();
        $occupied = Booking::create([
            'user_id'              => $guest->id,
            'accommodation_id'     => $accommodation->id,
            'booking_source'       => 'manual',
            'check_in'             => $today,
            'check_out'            => Carbon::parse($today)->addDays(2)->toDateString(),
            'nights'               => 2,
            'guests'               => 2,
            'base_price'           => 1_000_000,
            'services_subtotal'    => 0,
            'discount_amount'      => 0,
            'total_price'          => 1_000_000,
            'status'               => 'confirmed',
            'tracking_code'        => 'RSBOCC001',
            'guest_contact_name'   => 'مهمان فعلی',
            'guest_contact_mobile' => '09121110001',
        ]);
        BookingRoom::create([
            'booking_id'   => $occupied->id,
            'room_type_id' => $roomType->id,
            'room_rate_id' => $roomRate->id,
            'room_id'      => $rooms['occupied']->id,
            'adults'       => 2,
            'guests'       => 2,
            'sort_order'   => 0,
        ]);

        $futureOnOccupied = Booking::create([
            'user_id'              => $guest->id,
            'accommodation_id'     => $accommodation->id,
            'booking_source'       => 'program',
            'check_in'             => Carbon::parse($today)->addDays(5)->toDateString(),
            'check_out'            => Carbon::parse($today)->addDays(8)->toDateString(),
            'nights'               => 3,
            'guests'               => 8,
            'base_price'           => 1_000_000,
            'services_subtotal'    => 0,
            'discount_amount'      => 0,
            'total_price'          => 1_000_000,
            'status'               => 'confirmed',
            'tracking_code'        => 'RSBFUT002',
            'guest_contact_name'   => 'اردوی بعدی',
            'guest_contact_mobile' => '09121110002',
        ]);
        BookingRoom::create([
            'booking_id'   => $futureOnOccupied->id,
            'room_type_id' => $roomType->id,
            'room_rate_id' => $roomRate->id,
            'room_id'      => $rooms['occupied']->id,
            'adults'       => 1,
            'guests'       => 1,
            'sort_order'   => 0,
        ]);

        $program = Booking::create([
            'user_id'              => $host->id,
            'accommodation_id'     => $accommodation->id,
            'booking_source'       => 'program',
            'check_in'             => $today,
            'check_out'            => Carbon::parse($today)->addDays(3)->toDateString(),
            'nights'               => 3,
            'guests'               => 4,
            'base_price'           => 1_000_000,
            'services_subtotal'    => 0,
            'discount_amount'      => 0,
            'total_price'          => 1_000_000,
            'status'               => 'confirmed',
            'tracking_code'        => 'RSBPRG003',
            'guest_contact_name'   => 'سازمان اردو',
            'guest_contact_mobile' => '09123334444',
        ]);
        BookingRoom::create([
            'booking_id'   => $program->id,
            'room_type_id' => $roomType->id,
            'room_rate_id' => $roomRate->id,
            'room_id'      => $rooms['program']->id,
            'adults'       => 1,
            'guests'       => 4,
            'sort_order'   => 0,
        ]);

        $futureAvailable = Booking::create([
            'user_id'              => $guest->id,
            'accommodation_id'     => $accommodation->id,
            'booking_source'       => 'manual',
            'check_in'             => Carbon::parse($today)->addDays(4)->toDateString(),
            'check_out'            => Carbon::parse($today)->addDays(6)->toDateString(),
            'nights'               => 2,
            'guests'               => 2,
            'base_price'           => 1_000_000,
            'services_subtotal'    => 0,
            'discount_amount'      => 0,
            'total_price'          => 1_000_000,
            'status'               => 'confirmed',
            'tracking_code'        => 'RSBFUT004',
            'guest_contact_name'   => 'رزرو آینده',
            'guest_contact_mobile' => '09121110003',
        ]);
        BookingRoom::create([
            'booking_id'   => $futureAvailable->id,
            'room_type_id' => $roomType->id,
            'room_rate_id' => $roomRate->id,
            'room_id'      => $rooms['available']->id,
            'adults'       => 2,
            'guests'       => 2,
            'sort_order'   => 0,
        ]);

        RoomTypeBlockedDate::create([
            'room_type_id' => $roomType->id,
            'room_id'      => $rooms['blocked']->id,
            'date'         => $today,
            'reason'       => 'تعمیرات',
        ]);

        return [$host, $accommodation, $rooms];
    }

    private function createActiveRoom(int $accommodationId, string $name): Room
    {
        $roomType = RoomType::create([
            'accommodation_id' => $accommodationId,
            'name'             => 'دو تخته',
            'capacity'         => 2,
            'room_count'       => 1,
            'is_active'        => true,
        ]);

        return Room::create([
            'room_type_id' => $roomType->id,
            'name'         => $name,
            'sort_order'   => 1,
            'is_active'    => true,
        ]);
    }
}
