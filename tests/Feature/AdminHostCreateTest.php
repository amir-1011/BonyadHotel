<?php

namespace Tests\Feature;

use App\Livewire\Admin\HostCreate;
use App\Models\Accommodation;
use App\Models\HostPositionTitle;
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
        $provinceId = $this->ensureTestProvinceId();
        $cityId = $this->ensureTestCityId($provinceId);

        return Accommodation::create([
            'city_id'         => $cityId,
            'name'            => $name,
            'price_per_night' => 1_000_000,
            'capacity'        => 10,
            'rooms'           => 5,
            'is_active'       => true,
        ]);
    }

    private function admin(): User
    {
        $admin = User::create(['name' => 'ادمین', 'mobile' => '09100000001']);
        $admin->assignRole('super_admin');

        return $admin;
    }

    public function test_admin_can_create_host_with_position_template_permissions(): void
    {
        $grants = [
            'dashboard'           => ['read'],
            'accommodations.list' => ['read'],
            'bookings.list'       => ['read'],
        ];

        HostPositionTitle::query()->updateOrCreate(
            ['label' => 'مدیر مالی'],
            [
                'is_system'              => true,
                'sort_order'             => 5,
                'host_panel_permissions' => $grants,
            ],
        );

        $accommodation = $this->createAccommodation();

        $this->actingAs($this->admin());

        Livewire::test(HostCreate::class)
            ->set('name', 'میزبان جدید')
            ->set('mobile', '09120000001')
            ->set('nationalId', '1110000001')
            ->set('hostPassword', 'secret12')
            ->set('hostPassword_confirmation', 'secret12')
            ->set('hostPositionPreset', 'مدیر مالی')
            ->set('selectedAccommodationIds', [$accommodation->id])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $host = User::where('mobile', '09120000001')->first();

        $this->assertNotNull($host);
        $this->assertTrue($host->isHost());
        $this->assertSame('میزبان جدید', $host->name);
        $this->assertTrue(Hash::check('secret12', $host->password));
        $this->assertSame('مدیر مالی', $host->host_position_title);
        $this->assertSame(HostPermissions::normalizeStored($grants), $host->host_panel_permissions);
        $this->assertTrue($host->accommodations()->where('accommodations.id', $accommodation->id)->exists());
    }

    public function test_admin_can_create_host_without_position_gets_default_permissions(): void
    {
        $accommodation = $this->createAccommodation();

        $this->actingAs($this->admin());

        Livewire::test(HostCreate::class)
            ->set('name', 'میزبان بدون سمت')
            ->set('mobile', '09120000002')
            ->set('hostPassword', 'secret12')
            ->set('hostPassword_confirmation', 'secret12')
            ->set('selectedAccommodationIds', [$accommodation->id])
            ->call('save')
            ->assertHasNoErrors();

        $host = User::where('mobile', '09120000002')->first();

        $this->assertSame('میزبان', $host->host_position_title);
        $this->assertSame(HostPermissions::defaults(), $host->host_panel_permissions);
        $this->assertSame('515701', $host->personnel_code);
    }

    public function test_admin_can_create_host_with_default_position_template(): void
    {
        $grants = [
            'dashboard.overview' => ['read'],
            'bookings.list'    => ['read'],
        ];

        HostPositionTitle::query()->updateOrCreate(
            ['label' => 'میزبان'],
            [
                'is_system'              => true,
                'sort_order'             => 0,
                'host_panel_permissions' => $grants,
            ],
        );

        $accommodation = $this->createAccommodation();

        $this->actingAs($this->admin());

        Livewire::test(HostCreate::class)
            ->set('name', 'میزبان با سمت پیش‌فرض')
            ->set('mobile', '09120000003')
            ->set('hostPassword', 'secret12')
            ->set('hostPassword_confirmation', 'secret12')
            ->set('hostPositionPreset', 'میزبان')
            ->set('selectedAccommodationIds', [$accommodation->id])
            ->call('save')
            ->assertHasNoErrors();

        $host = User::where('mobile', '09120000003')->first();

        $this->assertSame('میزبان', $host->host_position_title);
        $this->assertSame(HostPermissions::normalizeStored($grants), $host->host_panel_permissions);
    }

    public function test_admin_can_create_host_with_existing_position_label(): void
    {
        $accommodation = $this->createAccommodation();

        $this->actingAs($this->admin());

        Livewire::test(HostCreate::class)
            ->set('name', 'کاربر تست')
            ->set('mobile', '09120000099')
            ->set('hostPassword', 'secret12')
            ->set('hostPassword_confirmation', 'secret12')
            ->set('hostPositionPreset', 'مدیر مالی')
            ->set('selectedAccommodationIds', [$accommodation->id])
            ->call('save')
            ->assertHasNoErrors();

        $host = User::where('mobile', '09120000099')->first();

        $this->assertSame('مدیر مالی', $host->host_position_title);
        $this->assertSame('مدیر مالی', $host->hostRoleLabel());
    }

    public function test_duplicate_mobile_is_rejected(): void
    {
        User::create(['name' => 'موجود', 'mobile' => '09120000002']);

        $this->actingAs($this->admin());

        Livewire::test(HostCreate::class)
            ->set('name', 'میزبان')
            ->set('mobile', '09120000002')
            ->set('hostPassword', 'secret12')
            ->set('hostPassword_confirmation', 'secret12')
            ->call('save')
            ->assertHasErrors(['mobile']);
    }
}
