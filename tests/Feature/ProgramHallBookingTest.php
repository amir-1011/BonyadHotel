<?php

namespace Tests\Feature;

use App\Livewire\ProgramBookingForm;
use App\Models\Hall;
use App\Models\HallType;
use App\Models\PlatformCommissionEntry;
use App\Models\Program;
use App\Models\ProgramEmployer;
use App\Models\User;
use App\Services\PlatformCommissionService;
use App\Services\ProgramBookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProgramHallBookingTest extends TestCase
{
    use RefreshDatabase;

    private $accommodation;
    private User $admin;
    private User $host;
    private ProgramEmployer $employer;
    private Hall $hall;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $this->admin = User::create(['name' => 'ادمین رویداد', 'mobile' => '09123330001']);
        $this->admin->assignRole('super_admin');

        $this->host = User::create(['name' => 'میزبان رویداد', 'mobile' => '09123330002']);
        $this->host->assignRole('host');

        $this->accommodation = $this->createTestAccommodation();
        $this->accommodation->hosts()->attach($this->host->id);

        $this->employer = ProgramEmployer::create([
            'name'                    => 'کارفرمای همایش',
            'employer_code'           => 'HALL-EMP-1',
            'national_or_economic_id' => '1122334455',
            'mobile'                  => '09125550000',
        ]);

        $type = HallType::query()->where('name', 'همایش')->first()
            ?? HallType::query()->firstOrCreate(['name' => 'همایش'], ['sort_order' => 1]);
        $this->hall = Hall::create([
            'accommodation_id' => $this->accommodation->id,
            'hall_type_id'     => $type->id,
            'name'             => 'سالن اصلی',
            'capacity'         => 200,
            'is_active'        => true,
            'amenities'        => ['سیستم صوتی'],
        ]);
    }

    public function test_hall_program_is_created_without_rooms_or_services(): void
    {
        $date = Carbon::today()->addDays(10)->toDateString();

        $program = $this->createHallProgram($date, '10:00', '12:00');

        $this->assertSame(Program::TYPE_HALL, $program->program_type);
        $this->assertSame('سالن همایش', $program->programTypeLabel());
        $this->assertSame($this->hall->id, $program->hall_id);
        $this->assertSame(0, (int) $program->rooms_allocated);
        $this->assertSame(0, (int) $program->booking->rooms_consumed);
        $this->assertSame(0, $program->booking->bookingRooms()->count());
        $this->assertSame(0, $program->booking->services()->count());
        $this->assertSame($date, $program->booking->check_in->toDateString());
        $this->assertSame(Carbon::parse($date)->addDay()->toDateString(), $program->booking->check_out->toDateString());
    }

    public function test_overlapping_hall_session_is_rejected(): void
    {
        $date = Carbon::today()->addDays(12)->toDateString();
        $this->createHallProgram($date, '10:00', '12:00');

        $this->expectException(\RuntimeException::class);
        $this->createHallProgram($date, '11:00', '13:00', 'رویداد تداخلی');
    }

    public function test_adjacent_hall_session_is_allowed(): void
    {
        $date = Carbon::today()->addDays(13)->toDateString();
        $this->createHallProgram($date, '10:00', '12:00');

        $second = $this->createHallProgram($date, '12:00', '14:00', 'سانس مجاور');

        $this->assertSame(Program::TYPE_HALL, $second->program_type);
        $this->assertDatabaseCount('programs', 2);
    }

    public function test_cancelled_hall_program_frees_the_slot(): void
    {
        $date = Carbon::today()->addDays(14)->toDateString();
        $program = $this->createHallProgram($date, '16:00', '18:00');

        $program->update(['status' => Program::STATUS_CANCELLED]);
        $program->booking->update(['status' => 'cancelled']);

        $replacement = $this->createHallProgram($date, '16:00', '18:00', 'جایگزین');

        $this->assertNotSame($program->id, $replacement->id);
    }

    public function test_hall_program_accrues_fixed_commission(): void
    {
        $date = Carbon::today()->addDays(15)->toDateString();
        $program = $this->createHallProgram($date, '09:00', '11:00');
        $commission = app(PlatformCommissionService::class);

        $this->assertSame($commission->fixedAmount(), $commission->calculateBookingCommission($program->booking));
        $this->assertSame($commission->fixedAmount(), $commission->walletBalance());
        $this->assertSame(1, PlatformCommissionEntry::where('booking_id', $program->booking_id)->count());
    }

    public function test_livewire_hall_wizard_skips_services_and_rooms(): void
    {
        $this->actingAs($this->host);

        Livewire::test(ProgramBookingForm::class, [
            'panel' => 'host',
            'accommodationId' => $this->accommodation->id,
        ])
            ->set('programType', Program::TYPE_HALL)
            ->assertSee('انتخاب سالن')
            ->assertDontSee('خدمات و نرخ')
            ->assertDontSee('تعداد اتاق اختصاص داده شده به این رزرو')
            ->assertSee('تاریخ برگزاری')
            ->set('programEmployerId', (string) $this->employer->id)
            ->set('title', 'همایش استانی')
            ->set('startDate', '1404/08/20')
            ->set('hallStartTime', '10:00')
            ->set('hallEndTime', '12:00')
            ->set('guestCount', 40)
            ->set('hallId', $this->hall->id)
            ->set('basePrice', 2_000_000)
            ->set('paymentType', Program::PAYMENT_CASH)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('step', 7);

        $program = Program::query()->where('title', 'همایش استانی')->first();
        $this->assertNotNull($program);
        $this->assertSame(Program::TYPE_HALL, $program->program_type);
        $this->assertSame($this->hall->id, $program->hall_id);
    }

    public function test_program_type_options_include_hall_label(): void
    {
        $this->assertSame('سالن همایش', Program::typeOptions()[Program::TYPE_HALL]);
    }

    public function test_admin_program_list_shows_hall_type_label(): void
    {
        $this->createHallProgram(Carbon::today()->addDays(16)->toDateString(), '09:00', '10:00', 'همایش لیست');

        $this->actingAs($this->admin)
            ->get(route('admin.programs.index'))
            ->assertOk()
            ->assertSee('سالن همایش')
            ->assertSee('همایش لیست');
    }

    /**
     * @return Program
     */
    private function createHallProgram(
        string $date,
        string $start,
        string $end,
        string $title = 'رویداد سالن',
    ): Program {
        return app(ProgramBookingService::class)->create(
            $this->accommodation->fresh(),
            [
                'title'           => $title,
                'program_type'    => Program::TYPE_HALL,
                'guest_count'     => 20,
                'rooms_allocated' => 0,
                'check_in'        => $date,
                'check_out'       => Carbon::parse($date)->addDay()->toDateString(),
                'hall_id'         => $this->hall->id,
                'hall_start_time' => $start,
                'hall_end_time'   => $end,
                'base_price'      => 1_500_000,
                'program_employer_id' => $this->employer->id,
                'guest_details'   => [
                    ['full_name' => 'مهمان همایش', 'national_id' => '1234567890', 'mobile' => '09121112233', 'relation' => ''],
                ],
            ],
            $this->admin,
        );
    }
}
