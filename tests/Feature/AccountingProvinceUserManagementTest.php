<?php

namespace Tests\Feature;

use App\Livewire\Admin\HostCreate;
use App\Livewire\Admin\UserEdit;
use App\Livewire\ProgramBookingForm;
use App\Models\ProgramBeneficiary;
use App\Models\ProgramEmployer;
use App\Models\Province;
use App\Models\User;
use App\Services\AccountingProvinceReassignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountingProvinceUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private $accommodation;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->accommodation = $this->createTestAccommodation();

        $this->admin = User::create([
            'name'   => 'مدیر',
            'mobile' => '09129990000',
        ]);
        $this->admin->assignRole('super_admin');
    }

    public function test_host_create_defaults_province_from_accommodation(): void
    {
        $provinceId = $this->accommodation->fresh()->resolvedProvince()?->id;

        $this->actingAs($this->admin);

        Livewire::test(HostCreate::class)
            ->set('selectedAccommodationIds', [$this->accommodation->id])
            ->assertSet('accountingProvinceId', $provinceId);
    }

    public function test_host_create_uses_selected_province_for_personnel_code(): void
    {
        $otherProvinceId = $this->ensureOtherProvinceId();

        $this->actingAs($this->admin);

        Livewire::test(HostCreate::class)
            ->set('name', 'میزبان استان دیگر')
            ->set('mobile', '09128887701')
            ->set('selectedAccommodationIds', [$this->accommodation->id])
            ->set('accountingProvinceId', $otherProvinceId)
            ->set('hostPositionPreset', 'مدیر مجموعه')
            ->set('hostPassword', 'secret123')
            ->set('hostPassword_confirmation', 'secret123')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $host = User::query()->where('mobile', '09128887701')->first();

        $this->assertNotNull($host);
        $this->assertSame($otherProvinceId, (int) $host->province_id);
        $this->assertSame('116701', $host->personnel_code);
    }

    public function test_employer_modal_uses_selected_province(): void
    {
        $hostUser = User::create(['name' => 'میزبان', 'mobile' => '09120000001']);
        $hostUser->assignRole('host');
        $this->accommodation->grantHostAccess($hostUser);

        $otherProvinceId = $this->ensureOtherProvinceId();

        $this->actingAs($hostUser);

        Livewire::test(ProgramBookingForm::class, ['panel' => 'host', 'accommodationId' => $this->accommodation->id])
            ->call('openEmployerModal')
            ->set('accountingProvinceId', $otherProvinceId)
            ->set('newEmployerName', 'کارفرمای تهران')
            ->set('newEmployerNationalId', '3344556601')
            ->set('newEmployerMobile', '09127778801')
            ->call('addEmployerToCatalog')
            ->assertHasNoErrors();

        $employer = ProgramEmployer::query()->where('mobile', '09127778801')->first();

        $this->assertNotNull($employer);
        $this->assertSame($otherProvinceId, (int) $employer->province_id);
        $this->assertSame('116401', $employer->employer_code);
    }

    public function test_beneficiary_modal_uses_selected_province(): void
    {
        $hostUser = User::create(['name' => 'میزبان', 'mobile' => '09120000002']);
        $hostUser->assignRole('host');
        $this->accommodation->grantHostAccess($hostUser);

        $otherProvinceId = $this->ensureOtherProvinceId();

        $this->actingAs($hostUser);

        Livewire::test(ProgramBookingForm::class, ['panel' => 'host', 'accommodationId' => $this->accommodation->id])
            ->call('openBeneficiaryModal')
            ->set('accountingProvinceId', $otherProvinceId)
            ->set('newBeneficiaryName', 'ذینفع تهران')
            ->set('newBeneficiaryNationalId', '3344556602')
            ->set('newBeneficiaryMobile', '09127778802')
            ->call('addBeneficiaryToCatalog')
            ->assertHasNoErrors();

        $beneficiary = ProgramBeneficiary::query()->where('mobile', '09127778802')->first();

        $this->assertNotNull($beneficiary);
        $this->assertSame($otherProvinceId, (int) $beneficiary->province_id);
        $this->assertSame('116101', $beneficiary->beneficiary_code);
    }

    public function test_user_edit_reassigns_host_personnel_code_on_province_change(): void
    {
        $host = User::create([
            'name'           => 'میزبان',
            'mobile'         => '09120000003',
            'province_id'    => $this->accommodation->resolvedProvince()?->id,
            'personnel_code' => '515701',
        ]);
        $host->assignRole('host');
        $this->accommodation->grantHostAccess($host);

        $otherProvinceId = $this->ensureOtherProvinceId();

        $this->actingAs($this->admin);

        Livewire::test(UserEdit::class, ['user' => $host])
            ->set('provinceId', $otherProvinceId)
            ->call('update')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $host->refresh();

        $this->assertSame($otherProvinceId, (int) $host->province_id);
        $this->assertSame('116701', $host->personnel_code);
    }

    public function test_user_edit_reassigns_employer_code_on_province_change(): void
    {
        $provinceId = $this->accommodation->resolvedProvince()?->id;

        $employer = ProgramEmployer::create([
            'province_id'             => $provinceId,
            'name'                    => 'کارفرما',
            'employer_code'           => '515408',
            'national_or_economic_id' => '1234567891',
            'mobile'                  => '09120001112',
        ]);

        $user = User::create(['name' => 'کارفرما', 'mobile' => '09120001112']);
        $user->assignRole('guest');
        $employer->update(['user_id' => $user->id]);

        $otherProvinceId = $this->ensureOtherProvinceId();

        $this->actingAs($this->admin);

        Livewire::test(UserEdit::class, ['user' => $user->fresh(['programEmployer'])])
            ->set('provinceId', $otherProvinceId)
            ->call('update')
            ->assertHasNoErrors();

        $employer->refresh();

        $this->assertSame($otherProvinceId, (int) $employer->province_id);
        $this->assertSame('116401', $employer->employer_code);
    }

    public function test_user_edit_reassigns_beneficiary_code_on_province_change(): void
    {
        $provinceId = $this->accommodation->resolvedProvince()?->id;

        $beneficiary = ProgramBeneficiary::create([
            'province_id'             => $provinceId,
            'name'                    => 'ذینفع',
            'beneficiary_code'        => '515101',
            'national_or_economic_id' => '1234567892',
            'mobile'                  => '09120001113',
        ]);

        $user = User::create(['name' => 'ذینفع', 'mobile' => '09120001113']);
        $user->assignRole('guest');
        $beneficiary->update(['user_id' => $user->id]);

        $otherProvinceId = $this->ensureOtherProvinceId();

        $this->actingAs($this->admin);

        Livewire::test(UserEdit::class, ['user' => $user->fresh(['programBeneficiary'])])
            ->set('provinceId', $otherProvinceId)
            ->call('update')
            ->assertHasNoErrors();

        $beneficiary->refresh();

        $this->assertSame($otherProvinceId, (int) $beneficiary->province_id);
        $this->assertSame('116101', $beneficiary->beneficiary_code);
    }

    public function test_reassignment_service_detects_province_code_change(): void
    {
        $service = app(AccountingProvinceReassignmentService::class);
        $tehran = Province::query()->where('accounting_code', '116')->first()
            ?? Province::create(['name' => 'تهران تست', 'accounting_code' => '116']);

        $this->assertTrue($service->willProvinceChangeAffectCode('515701', $tehran));
        $this->assertFalse($service->willProvinceChangeAffectCode('515701', $this->accommodation->resolvedProvince()));
    }

    private function ensureOtherProvinceId(): int
    {
        $row = DB::table('provinces')->where('accounting_code', '116')->first();

        if ($row) {
            return (int) $row->id;
        }

        return (int) DB::table('provinces')->insertGetId([
            'name'            => 'تهران تست',
            'accounting_code' => '116',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }
}
