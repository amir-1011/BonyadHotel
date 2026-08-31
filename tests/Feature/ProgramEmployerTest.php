<?php

namespace Tests\Feature;

use App\Livewire\ProgramBookingForm;
use App\Models\Program;
use App\Models\ProgramEmployer;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use App\Services\EmployerUserProvisioner;
use App\Services\ProgramBookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProgramEmployerTest extends TestCase
{
    use RefreshDatabase;

    private $accommodation;
    private RoomType $roomType;
    private RoomRate $roomRate;
    private Room $room;
    private User $hostUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $this->accommodation = $this->createTestAccommodation();
        $this->roomType = RoomType::create([
            'accommodation_id' => $this->accommodation->id,
            'name'             => 'اتاق اردو',
            'capacity'         => 4,
            'room_count'       => 3,
            'is_active'        => true,
        ]);
        $this->roomRate = RoomRate::create([
            'room_type_id'    => $this->roomType->id,
            'name'            => 'نرخ اردو',
            'price_per_night' => 500_000,
            'is_active'       => true,
        ]);
        $this->room = Room::create([
            'room_type_id' => $this->roomType->id,
            'name'         => '۱۰۱',
            'is_active'    => true,
        ]);

        $this->hostUser = User::create([
            'name'   => 'میزبان تست',
            'mobile' => '09120000001',
        ]);
        $this->hostUser->assignRole('host');
        $this->accommodation->hosts()->attach($this->hostUser->id);
    }

    public function test_program_booking_service_links_employer_to_user(): void
    {
        $employer = ProgramEmployer::create([
            'name'                    => 'وزارت آموزش',
            'employer_code'           => 'EMP-001',
            'national_or_economic_id' => '1234567890',
            'mobile'                  => '09121112233',
        ]);

        $checkIn = Carbon::today()->addDays(5)->toDateString();
        $checkOut = Carbon::today()->addDays(8)->toDateString();

        $program = app(ProgramBookingService::class)->create(
            $this->accommodation->fresh(),
            $this->baseProgramPayload($checkIn, $checkOut, $employer->id),
            $this->hostUser,
        );

        $employer->refresh();
        $this->assertNotNull($employer->user_id);
        $this->assertSame($employer->id, $program->program_employer_id);
        $this->assertSame('وزارت آموزش', $program->booking->guest_contact_name);
        $this->assertSame('ادارات و ارگان‌ها', $employer->user->roleBadgeLabel());
    }

    public function test_employer_user_provisioner_creates_guest_user(): void
    {
        $employer = ProgramEmployer::create([
            'name'                    => 'سازمان جهاد دانشگاهی',
            'employer_code'           => 'EMP-002',
            'national_or_economic_id' => '2234567890',
            'mobile'                  => '09124445566',
        ]);

        $linked = app(EmployerUserProvisioner::class)->linkEmployer($employer);

        $this->assertNotNull($linked->user_id);
        $this->assertTrue($linked->user->hasRole('guest'));
        $this->assertSame('ادارات و ارگان‌ها', $linked->user->roleBadgeLabel());
    }

    public function test_program_form_can_add_employer_to_catalog(): void
    {
        $this->actingAs($this->hostUser);

        Livewire::test(ProgramBookingForm::class, ['panel' => 'host', 'accommodationId' => $this->accommodation->id])
            ->call('openEmployerModal')
            ->set('newEmployerName', 'شهرداری تهران')
            ->set('newEmployerNationalId', '3344556677')
            ->set('newEmployerMobile', '09127778899')
            ->call('addEmployerToCatalog')
            ->assertHasNoErrors()
            ->assertSet('programEmployerId', (string) ProgramEmployer::query()->where('name', 'شهرداری تهران')->value('id'));

        $employer = ProgramEmployer::query()->where('name', 'شهرداری تهران')->first();
        $this->assertNotNull($employer);
        $this->assertNotEmpty($employer->employer_code);
        $this->assertNotNull($employer->user_id);
    }

    public function test_program_form_requires_employer_selection(): void
    {
        $this->actingAs($this->hostUser);

        Livewire::test(ProgramBookingForm::class, ['panel' => 'host', 'accommodationId' => $this->accommodation->id])
            ->set('title', 'اردوی تست')
            ->set('startDate', '1404/08/01')
            ->set('endDate', '1404/08/05')
            ->set('guestCount', 10)
            ->set('roomsAllocated', 1)
            ->call('nextStep')
            ->assertHasErrors(['programEmployerId']);
    }

    public function test_cannot_open_employer_modal_without_accommodation(): void
    {
        $this->actingAs($this->hostUser);

        Livewire::test(ProgramBookingForm::class, ['panel' => 'host'])
            ->set('accommodationId', 0)
            ->call('openEmployerModal')
            ->assertSet('showAddEmployer', false)
            ->assertHasErrors(['accommodationId']);
    }

    public function test_cannot_add_beneficiary_without_accommodation(): void
    {
        $this->actingAs($this->hostUser);

        Livewire::test(ProgramBookingForm::class, ['panel' => 'host'])
            ->set('accommodationId', 0)
            ->call('openBeneficiaryModal')
            ->assertSet('showAddBeneficiary', false)
            ->assertHasErrors(['accommodationId']);
    }

    public function test_employer_modal_opens_after_accommodation_selected(): void
    {
        $this->actingAs($this->hostUser);

        Livewire::test(ProgramBookingForm::class, ['panel' => 'host', 'accommodationId' => $this->accommodation->id])
            ->call('openEmployerModal')
            ->assertSet('showAddEmployer', true)
            ->assertHasNoErrors();
    }

    /** @return array<string, mixed> */
    private function baseProgramPayload(string $checkIn, string $checkOut, int $employerId): array
    {
        return [
            'title'               => 'اردوی تست',
            'program_type'        => Program::TYPE_CAMP,
            'program_employer_id' => $employerId,
            'guest_count'         => 10,
            'rooms_allocated'     => 1,
            'check_in'            => $checkIn,
            'check_out'           => $checkOut,
            'room_lines'          => [[
                'room_type_id' => $this->roomType->id,
                'room_rate_id' => $this->roomRate->id,
                'room_id'      => $this->room->id,
                'room_name'    => $this->room->name,
            ]],
            'payment_type'        => Program::PAYMENT_CASH,
            'base_price'          => 1_000_000,
        ];
    }
}
