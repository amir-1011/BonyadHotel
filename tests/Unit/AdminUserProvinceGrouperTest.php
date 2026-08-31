<?php

namespace Tests\Unit;

use App\Models\ProgramBeneficiary;
use App\Models\ProgramEmployer;
use App\Models\Province;
use App\Models\User;
use App\Support\AdminUserProvinceGrouper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminUserProvinceGrouperTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);
    }

    public function test_groups_users_by_accounting_province_code(): void
    {
        $fars = Province::create(['name' => 'فارس', 'accounting_code' => '515']);
        $tehran = Province::create(['name' => 'تهران', 'accounting_code' => '116']);

        $host = User::create([
            'name'           => 'میزبان فارس',
            'mobile'         => '09120005001',
            'province_id'    => $fars->id,
            'personnel_code' => '515701',
        ]);
        $host->assignRole('host');

        $employerUser = User::create(['name' => 'اداره فارس', 'mobile' => '09120005002']);
        ProgramEmployer::create([
            'user_id'                 => $employerUser->id,
            'name'                    => 'اداره فارس',
            'employer_code'           => '515401',
            'province_id'             => $fars->id,
            'national_or_economic_id' => '1234567890',
            'mobile'                  => '09120005002',
        ]);

        $beneficiaryUser = User::create(['name' => 'ذینفع تهران', 'mobile' => '09120005003']);
        ProgramBeneficiary::create([
            'user_id'                 => $beneficiaryUser->id,
            'name'                    => 'ذینفع تهران',
            'beneficiary_code'        => '116601',
            'province_id'             => $tehran->id,
            'mobile'                  => '09120005003',
            'national_or_economic_id' => '9876543210',
        ]);

        $users = User::query()
            ->with(['province', 'programEmployer.province', 'programBeneficiary.province'])
            ->whereIn('id', [$host->id, $employerUser->id, $beneficiaryUser->id])
            ->get();

        $groups = AdminUserProvinceGrouper::group($users);

        $this->assertCount(2, $groups);

        $farsGroup = collect($groups)->firstWhere('province_code', '515');
        $tehranGroup = collect($groups)->firstWhere('province_code', '116');

        $this->assertNotNull($farsGroup);
        $this->assertSame('فارس', $farsGroup['province_name']);
        $this->assertCount(2, $farsGroup['users']);

        $this->assertNotNull($tehranGroup);
        $this->assertSame('تهران', $tehranGroup['province_name']);
        $this->assertCount(1, $tehranGroup['users']);
    }

    public function test_users_without_accounting_code_go_to_unknown_group(): void
    {
        $guest = User::create(['name' => 'مهمان', 'mobile' => '09120005004']);
        $guest->assignRole('guest');

        $groups = AdminUserProvinceGrouper::group(User::query()->whereKey($guest->id)->get());

        $this->assertCount(1, $groups);
        $this->assertSame(AdminUserProvinceGrouper::UNKNOWN_KEY, $groups[0]['key']);
        $this->assertSame('بدون استان', $groups[0]['province_name']);
    }
}
