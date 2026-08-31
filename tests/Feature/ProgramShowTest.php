<?php

namespace Tests\Feature;

use App\Livewire\Admin\ProgramShow;
use App\Livewire\Host\ProgramShow as HostProgramShow;
use App\Models\BookingGuestDetail;
use App\Models\Program;
use App\Models\ProgramBeneficiary;
use App\Models\ProgramBeneficiaryCost;
use App\Models\ProgramEmployer;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use App\Services\ProgramBookingService;
use App\Support\HostPermissions;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProgramShowTest extends TestCase
{
    use RefreshDatabase;

    private $accommodation;
    private RoomType $roomType;
    private RoomRate $roomRate;
    private Room $room;
    private User $hostUser;
    private ProgramEmployer $employer;
    private ProgramBeneficiary $beneficiary;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
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

        $provinceId = $this->ensureTestProvinceId();

        $this->employer = ProgramEmployer::create([
            'province_id'             => $provinceId,
            'name'                    => 'کارفرمای تست',
            'employer_code'           => '515101',
            'national_or_economic_id' => '5566778899',
            'mobile'                  => '09125556677',
        ]);

        $this->beneficiary = ProgramBeneficiary::create([
            'province_id'             => $provinceId,
            'name'                    => 'ذینفع تست',
            'beneficiary_code'        => '515201',
            'national_or_economic_id' => '1122334455',
            'mobile'                  => '09126667788',
        ]);
    }

    private function createProgramWithGuests(): Program
    {
        $checkIn = Carbon::today()->addDays(5)->toDateString();
        $checkOut = Carbon::today()->addDays(8)->toDateString();

        return app(ProgramBookingService::class)->create(
            $this->accommodation->fresh(),
            [
                'title'               => 'اردوی نمایش',
                'description'         => 'توضیحات کامل برنامه',
                'program_type'        => Program::TYPE_CAMP,
                'program_employer_id' => $this->employer->id,
                'contractor'          => 'پیمانکار نمایش',
                'guest_count'         => 3,
                'rooms_allocated'     => 1,
                'check_in'            => $checkIn,
                'check_out'           => $checkOut,
                'room_lines'          => [[
                    'room_type_id' => $this->roomType->id,
                    'room_rate_id' => $this->roomRate->id,
                    'room_id'      => $this->room->id,
                    'room_name'    => $this->room->name,
                ]],
                'guest_details' => [
                    [
                        'full_name'       => 'مهمان اصلی',
                        'national_id'     => '1234567890',
                        'mobile'          => '09121111111',
                        'relation'        => BookingGuestDetail::RELATION_MAIN_GUEST,
                        'room_line_index' => 0,
                        'sort_order'      => 0,
                    ],
                    [
                        'full_name'       => 'مهمان دوم',
                        'national_id'     => '0987654321',
                        'mobile'          => '09122222222',
                        'relation'        => 'همسر',
                        'room_line_index' => 0,
                        'sort_order'      => 1,
                    ],
                ],
                'beneficiary_costs' => [[
                    'program_beneficiary_id' => $this->beneficiary->id,
                    'debt_amount'            => 1_500_000,
                    'description'            => 'بدهی تست',
                ]],
                'payment_type'    => Program::PAYMENT_CASH,
                'base_price'      => 2_000_000,
                'deposit_amount'  => 500_000,
            ],
            $this->hostUser,
        );
    }

    public function test_admin_program_show_displays_enriched_details(): void
    {
        $program = $this->createProgramWithGuests();
        $admin = User::create(['name' => 'ادمین', 'mobile' => '09123334444']);
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)
            ->test(ProgramShow::class, ['program' => $program])
            ->assertSee('اردوی نمایش')
            ->assertSee('کارفرمای تست')
            ->assertSee('5566778899')
            ->assertSee('09125556677')
            ->assertSee('ذینفع تست')
            ->assertSee('1122334455')
            ->assertSee('1,500,000')
            ->assertSee('جمع بدهی')
            ->assertSee('مهمان اصلی')
            ->assertSee('مهمان دوم')
            ->assertSee('پیمانکار نمایش')
            ->assertSee('توضیحات کامل برنامه')
            ->assertSee('3 شب')
            ->assertSee('ثبت‌کننده')
            ->assertSee('میزبان تست');
    }

    public function test_admin_can_edit_program_guests_from_show_page(): void
    {
        $program = $this->createProgramWithGuests();
        $admin = User::create(['name' => 'ادمین', 'mobile' => '09123334444']);
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)
            ->test(ProgramShow::class, ['program' => $program])
            ->call('toggleGuestEditMode')
            ->set('guestRows.1.full_name', 'علی رضایی')
            ->set('guestRows.1.mobile', '09129998877')
            ->call('saveProgramGuests')
            ->assertDispatched('toast');

        $guest = $program->booking->guestDetails()->where('sort_order', 1)->first();
        $this->assertNotNull($guest);
        $this->assertSame('علی رضایی', $guest->full_name);
        $this->assertSame('09129998877', $guest->mobile);
    }

    public function test_host_can_edit_program_guests_with_permission(): void
    {
        $program = $this->createProgramWithGuests();

        $this->hostUser->update([
            'host_panel_permissions' => [
                'programs.show'   => ['read'],
                'programs.guests' => ['edit'],
            ],
        ]);

        Livewire::actingAs($this->hostUser)
            ->test(HostProgramShow::class, ['program' => $program])
            ->call('toggleGuestEditMode')
            ->set('guestRows.1.full_name', 'زهرا احمدی')
            ->call('saveProgramGuests')
            ->assertDispatched('toast');

        $guest = $program->booking->guestDetails()->where('sort_order', 1)->first();
        $this->assertSame('زهرا احمدی', $guest->full_name);
    }

    public function test_host_cannot_edit_program_guests_without_permission(): void
    {
        $program = $this->createProgramWithGuests();

        $this->hostUser->update([
            'host_panel_permissions' => [
                'programs.show' => ['read'],
            ],
        ]);

        Livewire::actingAs($this->hostUser)
            ->test(HostProgramShow::class, ['program' => $program])
            ->call('toggleGuestEditMode')
            ->assertForbidden();
    }

    public function test_host_can_edit_program_guests_after_check_out(): void
    {
        $program = $this->createProgramWithGuests();

        $this->hostUser->update([
            'host_panel_permissions' => [
                'programs.show'   => ['read'],
                'programs.guests' => ['edit'],
            ],
        ]);

        Carbon::setTestNow(Carbon::parse($program->booking->check_out)->addDays(3));

        Livewire::actingAs($this->hostUser)
            ->test(HostProgramShow::class, ['program' => $program->fresh()])
            ->call('toggleGuestEditMode')
            ->set('guestRows.1.full_name', 'کامران نوری')
            ->call('saveProgramGuests')
            ->assertDispatched('toast');

        $guest = $program->booking->guestDetails()->where('sort_order', 1)->first();
        $this->assertSame('کامران نوری', $guest->full_name);

        Carbon::setTestNow();
    }

    public function test_host_with_bookings_module_gets_guest_edit_via_migration(): void
    {
        $grants = HostPermissions::backfillGuestEditGrants([
            'bookings.show' => ['read'],
        ]);

        $this->assertTrue(HostPermissions::grantsAllow('bookings.guests', 'read', $grants));
        $this->assertTrue(HostPermissions::grantsAllow('bookings.guests', 'edit', $grants));
    }

    public function test_host_with_programs_module_gets_program_guest_edit_via_migration(): void
    {
        $grants = HostPermissions::backfillGuestEditGrants([
            'programs.show' => ['read'],
        ]);

        $this->assertTrue(HostPermissions::grantsAllow('programs.guests', 'read', $grants));
        $this->assertTrue(HostPermissions::grantsAllow('programs.guests', 'edit', $grants));
        $this->assertTrue(HostPermissions::grantsAllow('programs.pricing', 'read', $grants));
        $this->assertTrue(HostPermissions::grantsAllow('programs.pricing', 'edit', $grants));
    }

    public function test_admin_can_edit_program_financial(): void
    {
        $program = $this->createProgramWithGuests();
        $admin = User::create(['name' => 'ادمین', 'mobile' => '09123334444']);
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)
            ->test(ProgramShow::class, ['program' => $program])
            ->call('toggleFinancialEditMode')
            ->set('editBasePrice', 3_000_000)
            ->set('editDiscountAmount', 200_000)
            ->set('editDepositAmount', 1_000_000)
            ->call('saveProgramFinancial')
            ->assertDispatched('toast');

        $program->refresh();
        $booking = $program->booking->fresh();

        $this->assertSame(3_000_000, (int) $program->base_price);
        $this->assertSame(200_000, (int) $program->discount_amount);
        $this->assertSame(1_000_000, (int) $program->deposit_amount);
        $this->assertSame(2_800_000, (int) $program->total_amount);
        $this->assertSame(3_000_000 + (int) $program->services_subtotal, (int) $booking->base_price);
        $this->assertSame(2_800_000, (int) $booking->total_price);
    }

    public function test_host_can_edit_program_financial_with_permission(): void
    {
        $program = $this->createProgramWithGuests();

        $this->hostUser->update([
            'host_panel_permissions' => [
                'programs.show'    => ['read'],
                'programs.pricing' => ['edit'],
            ],
        ]);

        Livewire::actingAs($this->hostUser)
            ->test(HostProgramShow::class, ['program' => $program])
            ->call('toggleFinancialEditMode')
            ->set('editBasePrice', 2_500_000)
            ->set('editDiscountAmount', 100_000)
            ->set('editDepositAmount', 800_000)
            ->call('saveProgramFinancial')
            ->assertDispatched('toast');

        $program->refresh();
        $this->assertSame(2_500_000, (int) $program->base_price);
        $this->assertSame(100_000, (int) $program->discount_amount);
        $this->assertSame(800_000, (int) $program->deposit_amount);
        $this->assertSame(2_400_000, (int) $program->total_amount);
    }

    public function test_host_cannot_edit_program_financial_without_permission(): void
    {
        $program = $this->createProgramWithGuests();

        $this->hostUser->update([
            'host_panel_permissions' => [
                'programs.show' => ['read'],
            ],
        ]);

        Livewire::actingAs($this->hostUser)
            ->test(HostProgramShow::class, ['program' => $program])
            ->call('toggleFinancialEditMode')
            ->assertForbidden();
    }

    public function test_program_financial_validation_rejects_deposit_above_total(): void
    {
        $program = $this->createProgramWithGuests();
        $admin = User::create(['name' => 'ادمین', 'mobile' => '09123334444']);
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)
            ->test(ProgramShow::class, ['program' => $program])
            ->call('toggleFinancialEditMode')
            ->set('editBasePrice', 2_000_000)
            ->set('editDiscountAmount', 0)
            ->set('editDepositAmount', 3_000_000)
            ->call('saveProgramFinancial')
            ->assertHasErrors(['editDepositAmount']);
    }

    public function test_program_financial_validation_rejects_discount_above_base_plus_services(): void
    {
        $program = $this->createProgramWithGuests();
        $admin = User::create(['name' => 'ادمین', 'mobile' => '09123334444']);
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)
            ->test(ProgramShow::class, ['program' => $program])
            ->call('toggleFinancialEditMode')
            ->set('editBasePrice', 1_000_000)
            ->set('editDiscountAmount', 5_000_000)
            ->call('saveProgramFinancial')
            ->assertHasErrors(['editDiscountAmount']);
    }

    public function test_cancelled_program_cannot_edit_financial(): void
    {
        $program = $this->createProgramWithGuests();
        $program->update(['status' => Program::STATUS_CANCELLED]);

        $admin = User::create(['name' => 'ادمین', 'mobile' => '09123334444']);
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)
            ->test(ProgramShow::class, ['program' => $program->fresh()])
            ->call('toggleFinancialEditMode')
            ->assertForbidden();
    }

    public function test_program_show_rejects_duplicate_national_ids_on_save(): void
    {
        $program = $this->createProgramWithGuests();
        $admin = User::create(['name' => 'ادمین', 'mobile' => '09123334444']);
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)
            ->test(ProgramShow::class, ['program' => $program])
            ->call('toggleGuestEditMode')
            ->set('guestRows.1.national_id', '1234567890')
            ->call('saveProgramGuests')
            ->assertHasErrors(['guestRows']);
    }

    public function test_program_show_displays_beneficiary_document_badge(): void
    {
        $program = $this->createProgramWithGuests();

        ProgramBeneficiaryCost::query()
            ->where('program_id', $program->id)
            ->update(['documents' => ['program-documents/beneficiary/test-doc.pdf']]);

        $program->refresh()->load('beneficiaryCosts.beneficiary');

        $admin = User::create(['name' => 'ادمین', 'mobile' => '09123334444']);
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)
            ->test(ProgramShow::class, ['program' => $program])
            ->assertSee('1 مدرک')
            ->assertSee('مدارک ضمیمه')
            ->assertSee('اطلاعات تماس و هویت')
            ->assertSee('کدینگ حسابداری');
    }

    public function test_host_program_show_displays_employer_modal_sections(): void
    {
        $program = $this->createProgramWithGuests();
        $this->hostUser->update([
            'host_panel_permissions' => ['programs.show' => ['read']],
        ]);

        Livewire::actingAs($this->hostUser)
            ->test(HostProgramShow::class, ['program' => $program])
            ->assertSee('pg-modal-employer-' . $program->id)
            ->assertSee('کارفرمای تست')
            ->assertSee('اطلاعات تماس و هویت')
            ->assertSee('کدینگ حسابداری')
            ->assertSee('515101');
    }

    public function test_physical_rooms_table_does_not_show_rate_column(): void
    {
        $program = $this->createProgramWithGuests();
        $admin = User::create(['name' => 'ادمین', 'mobile' => '09123334444']);
        $admin->assignRole('super_admin');

        $component = Livewire::actingAs($admin)
            ->test(ProgramShow::class, ['program' => $program]);

        $html = $component->html();
        $this->assertStringContainsString('اتاق‌های فیزیکی', $html);
        $this->assertStringContainsString('نوع اتاق', $html);
        $this->assertStringNotContainsString('<th>نرخ</th>', $html);
    }

    public function test_employer_and_beneficiary_expose_accounting_profile(): void
    {
        $employerDetails = $this->employer->fresh('province')->accountingProfileDetails();
        $beneficiaryDetails = $this->beneficiary->fresh('province')->accountingProfileDetails();

        $this->assertNotNull($employerDetails);
        $this->assertSame('515101', $employerDetails['code']);
        $this->assertSame('ارگان / اداره', $employerDetails['entity_type_label']);

        $this->assertNotNull($beneficiaryDetails);
        $this->assertSame('515201', $beneficiaryDetails['code']);
        $this->assertSame('ذینفع', $beneficiaryDetails['entity_type_label']);
    }
}
