<?php

namespace Tests\Feature;

use App\Livewire\Admin\UserIndex;
use App\Models\ProgramBeneficiary;
use App\Models\ProgramEmployer;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminUserCatalogModalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $this->admin = User::create([
            'name'   => 'مدیر',
            'mobile' => '09129990000',
        ]);
        $this->admin->assignRole('super_admin');
    }

    public function test_admin_users_page_opens_employer_modal_without_accommodation(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(UserIndex::class)
            ->call('openEmployerModal')
            ->assertSet('showAddEmployer', true);
    }

    public function test_admin_users_page_opens_beneficiary_modal_without_accommodation(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(UserIndex::class)
            ->call('openBeneficiaryModal')
            ->assertSet('showAddBeneficiary', true);
    }

    public function test_admin_users_page_can_create_employer_from_modal(): void
    {
        $province = Province::query()->orderBy('id')->first();
        $this->assertNotNull($province);

        $this->actingAs($this->admin);

        Livewire::test(UserIndex::class)
            ->call('openEmployerModal')
            ->set('accountingProvinceId', $province->id)
            ->set('newEmployerName', 'اداره تست')
            ->set('newEmployerNationalId', '1234567891')
            ->set('newEmployerMobile', '09127778881')
            ->call('addEmployerToCatalog')
            ->assertHasNoErrors()
            ->assertSet('showAddEmployer', false)
            ->assertSet('section', 'employers');

        $employer = ProgramEmployer::query()->where('mobile', '09127778881')->first();

        $this->assertNotNull($employer);
        $this->assertSame($province->id, (int) $employer->province_id);
        $this->assertNotNull($employer->user_id);
    }

    public function test_admin_users_page_can_create_beneficiary_from_modal(): void
    {
        $province = Province::query()->orderBy('id')->first();
        $this->assertNotNull($province);

        $this->actingAs($this->admin);

        Livewire::test(UserIndex::class)
            ->call('openBeneficiaryModal')
            ->set('accountingProvinceId', $province->id)
            ->set('newBeneficiaryName', 'ذینفع تست')
            ->set('newBeneficiaryNationalId', '1234567892')
            ->set('newBeneficiaryMobile', '09127778882')
            ->call('addBeneficiaryToCatalog')
            ->assertHasNoErrors()
            ->assertSet('showAddBeneficiary', false)
            ->assertSet('section', 'beneficiaries');

        $beneficiary = ProgramBeneficiary::query()->where('mobile', '09127778882')->first();

        $this->assertNotNull($beneficiary);
        $this->assertSame($province->id, (int) $beneficiary->province_id);
        $this->assertNotNull($beneficiary->user_id);
    }
}
