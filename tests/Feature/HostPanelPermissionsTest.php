<?php

namespace Tests\Feature;

use App\Livewire\Admin\UserEdit;
use App\Models\User;
use App\Support\HostPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HostPanelPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
    }

    public function test_admin_can_save_host_panel_permissions(): void
    {
        $admin = User::create(['name' => 'ادمین', 'mobile' => '09100000001']);
        $admin->assignRole('super_admin');

        $host = User::create(['name' => 'میزبان', 'mobile' => '09100000002']);
        $host->assignRole('host');

        $this->actingAs($admin);

        Livewire::test(UserEdit::class, ['user' => $host])
            ->set('role', 'host')
            ->set('hostPanelPermissions', ['bookings', 'users'])
            ->call('saveHostPanelAccess')
            ->assertHasNoErrors();

        $host->refresh();

        $this->assertSame(['bookings', 'users'], $host->host_panel_permissions);
        $this->assertTrue($host->hasHostPanelAccess('bookings'));
        $this->assertFalse($host->hasHostPanelAccess('accommodations'));
    }

    public function test_host_without_permission_is_redirected_from_accommodations(): void
    {
        $host = User::create([
            'name'                    => 'میزبان محدود',
            'mobile'                  => '09100000003',
            'host_panel_permissions'  => ['bookings'],
        ]);
        $host->assignRole('host');

        $this->actingAs($host)
            ->get(route('host.accommodations.index'))
            ->assertRedirect(route('host.bookings.index'));
    }

    public function test_host_with_users_permission_can_open_users_page(): void
    {
        $host = User::create([
            'name'                   => 'میزبان',
            'mobile'                 => '09100000004',
            'host_panel_permissions' => ['users'],
        ]);
        $host->assignRole('host');

        $this->actingAs($host)
            ->get(route('host.users.index'))
            ->assertOk();
    }

    public function test_null_permissions_mean_full_access_for_backward_compatibility(): void
    {
        $host = User::create(['name' => 'میزبان', 'mobile' => '09100000005']);
        $host->assignRole('host');

        $this->assertSame(HostPermissions::defaults(), $host->effectiveHostPermissions());
    }
}
