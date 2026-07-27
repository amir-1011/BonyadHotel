<?php

namespace Tests\Feature;

use App\Livewire\Admin\UserEdit;
use App\Livewire\Admin\UserShow;
use App\Livewire\Host\Profile as HostProfile;
use App\Models\Accommodation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HostPersonnelCodeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->adminUser = User::create(['name' => 'ادمین', 'mobile' => '09129991111']);
        $this->adminUser->assignRole('super_admin');
    }

    public function test_user_show_auto_provisions_legacy_host_without_code(): void
    {
        $accommodation = $this->createTestAccommodation();
        $host = User::create(['name' => 'میزبان قدیمی', 'mobile' => '09128881111']);
        $host->assignRole('host');
        $host->accommodations()->attach($accommodation->id, [
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $this->assertNull($host->personnel_code);

        $this->actingAs($this->adminUser);

        Livewire::test(UserShow::class, ['user' => $host->fresh()])
            ->assertSee('کد حسابداری پرسنلی')
            ->assertSee('515701')
            ->assertSee('کدینگ حسابداری');

        $host->refresh();
        $this->assertSame('515701', $host->personnel_code);
        $this->assertSame($accommodation->resolvedProvince()?->id, $host->province_id);
    }

    public function test_user_edit_mount_auto_provisions_legacy_host_without_code(): void
    {
        $accommodation = $this->createTestAccommodation();
        $host = User::create(['name' => 'میزبان ویرایش', 'mobile' => '09128882222']);
        $host->assignRole('host');
        $host->accommodations()->attach($accommodation->id);

        $this->actingAs($this->adminUser);

        Livewire::test(UserEdit::class, ['user' => $host->fresh()])
            ->assertSee('515701');

        $host->refresh();
        $this->assertSame('515701', $host->personnel_code);
    }

    public function test_host_profile_auto_provisions_legacy_host_without_code(): void
    {
        $accommodation = $this->createTestAccommodation();
        $host = User::create([
            'name'     => 'میزبان پروفایل',
            'mobile'   => '09128883333',
            'password' => 'secret123',
        ]);
        $host->assignRole('host');
        $host->accommodations()->attach($accommodation->id);

        $this->actingAs($host);

        Livewire::test(HostProfile::class)
            ->assertSee('کد حسابداری پرسنلی')
            ->assertSee('515701')
            ->assertSee('کدینگ حسابداری');

        $this->assertSame('515701', $host->fresh()->personnel_code);
    }

    public function test_grant_host_access_provisions_personnel_code_on_first_assignment(): void
    {
        $accommodation = $this->createTestAccommodation();
        $host = User::create(['name' => 'میزبان جدید', 'mobile' => '09128884444']);
        $host->assignRole('host');

        $accommodation->grantHostAccess($host);

        $host->refresh();
        $this->assertSame('515701', $host->personnel_code);
    }

    public function test_provision_host_codes_command_backfills_multiple_hosts(): void
    {
        $accommodation = $this->createTestAccommodation();

        $hostA = User::create(['name' => 'میزبان الف', 'mobile' => '09128885551']);
        $hostA->assignRole('host');
        $hostA->accommodations()->attach($accommodation->id);

        $hostB = User::create(['name' => 'میزبان ب', 'mobile' => '09128885552']);
        $hostB->assignRole('host');
        $hostB->accommodations()->attach($accommodation->id);

        $this->assertNull($hostA->fresh()->personnel_code);
        $this->assertNull($hostB->fresh()->personnel_code);

        Artisan::call('accounting:provision-host-codes');

        $this->assertSame('515701', $hostA->fresh()->personnel_code);
        $this->assertSame('515702', $hostB->fresh()->personnel_code);
    }

    public function test_provision_host_codes_command_dry_run_does_not_persist(): void
    {
        $accommodation = $this->createTestAccommodation();
        $host = User::create(['name' => 'میزبان خشک', 'mobile' => '09128886666']);
        $host->assignRole('host');
        $host->accommodations()->attach($accommodation->id);

        Artisan::call('accounting:provision-host-codes', ['--dry-run' => true]);

        $this->assertNull($host->fresh()->personnel_code);
    }

    public function test_host_without_accommodation_shows_placeholder_in_profile(): void
    {
        $host = User::create([
            'name'     => 'میزبان بدون اقامتگاه',
            'mobile'   => '09128887777',
            'password' => 'secret123',
        ]);
        $host->assignRole('host');

        $this->actingAs($host);

        Livewire::test(HostProfile::class)
            ->assertSee('کد حسابداری پرسنلی')
            ->assertSee('پس از تخصیص اقامتگاه')
            ->assertDontSee('کدینگ حسابداری');
    }

    public function test_second_host_on_same_accommodation_gets_incremented_personnel_code(): void
    {
        $accommodation = $this->createTestAccommodation();

        $hostA = User::create(['name' => 'میزبان اول', 'mobile' => '09128888881']);
        $hostA->assignRole('host');
        $accommodation->grantHostAccess($hostA);

        $hostB = User::create(['name' => 'میزبان دوم', 'mobile' => '09128888882']);
        $hostB->assignRole('host');
        $accommodation->grantHostAccess($hostB);

        $this->assertSame('515701', $hostA->fresh()->personnel_code);
        $this->assertSame('515702', $hostB->fresh()->personnel_code);
    }

    public function test_personnel_code_uses_earliest_accommodation_when_multiple_exist(): void
    {
        $mazandaranAcc = $this->createTestAccommodation(['name' => 'مازندران']);

        $tehranProvinceId = DB::table('provinces')->insertGetId([
            'name'            => 'تهران',
            'accounting_code' => '508',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        $tehranCityId = $this->ensureTestCityId($tehranProvinceId, 'تهران');
        $tehranAcc = Accommodation::create([
            'city_id'         => $tehranCityId,
            'name'            => 'تهران',
            'price_per_night' => 2_000_000,
            'capacity'        => 10,
            'rooms'           => 5,
            'is_active'       => true,
        ]);

        $host = User::create(['name' => 'میزبان چند استان', 'mobile' => '09128889999']);
        $host->assignRole('host');
        $tehranAcc->hosts()->attach($host->id, ['created_at' => now(), 'updated_at' => now()]);
        $mazandaranAcc->hosts()->attach($host->id, ['created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);

        Artisan::call('accounting:provision-host-codes');

        $host->refresh();
        $this->assertSame('515701', $host->personnel_code);
        $this->assertSame($mazandaranAcc->resolvedProvince()?->id, $host->province_id);
    }
}
