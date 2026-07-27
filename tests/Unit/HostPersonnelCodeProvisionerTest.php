<?php

namespace Tests\Unit;

use App\Models\Accommodation;
use App\Models\Province;
use App\Models\User;
use App\Services\HostPersonnelCodeProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HostPersonnelCodeProvisionerTest extends TestCase
{
    use RefreshDatabase;

    public function test_provisions_code_from_first_accommodation_province(): void
    {
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);

        $accommodation = $this->createTestAccommodation();
        $host = User::create(['name' => 'میزبان', 'mobile' => '09121110001']);
        $host->assignRole('host');
        $host->accommodations()->attach($accommodation->id);

        $provisioned = app(HostPersonnelCodeProvisioner::class)->provisionIfNeeded($host->fresh());

        $this->assertSame('515701', $provisioned->personnel_code);
        $this->assertNotNull($provisioned->province_id);
    }

    public function test_does_not_reprovision_existing_code(): void
    {
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);

        $province = Province::create(['name' => 'مازندران', 'accounting_code' => '515']);
        $host = User::create([
            'name'           => 'میزبان',
            'mobile'         => '09121110002',
            'province_id'    => $province->id,
            'personnel_code' => '515799',
        ]);
        $host->assignRole('host');

        $provisioned = app(HostPersonnelCodeProvisioner::class)->provisionIfNeeded($host);

        $this->assertSame('515799', $provisioned->personnel_code);
    }

    public function test_resolve_province_uses_earliest_assigned_accommodation(): void
    {
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);

        $mazandaranAcc = $this->createTestAccommodation(['name' => 'مازندران']);

        $tehranProvinceId = DB::table('provinces')->insertGetId([
            'name'            => 'تهران',
            'accounting_code' => '508',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        $tehranCityId = DB::table('cities')->insertGetId([
            'province_id' => $tehranProvinceId,
            'name'        => 'تهران',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        $tehranAcc = Accommodation::create([
            'city_id'         => $tehranCityId,
            'name'            => 'تهران',
            'price_per_night' => 1_000_000,
            'capacity'        => 5,
            'rooms'           => 3,
            'is_active'       => true,
        ]);

        $host = User::create(['name' => 'میزبان', 'mobile' => '09121110003']);
        $host->assignRole('host');

        $mazandaranAcc->hosts()->attach($host->id, ['created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);
        $tehranAcc->hosts()->attach($host->id, ['created_at' => now(), 'updated_at' => now()]);

        $province = app(HostPersonnelCodeProvisioner::class)->resolveProvinceFromAccommodations($host->fresh());

        $this->assertSame('515', $province?->accounting_code);
    }
}
