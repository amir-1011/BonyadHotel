<?php

namespace Tests\Feature;

use App\Livewire\Admin\UserEdit;
use App\Models\Accommodation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminUserEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);
    }

    private function createAccommodation(?int $hostId = null, string $name = 'اقامتگاه تست'): Accommodation
    {
        $provinceId = $this->ensureTestProvinceId();
        $cityId = $this->ensureTestCityId($provinceId);

        $accommodation = Accommodation::create([
            'city_id'         => $cityId,
            'host_id'         => $hostId,
            'name'            => $name,
            'price_per_night' => 1_000_000,
            'capacity'        => 10,
            'rooms'           => 5,
            'is_active'       => true,
        ]);

        if ($hostId) {
            $host = User::find($hostId);
            $accommodation->grantHostAccess($host);
        }

        return $accommodation;
    }

    public function test_duplicate_national_id_shows_validation_error(): void
    {
        $admin = User::create([
            'name'   => 'ادمین',
            'mobile' => '09100000001',
        ]);
        $admin->assignRole('super_admin');

        User::create([
            'name'        => 'کاربر اول',
            'mobile'      => '0923983650',
            'national_id' => '0923983650',
        ])->assignRole('guest');

        $target = User::create([
            'name'   => 'عطا',
            'mobile' => '09032512253',
        ])->assignRole('guest');

        $this->actingAs($admin);

        Livewire::test(UserEdit::class, ['user' => $target])
            ->set('nationalId', '0923983650')
            ->call('update')
            ->assertHasErrors(['nationalId'])
            ->assertSee('این کد ملی قبلاً برای «کاربر اول»');
    }

    public function test_admin_can_assign_accommodation_to_host(): void
    {
        $admin = User::create(['name' => 'ادمین', 'mobile' => '09100000001']);
        $admin->assignRole('super_admin');

        $host = User::create(['name' => 'میزبان', 'mobile' => '09100000002']);
        $host->assignRole('host');

        $accommodation = $this->createAccommodation();

        $this->actingAs($admin);

        Livewire::test(UserEdit::class, ['user' => $host])
            ->set('role', 'host')
            ->set('accommodationToAssign', $accommodation->id)
            ->call('assignAccommodation')
            ->assertHasNoErrors();

        $this->assertTrue($host->accommodations()->where('accommodations.id', $accommodation->id)->exists());
    }

    public function test_admin_can_revoke_host_access_from_accommodation(): void
    {
        $admin = User::create(['name' => 'ادمین', 'mobile' => '09100000001']);
        $admin->assignRole('super_admin');

        $host = User::create(['name' => 'میزبان', 'mobile' => '09100000002']);
        $host->assignRole('host');

        $accommodation = $this->createAccommodation($host->id);

        $this->actingAs($admin);

        Livewire::test(UserEdit::class, ['user' => $host])
            ->set('role', 'host')
            ->call('revokeAccommodation', $accommodation->id)
            ->assertHasNoErrors();

        $this->assertFalse($host->accommodations()->where('accommodations.id', $accommodation->id)->exists());
    }

    public function test_multiple_hosts_can_access_same_accommodation(): void
    {
        $admin = User::create(['name' => 'ادمین', 'mobile' => '09100000001']);
        $admin->assignRole('super_admin');

        $hostA = User::create(['name' => 'میزبان اول', 'mobile' => '09100000002']);
        $hostA->assignRole('host');

        $hostB = User::create(['name' => 'میزبان دوم', 'mobile' => '09100000004']);
        $hostB->assignRole('host');

        $accommodation = $this->createAccommodation($hostA->id);

        $this->actingAs($admin);

        Livewire::test(UserEdit::class, ['user' => $hostB])
            ->set('role', 'host')
            ->set('accommodationToAssign', $accommodation->id)
            ->call('assignAccommodation')
            ->assertHasNoErrors();

        $this->assertTrue($hostA->accommodations()->where('accommodations.id', $accommodation->id)->exists());
        $this->assertTrue($hostB->accommodations()->where('accommodations.id', $accommodation->id)->exists());
    }

    public function test_cannot_assign_accommodation_when_role_is_not_host(): void
    {
        $admin = User::create(['name' => 'ادمین', 'mobile' => '09100000001']);
        $admin->assignRole('super_admin');

        $guest = User::create(['name' => 'مهمان', 'mobile' => '09100000003']);
        $guest->assignRole('guest');

        $accommodation = $this->createAccommodation();

        $this->actingAs($admin);

        Livewire::test(UserEdit::class, ['user' => $guest])
            ->set('role', 'guest')
            ->set('accommodationToAssign', $accommodation->id)
            ->call('assignAccommodation')
            ->assertHasErrors(['accommodations']);

        $this->assertFalse($guest->accommodations()->where('accommodations.id', $accommodation->id)->exists());
    }

    public function test_admin_can_reset_host_password_without_current_password(): void
    {
        $admin = User::create(['name' => 'ادمین', 'mobile' => '09100000001']);
        $admin->assignRole('super_admin');

        $host = User::create([
            'name'     => 'میزبان',
            'mobile'   => '09100000002',
            'password' => 'old-password',
        ]);
        $host->assignRole('host');

        $this->actingAs($admin);

        Livewire::test(UserEdit::class, ['user' => $host])
            ->set('role', 'host')
            ->set('hostPassword', 'new-secret')
            ->set('hostPassword_confirmation', 'new-secret')
            ->call('updateHostPassword')
            ->assertHasNoErrors();

        $host->refresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-secret', $host->password));
    }
}
