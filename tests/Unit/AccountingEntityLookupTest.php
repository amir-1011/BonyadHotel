<?php

namespace Tests\Unit;

use App\Models\ProgramBeneficiary;
use App\Models\ProgramEmployer;
use App\Models\Province;
use App\Models\User;
use App\Services\AccountingEntityLookup;
use App\Services\BeneficiaryUserProvisioner;
use App\Services\EmployerUserProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountingEntityLookupTest extends TestCase
{
    use RefreshDatabase;

    private AccountingEntityLookup $lookup;

    private Province $province;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $this->province = Province::create(['name' => 'مازندران', 'accounting_code' => '515']);
        $this->lookup = app(AccountingEntityLookup::class);
    }

    public function test_finds_user_by_national_id(): void
    {
        $user = User::create([
            'name'        => 'مهمان',
            'mobile'      => '09121111111',
            'national_id' => '1234567890',
        ]);
        $user->assignRole('guest');

        $found = $this->lookup->findUserByIdentifier('1234567890');

        $this->assertNotNull($found);
        $this->assertSame($user->id, $found->id);
    }

    public function test_finds_host_by_personnel_code(): void
    {
        $host = User::create([
            'name'           => 'پرسنل',
            'mobile'         => '09122222222',
            'province_id'    => $this->province->id,
            'personnel_code' => '515701',
        ]);
        $host->assignRole('host');

        $found = $this->lookup->findUserByIdentifier('515701');

        $this->assertNotNull($found);
        $this->assertSame($host->id, $found->id);
    }

    public function test_finds_employer_linked_user_by_code(): void
    {
        $employer = ProgramEmployer::create([
            'province_id'             => $this->province->id,
            'name'                    => 'بنیاد شهید',
            'employer_code'           => '515401',
            'national_or_economic_id' => '2234567890',
            'mobile'                  => '09123333333',
        ]);

        $linked = app(EmployerUserProvisioner::class)->linkEmployer($employer);

        $found = $this->lookup->findUserByIdentifier('515401');

        $this->assertNotNull($found);
        $this->assertSame($linked->user_id, $found->id);
    }

    public function test_finds_beneficiary_linked_user_by_code(): void
    {
        $beneficiary = ProgramBeneficiary::create([
            'province_id'             => $this->province->id,
            'name'                    => 'رستوران',
            'beneficiary_code'        => '515101',
            'national_or_economic_id' => '3234567890',
            'mobile'                  => '09124444444',
        ]);

        $linked = app(BeneficiaryUserProvisioner::class)->linkBeneficiary($beneficiary);

        $found = $this->lookup->findUserByIdentifier('515101');

        $this->assertNotNull($found);
        $this->assertSame($linked->user_id, $found->id);
    }

    public function test_returns_null_for_unlinked_employer_code(): void
    {
        ProgramEmployer::create([
            'province_id'             => $this->province->id,
            'name'                    => 'بدون کاربر',
            'employer_code'           => '515402',
            'national_or_economic_id' => '4234567890',
            'mobile'                  => '09125555555',
        ]);

        $this->assertNull($this->lookup->findUserByIdentifier('515402'));
    }

    public function test_returns_null_for_unknown_six_digit_code(): void
    {
        $this->assertNull($this->lookup->findUserByIdentifier('515999'));
    }

    public function test_returns_null_for_empty_identifier(): void
    {
        $this->assertNull($this->lookup->findUserByIdentifier(''));
        $this->assertNull($this->lookup->findUserByIdentifier('   '));
    }

    public function test_returns_null_for_legacy_employer_code_format(): void
    {
        ProgramEmployer::create([
            'province_id'             => $this->province->id,
            'name'                    => 'قدیمی',
            'employer_code'           => 'EMP-001',
            'national_or_economic_id' => '5234567890',
            'mobile'                  => '09126666666',
        ]);

        $this->assertNull($this->lookup->findUserByIdentifier('EMP-001'));
    }

    public function test_entity_label_for_valid_code(): void
    {
        $label = $this->lookup->entityLabelForCode('515401');

        $this->assertSame('کد حسابداری 515401 (ارگان / اداره)', $label);
    }

    public function test_entity_label_returns_null_for_invalid_code(): void
    {
        $this->assertNull($this->lookup->entityLabelForCode('515201'));
        $this->assertNull($this->lookup->entityLabelForCode('EMP-001'));
    }

    public function test_personnel_code_takes_precedence_over_employer_with_same_digits_in_lookup(): void
    {
        $host = User::create([
            'name'           => 'پرسنل',
            'mobile'         => '09127777777',
            'province_id'    => $this->province->id,
            'personnel_code' => '515401',
        ]);
        $host->assignRole('host');

        ProgramEmployer::create([
            'province_id'             => $this->province->id,
            'user_id'                 => User::create(['name' => 'ارگان', 'mobile' => '09128888888'])->id,
            'name'                    => 'ارگان',
            'employer_code'           => '515401',
            'national_or_economic_id' => '6234567890',
            'mobile'                  => '09128888888',
        ]);

        $found = $this->lookup->findUserByIdentifier('515401');

        $this->assertSame($host->id, $found?->id);
    }
}
