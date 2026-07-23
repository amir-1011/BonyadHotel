<?php

namespace Tests\Feature;

use App\Livewire\Admin\HostCreate;
use App\Models\Accommodation;
use App\Models\User;
use App\Support\HostPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminHostCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);
    }

    private function createAccommodation(string $name = 'اقامتگاه تست'): Accommodation
    {
        $provinceId = DB::table('provinces')->insertGetId(['name' => 'استان تست', 'created_at' => now(), 'updated_at' => now()]);
        $cityId = DB::table('cities')->insertGetId(['province_id' => $provinceId, 'name' => 'شهر تست', 'created_at' => now(), 'updated_at' => now()]);

        return Accommodation::create([
            'city_id'         => $cityId,
            'name'            => $name,
            'price_per_night' => 1_000_000,
            'capacity'        => 10,
            'rooms'           => 5,
            'is_active'       => true,
        ]);
    }

    public function test_admin_can_create_host_with_full_details(): void
    {
        $admin = User::create(['name' => 'ادمین', 'mobile' => '09100000001']);
        $admin->assignRole('super_admin');

        $accommodation = $this->createAccommodation();

        $this->actingAs($admin);

        Livewire::test(HostCreate::class)
            ->set('name', 'میزبان جدید')
            ->set('mobile', '09120000001')
            ->set('nationalId', '1110000001')
            ->set('hostPassword', 'secret12')
            ->set('hostPassword_confirmation', 'secret12')
            ->set('hostPermissionForm', HostPermissions::grantsToFormState([
                'dashboard'          => ['read'],
                'accommodations.list'=> ['read'],
                'bookings.list'      => ['read'],
            ]))
            ->set('selectedAccommodationIds', [$accommodation->id])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $host = User::where('mobile', '09120000001')->first();

        $this->assertNotNull($host);
        $this->assertTrue($host->isHost());
        $this->assertSame('میزبان جدید', $host->name);
        $this->assertTrue(Hash::check('secret12', $host->password));
        $this->assertNotNull($host->mobile_verified_at);
        $this->assertSame([
            'dashboard'           => ['read'],
            'accommodations.list' => ['read'],
            'bookings.list'       => ['read'],
        ], $host->host_panel_permissions);
        $this->assertTrue($host->accommodations()->where('accommodations.id', $accommodation->id)->exists());
    }

    public function test_admin_can_create_host_with_custom_position_title(): void
    {
        $admin = User::create(['name' => 'ادمین', 'mobile' => '09100000001']);
        $admin->assignRole('super_admin');

        $this->actingAs($admin);

        Livewire::test(HostCreate::class)
            ->set('name', 'کاربر تست')
            ->set('mobile', '09120000099')
            ->set('hostPassword', 'secret12')
            ->set('hostPassword_confirmation', 'secret12')
            ->set('hostPositionPreset', 'مدیر مالی')
            ->call('save')
            ->assertHasNoErrors();

        $host = User::where('mobile', '09120000099')->first();

        $this->assertSame('مدیر مالی', $host->host_position_title);
        $this->assertSame('مدیر مالی', $host->hostRoleLabel());
    }

    public function test_admin_can_create_host_with_manual_position_title(): void
    {
        $admin = User::create(['name' => 'ادمین', 'mobile' => '09100000001']);
        $admin->assignRole('super_admin');

        $this->actingAs($admin);

        Livewire::test(HostCreate::class)
            ->set('name', 'کاربر تست')
            ->set('mobile', '09120000098')
            ->set('hostPassword', 'secret12')
            ->set('hostPassword_confirmation', 'secret12')
            ->set('newHostPositionTitle', 'سرپرست شیفت')
            ->call('addHostPosition')
            ->call('save')
            ->assertHasNoErrors();

        $host = User::where('mobile', '09120000098')->first();

        $this->assertSame('سرپرست شیفت', $host->host_position_title);
        $this->assertSame('سرپرست شیفت', $host->hostRoleLabel());
        $this->assertDatabaseHas('host_position_titles', ['label' => 'سرپرست شیفت']);
    }

    public function test_added_host_position_persists_in_catalog_for_next_form(): void
    {
        $admin = User::create(['name' => 'ادمین', 'mobile' => '09100000001']);
        $admin->assignRole('super_admin');

        $this->actingAs($admin);

        Livewire::test(HostCreate::class)
            ->set('newHostPositionTitle', 'سمت دائمی')
            ->call('addHostPosition')
            ->assertSet('hostPositionPreset', 'سمت دائمی');

        $this->assertDatabaseHas('host_position_titles', ['label' => 'سمت دائمی']);

        Livewire::test(HostCreate::class)
            ->assertViewHas('hostPositionOptions', function (array $options): bool {
                return in_array('سمت دائمی', $options, true);
            });
    }

    public function test_duplicate_mobile_is_rejected(): void
    {
        $admin = User::create(['name' => 'ادمین', 'mobile' => '09100000001']);
        $admin->assignRole('super_admin');

        User::create(['name' => 'موجود', 'mobile' => '09120000002']);

        $this->actingAs($admin);

        Livewire::test(HostCreate::class)
            ->set('name', 'میزبان')
            ->set('mobile', '09120000002')
            ->set('hostPassword', 'secret12')
            ->set('hostPassword_confirmation', 'secret12')
            ->call('save')
            ->assertHasErrors(['mobile']);
    }
}
