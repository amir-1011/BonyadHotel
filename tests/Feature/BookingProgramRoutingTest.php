<?php

namespace Tests\Feature;

use App\Livewire\Admin\BookingIndex;
use App\Livewire\Host\BookingIndex as HostBookingIndex;
use App\Livewire\ProgramBookingForm;
use App\Models\Booking;
use App\Models\Program;
use App\Models\ProgramEmployer;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use App\Services\ProgramBookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingProgramRoutingTest extends TestCase
{
    use RefreshDatabase;

    private $accommodation;
    private RoomType $roomType;
    private RoomRate $roomRate;
    private Room $room;
    private User $hostUser;
    private ProgramEmployer $employer;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $this->accommodation = $this->createTestAccommodation();
        $this->roomType = RoomType::create([
            'accommodation_id' => $this->accommodation->id,
            'name'             => 'اتاق',
            'capacity'         => 4,
            'room_count'       => 2,
            'is_active'        => true,
        ]);
        $this->roomRate = RoomRate::create([
            'room_type_id'    => $this->roomType->id,
            'name'            => 'نرخ',
            'price_per_night' => 500_000,
            'is_active'       => true,
        ]);
        $this->room = Room::create([
            'room_type_id' => $this->roomType->id,
            'name'         => '۱۰۱',
            'is_active'    => true,
        ]);

        $this->hostUser = User::create([
            'name'   => 'میزبان',
            'mobile' => '09120000001',
        ]);
        $this->hostUser->assignRole('host');
        $this->accommodation->hosts()->attach($this->hostUser->id);

        $this->employer = ProgramEmployer::create([
            'name'                    => 'کارفرما',
            'employer_code'           => '515101',
            'national_or_economic_id' => '5566778899',
            'mobile'                  => '09125556677',
        ]);
    }

    private function createProgramBooking(): Booking
    {
        $program = app(ProgramBookingService::class)->create(
            $this->accommodation->fresh(),
            [
                'title'               => 'اردوی مسیریابی',
                'program_type'        => Program::TYPE_CAMP,
                'program_employer_id' => $this->employer->id,
                'guest_count'         => 2,
                'rooms_allocated'     => 1,
                'check_in'            => Carbon::today()->addDays(5)->toDateString(),
                'check_out'           => Carbon::today()->addDays(8)->toDateString(),
                'room_lines'          => [[
                    'room_type_id' => $this->roomType->id,
                    'room_rate_id' => $this->roomRate->id,
                    'room_id'      => $this->room->id,
                    'room_name'    => $this->room->name,
                ]],
                'payment_type'        => Program::PAYMENT_CASH,
                'base_price'          => 1_000_000,
            ],
            $this->hostUser,
        );

        return $program->booking()->with('program')->first();
    }

    public function test_booking_panel_show_url_points_to_program_for_program_bookings(): void
    {
        $booking = $this->createProgramBooking();
        $program = $booking->program;

        $this->assertTrue($booking->isProgram());
        $this->assertStringContainsString('/admin/programs/' . $program->id, $booking->panelShowUrl('admin'));
        $this->assertStringContainsString('/host/programs/' . $program->id, $booking->panelShowUrl('host'));
    }

    public function test_manual_booking_panel_show_url_points_to_booking_show(): void
    {
        $guest = User::create(['name' => 'مهمان', 'mobile' => '09121112222']);

        $booking = Booking::create([
            'user_id'          => $guest->id,
            'accommodation_id' => $this->accommodation->id,
            'tracking_code'    => 'MANUAL0001',
            'booking_source'   => 'manual',
            'check_in'         => Carbon::today()->addDays(3)->toDateString(),
            'check_out'        => Carbon::today()->addDays(5)->toDateString(),
            'guests'           => 2,
            'nights'           => 2,
            'base_price'       => 1_000_000,
            'total_price'      => 1_000_000,
            'status'           => 'confirmed',
        ]);

        $this->assertStringContainsString('/admin/bookings/' . $booking->id, $booking->panelShowUrl('admin'));
        $this->assertStringContainsString('/host/bookings/' . $booking->id, $booking->panelShowUrl('host'));
    }

    public function test_admin_booking_index_links_program_booking_to_program_show(): void
    {
        $booking = $this->createProgramBooking();
        $program = $booking->program;

        $admin = User::create(['name' => 'ادمین', 'mobile' => '09123334444']);
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)
            ->test(BookingIndex::class)
            ->assertSee($booking->tracking_code)
            ->assertSee('/admin/programs/' . $program->id, false);
    }

    public function test_host_booking_index_links_program_booking_to_program_show(): void
    {
        $booking = $this->createProgramBooking();
        $program = $booking->program;

        $this->hostUser->update([
            'host_panel_permissions' => ['bookings.list' => ['read']],
        ]);

        Livewire::actingAs($this->hostUser)
            ->test(HostBookingIndex::class)
            ->assertSee($booking->tracking_code)
            ->assertSee('/host/programs/' . $program->id, false);
    }

    public function test_program_creation_confirmation_shows_only_program_link(): void
    {
        $this->actingAs($this->hostUser);

        $component = Livewire::test(ProgramBookingForm::class, [
            'panel' => 'host',
            'accommodationId' => $this->accommodation->id,
        ])
            ->set('programEmployerId', (string) $this->employer->id)
            ->set('title', 'اردوی تأیید')
            ->set('startDate', '1404/08/10')
            ->set('endDate', '1404/08/13')
            ->set('guestCount', 2)
            ->set('roomsAllocated', 1)
            ->set('basePrice', 1_000_000)
            ->set('paymentType', Program::PAYMENT_CASH)
            ->set('roomLines', [[
                'room_type_id'   => $this->roomType->id,
                'room_rate_id'   => $this->roomRate->id,
                'room_id'        => $this->room->id,
                'room_name'      => $this->room->name,
                'room_type_name' => $this->roomType->name,
            ]])
            ->call('submit')
            ->assertSet('step', 7);

        $program = Program::query()->where('title', 'اردوی تأیید')->first();
        $this->assertNotNull($program);

        $component
            ->assertSee('مشاهده برنامه')
            ->assertSee('/host/programs/' . $program->id, false)
            ->assertDontSee('مشاهده رزرو')
            ->assertDontSee('/host/bookings/', false);
    }
}
