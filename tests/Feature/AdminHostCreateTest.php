<?php

namespace Tests\Feature;

use App\Livewire\Admin\HostCreate;
use App\Models\Accommodation;
use App\Models\User;
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
            ->set('hostPanelPermissions', ['dashboard', 'accommodations', 'bookings'])
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
        $this->assertSame(['dashboard', 'accommodations', 'bookings'], $host->host_panel_permissions);
        $this->assertTrue($host->accommodations()->where('accommodations.id', $accommodation->id)->exists());
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
