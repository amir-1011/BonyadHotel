<?php

namespace Tests\Feature;

use App\Livewire\RoomStatusBoard;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use App\Services\RoomBoardLayoutService;
use App\Services\RoomStatusBoardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoomStatusBoardLayoutTest extends TestCase
{
    use RefreshDatabase;
    public function test_sort_layout_row_reorders_edit_layout(): void
    {
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);

        $accommodation = $this->createTestAccommodation();
        $roomType = RoomType::create([
            'accommodation_id' => $accommodation->id,
            'name'             => 'دو تخته',
            'capacity'         => 2,
            'room_count'       => 3,
            'is_active'        => true,
        ]);

        $roomA = Room::create(['room_type_id' => $roomType->id, 'name' => '۱۰۱', 'sort_order' => 1, 'is_active' => true]);
        $roomB = Room::create(['room_type_id' => $roomType->id, 'name' => '۱۰۲', 'sort_order' => 2, 'is_active' => true]);
        $roomC = Room::create(['room_type_id' => $roomType->id, 'name' => '۲۰۱', 'sort_order' => 3, 'is_active' => true]);

        $host = User::create(['name' => 'میزبان', 'mobile' => '09120000111']);
        $host->assignRole('host');
        $accommodation->hosts()->attach($host->id);

        $component = Livewire::actingAs($host)
            ->test(RoomStatusBoard::class, ['panel' => 'host'])
            ->call('toggleLayoutEdit')
            ->assertSet('layoutEditMode', true);

        $accKey = (string) $accommodation->id;
        $component->set("editLayouts.{$accKey}.rows", [
            [$roomA->id, $roomB->id],
            [$roomC->id],
        ]);
        $component->set("editLayouts.{$accKey}.row_labels", ['طبقه ۱', 'طبقه ۲']);

        $component->call('sortLayoutRow', 0, 1, (string) $accommodation->id);

        $component->assertSet("editLayouts.{$accKey}.rows", [
            [$roomC->id],
            [$roomA->id, $roomB->id],
        ]);
        $component->assertSet("editLayouts.{$accKey}.row_labels", ['طبقه ۲', 'طبقه ۱']);
    }

    public function test_layout_edit_view_includes_row_drag_handle(): void
    {
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);

        $accommodation = $this->createTestAccommodation();
        $roomType = RoomType::create([
            'accommodation_id' => $accommodation->id,
            'name'             => 'دو تخته',
            'capacity'         => 2,
            'room_count'       => 1,
            'is_active'        => true,
        ]);
        Room::create(['room_type_id' => $roomType->id, 'name' => '۱۰۱', 'sort_order' => 1, 'is_active' => true]);

        $host = User::create(['name' => 'میزبان', 'mobile' => '09120000222']);
        $host->assignRole('host');
        $accommodation->hosts()->attach($host->id);

        Livewire::actingAs($host)
            ->test(RoomStatusBoard::class, ['panel' => 'host'])
            ->call('toggleLayoutEdit')
            ->assertSee('room-status-row__drag', escape: false)
            ->assertSee('room-status-rows-list', escape: false)
            ->assertSee('جابجایی ردیف');
    }

    public function test_saved_layout_is_shared_across_hosts_of_same_accommodation(): void
    {
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);

        $accommodation = $this->createTestAccommodation();
        $roomType = RoomType::create([
            'accommodation_id' => $accommodation->id,
            'name'             => 'دو تخته',
            'capacity'         => 2,
            'room_count'       => 2,
            'is_active'        => true,
        ]);

        $roomA = Room::create(['room_type_id' => $roomType->id, 'name' => '۱۰۱', 'sort_order' => 1, 'is_active' => true]);
        $roomB = Room::create(['room_type_id' => $roomType->id, 'name' => '۱۰۲', 'sort_order' => 2, 'is_active' => true]);

        $hostA = User::create(['name' => 'میزبان الف', 'mobile' => '09120000333']);
        $hostA->assignRole('host');
        $hostB = User::create(['name' => 'میزبان ب', 'mobile' => '09120000444']);
        $hostB->assignRole('host');
        $accommodation->hosts()->attach([$hostA->id, $hostB->id]);

        Livewire::actingAs($hostA)
            ->test(RoomStatusBoard::class, ['panel' => 'host'])
            ->call('toggleLayoutEdit')
            ->set('editLayouts.' . $accommodation->id . '.rows', [[$roomB->id], [$roomA->id]])
            ->set('editLayouts.' . $accommodation->id . '.row_labels', ['طبقه ۲', 'طبقه ۱'])
            ->call('saveLayout');

        $accommodation->refresh();
        $this->assertSame(
            [[$roomB->id], [$roomA->id]],
            $accommodation->room_board_layout['rows'],
        );

        $boardForHostB = app(RoomStatusBoardService::class)->buildForHost($hostB);
        $rows = $boardForHostB[0]['rows'];

        $this->assertSame('طبقه ۲', $rows[0]['label']);
        $this->assertSame($roomB->id, $rows[0]['rooms'][0]['id']);
        $this->assertSame('طبقه ۱', $rows[1]['label']);
        $this->assertSame($roomA->id, $rows[1]['rooms'][0]['id']);
    }

    public function test_host_without_dashboard_edit_cannot_open_building_layout(): void
    {
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);

        $accommodation = $this->createTestAccommodation();
        $roomType = RoomType::create([
            'accommodation_id' => $accommodation->id,
            'name'             => 'دو تخته',
            'capacity'         => 2,
            'room_count'       => 1,
            'is_active'        => true,
        ]);
        Room::create(['room_type_id' => $roomType->id, 'name' => '۱۰۱', 'sort_order' => 1, 'is_active' => true]);

        $host = User::create([
            'name'                   => 'میزبان',
            'mobile'                 => '09120000555',
            'host_panel_permissions' => [
                'dashboard' => ['read'],
            ],
        ]);
        $host->assignRole('host');
        $accommodation->hosts()->attach($host->id);

        Livewire::actingAs($host)
            ->test(RoomStatusBoard::class, ['panel' => 'host'])
            ->assertDontSee('نقشه ساختمان')
            ->call('toggleLayoutEdit')
            ->assertForbidden()
            ->assertSet('layoutEditMode', false);
    }

    public function test_admin_panel_uses_accommodation_layout(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $accommodation = $this->createTestAccommodation();
        $roomType = RoomType::create([
            'accommodation_id' => $accommodation->id,
            'name'             => 'دو تخته',
            'capacity'         => 2,
            'room_count'       => 2,
            'is_active'        => true,
        ]);

        $roomA = Room::create(['room_type_id' => $roomType->id, 'name' => '۳۰۱', 'sort_order' => 1, 'is_active' => true]);
        $roomB = Room::create(['room_type_id' => $roomType->id, 'name' => '۳۰۲', 'sort_order' => 2, 'is_active' => true]);

        app(RoomBoardLayoutService::class)->saveAccommodationLayout($accommodation, [
            'cols'       => 4,
            'rows'       => [[$roomB->id], [$roomA->id]],
            'row_labels' => ['بالا', 'پایین'],
        ]);

        $board = app(RoomStatusBoardService::class)->buildForAccommodation($accommodation->id);
        $rows = $board[0]['rows'];

        $this->assertSame('بالا', $rows[0]['label']);
        $this->assertSame($roomB->id, $rows[0]['rooms'][0]['id']);
        $this->assertSame('پایین', $rows[1]['label']);
        $this->assertSame($roomA->id, $rows[1]['rooms'][0]['id']);
    }

    public function test_program_booking_room_uses_purple_status_on_board(): void
    {
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $accommodation = $this->createTestAccommodation();
        $roomType = RoomType::create([
            'accommodation_id' => $accommodation->id,
            'name'             => 'دو تخته',
            'capacity'         => 2,
            'room_count'       => 2,
            'is_active'        => true,
        ]);
        $roomRate = RoomRate::create([
            'room_type_id'    => $roomType->id,
            'name'            => 'نرخ اردو',
            'price_per_night' => 500_000,
            'is_active'       => true,
        ]);
        $room = Room::create(['room_type_id' => $roomType->id, 'name' => '۱۰۱', 'sort_order' => 1, 'is_active' => true]);

        $host = User::create(['name' => 'میزبان', 'mobile' => '09120000555']);
        $host->assignRole('host');
        $accommodation->hosts()->attach($host->id);

        $checkIn = now()->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(3)->format('Y-m-d');

        $booking = Booking::create([
            'user_id'              => $host->id,
            'accommodation_id'     => $accommodation->id,
            'booking_source'       => 'program',
            'check_in'             => $checkIn,
            'check_out'            => $checkOut,
            'nights'               => 3,
            'guests'               => 10,
            'base_price'           => 1_000_000,
            'services_subtotal'    => 0,
            'discount_amount'      => 0,
            'total_price'          => 1_000_000,
            'status'               => 'confirmed',
            'tracking_code'        => 'PROGCAMP01',
            'guest_contact_name'   => 'سازمان اردو',
            'guest_contact_mobile' => '09123334444',
        ]);

        BookingRoom::create([
            'booking_id'   => $booking->id,
            'room_type_id' => $roomType->id,
            'room_rate_id' => $roomRate->id,
            'room_id'      => $room->id,
            'adults'       => 1,
            'guests'       => 1,
            'sort_order'   => 0,
        ]);

        $board = app(RoomStatusBoardService::class)->buildForHost($host, $checkIn);
        $programRoom = collect($board[0]['rooms'])->firstWhere('id', $room->id);

        $this->assertNotNull($programRoom);
        $this->assertSame('program_occupied', $programRoom['status']);
        $this->assertSame('purple', $programRoom['color']);
        $this->assertSame('اردو / برنامه', $programRoom['status_label']);
        $this->assertTrue($programRoom['current_booking']['is_program']);
    }
}
