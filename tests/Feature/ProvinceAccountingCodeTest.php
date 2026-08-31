<?php

namespace Tests\Feature;

use App\Livewire\Admin\HostCreate;
use App\Livewire\ProgramBookingForm;
use App\Models\ProgramEmployer;
use App\Models\Province;
use App\Models\User;
use App\Services\AccountingEntityLookup;
use App\Services\EmployerUserProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProvinceAccountingCodeTest extends TestCase
{
    use RefreshDatabase;

    private $accommodation;
    private User $hostUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->accommodation = $this->createTestAccommodation();

        $this->hostUser = User::create([
            'name'   => 'میزبان تست',
            'mobile' => '09120000001',
        ]);
        $this->hostUser->assignRole('host');
        $this->accommodation->hosts()->attach($this->hostUser->id);
    }

    public function test_program_form_auto_assigns_employer_code_by_province(): void
    {
        $this->actingAs($this->hostUser);

        Livewire::test(ProgramBookingForm::class, ['panel' => 'host', 'accommodationId' => $this->accommodation->id])
            ->call('openEmployerModal')
            ->set('newEmployerName', 'بنیاد شهید استان مازندران')
            ->set('newEmployerNationalId', '3344556677')
            ->set('newEmployerMobile', '09127778899')
            ->call('addEmployerToCatalog')
            ->assertHasNoErrors();

        $employer = ProgramEmployer::query()->where('name', 'بنیاد شهید استان مازندران')->first();
        $this->assertNotNull($employer);
        $this->assertSame('515402', $employer->employer_code);
        $this->assertNotNull($employer->province_id);
    }

    public function test_second_employer_in_same_province_gets_incremented_code(): void
    {
        $this->actingAs($this->hostUser);

        Livewire::test(ProgramBookingForm::class, ['panel' => 'host', 'accommodationId' => $this->accommodation->id])
            ->call('openEmployerModal')
            ->set('newEmployerName', 'شهرداری مازندران')
            ->set('newEmployerNationalId', '3344556678')
            ->set('newEmployerMobile', '09127778898')
            ->call('addEmployerToCatalog')
            ->assertHasNoErrors();

        $this->assertSame('515402', ProgramEmployer::query()->where('name', 'شهرداری مازندران')->value('employer_code'));
    }

    public function test_host_create_assigns_personnel_code(): void
    {
        $admin = User::create([
            'name'   => 'مدیر',
            'mobile' => '09129990000',
        ]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin);

        Livewire::test(HostCreate::class)
            ->set('name', 'علی اصغر مسلمی')
            ->set('mobile', '09128887766')
            ->set('selectedAccommodationIds', [$this->accommodation->id])
            ->set('hostPositionPreset', 'مدیر مجموعه')
            ->set('hostPassword', 'secret123')
            ->set('hostPassword_confirmation', 'secret123')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $host = User::query()->where('mobile', '09128887766')->first();
        $this->assertSame('515701', $host->personnel_code);
        $this->assertSame($this->accommodation->resolvedProvince()?->id, $host->province_id);
    }

    public function test_accounting_lookup_finds_employer_user_by_code(): void
    {
        $provinceId = $this->accommodation->fresh()->resolvedProvince()?->id;

        $employer = ProgramEmployer::create([
            'province_id'             => $provinceId,
            'name'                    => 'سازمان تست',
            'employer_code'           => '515408',
            'national_or_economic_id' => '1234567890',
            'mobile'                  => '09125556677',
        ]);

        app(EmployerUserProvisioner::class)->linkEmployer($employer);

        $user = app(AccountingEntityLookup::class)->findUserByIdentifier('515408');

        $this->assertNotNull($user);
        $this->assertSame($employer->fresh()->user_id, $user->id);
    }
}
