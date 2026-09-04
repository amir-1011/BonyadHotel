<?php

namespace Tests\Feature;

use App\Livewire\Host\UserIndex;
use App\Models\Accommodation;
use App\Models\ProgramBeneficiary;
use App\Models\ProgramEmployer;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HostUserCatalogModalTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    private Accommodation $accommodation;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $provinceId = DB::table('provinces')->insertGetId([
            'name'            => 'استان تست',
            'accounting_code' => '501',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        $cityId = DB::table('cities')->insertGetId([
            'province_id' => $provinceId,
            'name'        => 'شهر تست',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->host = User::create(['name' => 'میزبان', 'mobile' => '09120000001']);
        $this->host->assignRole('host');

        $this->accommodation = Accommodation::create([
            'city_id'         => $cityId,
            'name'            => 'هتل میزبان',
            'price_per_night' => 1_000_000,
            'capacity'        => 10,
            'rooms'           => 5,
            'is_active'       => true,
        ]);

        $this->host->accommodations()->attach($this->accommodation->id);
    }

    public function test_host_users_page_opens_employer_modal_without_accommodation(): void
    {
        $this->actingAs($this->host);

        Livewire::test(UserIndex::class)
            ->call('openEmployerModal')
            ->assertSet('showAddEmployer', true);
    }

    public function test_host_users_page_opens_beneficiary_modal_without_accommodation(): void
    {
        $this->actingAs($this->host);

        Livewire::test(UserIndex::class)
            ->call('openBeneficiaryModal')
            ->assertSet('showAddBeneficiary', true);
    }

    public function test_host_users_page_can_create_employer_from_modal(): void
    {
        $province = Province::query()->orderBy('id')->first();
        $this->assertNotNull($province);

        $this->actingAs($this->host);

        Livewire::test(UserIndex::class)
            ->call('openEmployerModal')
            ->set('accountingProvinceId', $province->id)
            ->set('newEmployerName', 'اداره تست میزبان')
            ->set('newEmployerNationalId', '1234567893')
            ->set('newEmployerMobile', '09127778883')
            ->call('addEmployerToCatalog')
            ->assertHasNoErrors()
            ->assertSet('showAddEmployer', false);

        $employer = ProgramEmployer::query()->where('mobile', '09127778883')->first();

        $this->assertNotNull($employer);
        $this->assertSame($province->id, (int) $employer->province_id);
        $this->assertNotNull($employer->user_id);
    }

    public function test_host_users_page_can_create_beneficiary_from_modal(): void
    {
        $province = Province::query()->orderBy('id')->first();
        $this->assertNotNull($province);

        $this->actingAs($this->host);

        Livewire::test(UserIndex::class)
            ->call('openBeneficiaryModal')
            ->set('accountingProvinceId', $province->id)
            ->set('newBeneficiaryName', 'ذینفع تست میزبان')
            ->set('newBeneficiaryNationalId', '1234567894')
            ->set('newBeneficiaryMobile', '09127778884')
            ->call('addBeneficiaryToCatalog')
            ->assertHasNoErrors()
            ->assertSet('showAddBeneficiary', false);

        $beneficiary = ProgramBeneficiary::query()->where('mobile', '09127778884')->first();

        $this->assertNotNull($beneficiary);
        $this->assertSame($province->id, (int) $beneficiary->province_id);
        $this->assertNotNull($beneficiary->user_id);
    }
}
