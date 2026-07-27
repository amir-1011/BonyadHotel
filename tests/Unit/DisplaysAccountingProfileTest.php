<?php

namespace Tests\Unit;

use App\Models\ProgramBeneficiary;
use App\Models\ProgramEmployer;
use App\Models\Province;
use App\Models\User;
use App\Support\ProvinceAccountingCodeCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DisplaysAccountingProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_accounting_profile_details(): void
    {
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);

        $province = Province::create(['name' => 'مازندران', 'accounting_code' => '515']);
        $user = User::create([
            'name'           => 'علی اصغر مسلمی',
            'mobile'         => '09121112233',
            'province_id'    => $province->id,
            'personnel_code' => '515701',
        ]);
        $user->assignRole('host');
        $user->load('province');

        $details = $user->accountingProfileDetails();

        $this->assertNotNull($details);
        $this->assertSame('515701', $details['code']);
        $this->assertSame('پرسنل', $details['entity_type_label']);
        $this->assertSame('515', $details['province_code']);
        $this->assertSame('مازندران', $details['province_name']);
        $this->assertSame(7, $details['indicator']);
        $this->assertSame(1, $details['counter']);
        $this->assertTrue($user->hasAccountingProfile());
    }

    public function test_beneficiary_accounting_profile_details(): void
    {
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $province = Province::create(['name' => 'مازندران', 'accounting_code' => '515']);
        $user = User::create(['name' => 'ذینفع تست', 'mobile' => '09123334455']);
        $user->assignRole('guest');

        ProgramBeneficiary::create([
            'province_id'             => $province->id,
            'user_id'                 => $user->id,
            'name'                    => 'رستوران حاج حسین',
            'beneficiary_code'        => '515101',
            'national_or_economic_id' => '1234567890',
            'mobile'                  => '09123334455',
        ]);

        $user->load('programBeneficiary.province');
        $details = $user->accountingProfileDetails();

        $this->assertSame('515101', $details['code']);
        $this->assertSame('ذینفع', $details['entity_type_label']);
        $this->assertSame(1, $details['indicator']);
    }

    public function test_employer_accounting_profile_details(): void
    {
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $province = Province::create(['name' => 'مازندران', 'accounting_code' => '515']);
        $user = User::create(['name' => 'ارگان تست', 'mobile' => '09124445566']);
        $user->assignRole('guest');

        ProgramEmployer::create([
            'province_id'             => $province->id,
            'user_id'                 => $user->id,
            'name'                    => 'بنیاد شهید',
            'employer_code'           => '515401',
            'national_or_economic_id' => '2234567890',
            'mobile'                  => '09124445566',
        ]);

        $user->load('programEmployer.province');
        $details = $user->accountingProfileDetails();

        $this->assertSame('515401', $details['code']);
        $this->assertSame('ارگان / اداره', $details['entity_type_label']);
        $this->assertSame(4, $details['indicator']);
    }

    public function test_guest_without_code_has_no_profile(): void
    {
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $user = User::create(['name' => 'مهمان', 'mobile' => '09125556677']);
        $user->assignRole('guest');

        $this->assertNull($user->accountingProfileDetails());
        $this->assertFalse($user->hasAccountingProfile());
    }

    public function test_host_without_personnel_code_has_no_profile(): void
    {
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);

        $user = User::create(['name' => 'میزبان قدیمی', 'mobile' => '09126667788']);
        $user->assignRole('host');

        $this->assertNull($user->accountingProfileDetails());
    }

    public function test_host_without_personnel_code_resolves_province_from_accommodation(): void
    {
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);

        $accommodation = $this->createTestAccommodation();
        $user = User::create(['name' => 'میزبان با اقامتگاه', 'mobile' => '09126667789']);
        $user->assignRole('host');
        $user->accommodations()->attach($accommodation->id);
        $user->load(['accommodations.city.province', 'accommodations.county.province']);

        $this->assertNull($user->accountingProfileDetails());

        $province = app(\App\Services\HostPersonnelCodeProvisioner::class)
            ->resolveProvinceFromAccommodations($user);

        $this->assertSame('515', $province?->accounting_code);
    }

    public function test_legacy_beneficiary_code_still_exposes_raw_code(): void
    {
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $province = Province::create(['name' => 'مازندران', 'accounting_code' => '515']);
        $user = User::create(['name' => 'ذینفع قدیمی', 'mobile' => '09127778899']);
        $user->assignRole('guest');

        ProgramBeneficiary::create([
            'province_id'             => $province->id,
            'user_id'                 => $user->id,
            'name'                    => 'قدیمی',
            'beneficiary_code'        => 'MB-001',
            'national_or_economic_id' => '1234567890',
            'mobile'                  => '09127778899',
        ]);

        $user->load('programBeneficiary.province');
        $details = $user->accountingProfileDetails();

        $this->assertSame('MB-001', $details['code']);
        $this->assertSame('ذینفع', $details['entity_type_label']);
        $this->assertNull($details['indicator']);
        $this->assertNull($details['counter']);
    }

    public function test_host_profile_takes_precedence_over_beneficiary_relation(): void
    {
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);

        $province = Province::create(['name' => 'مازندران', 'accounting_code' => '515']);
        $user = User::create([
            'name'           => 'دو نقشی',
            'mobile'         => '09129990001',
            'province_id'    => $province->id,
            'personnel_code' => '515701',
        ]);
        $user->assignRole('host');

        ProgramBeneficiary::create([
            'province_id'             => $province->id,
            'user_id'                 => $user->id,
            'name'                    => 'ذینفع همزمان',
            'beneficiary_code'        => '515101',
            'national_or_economic_id' => '1234567890',
            'mobile'                  => '09129990001',
        ]);

        $user->load(['province', 'programBeneficiary.province']);
        $details = $user->accountingProfileDetails();

        $this->assertSame('515701', $details['code']);
        $this->assertSame('پرسنل', $details['entity_type_label']);
    }

    public function test_catalog_normalizes_persian_characters(): void
    {
        $this->assertSame('515', ProvinceAccountingCodeCatalog::resolveForName('مازندران'));
        $this->assertSame('515', ProvinceAccountingCodeCatalog::resolveForName('مازندران'));
    }
}
