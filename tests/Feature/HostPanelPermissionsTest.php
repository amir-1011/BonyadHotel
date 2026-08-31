<?php

namespace Tests\Feature;

use App\Models\HostPositionTitle;
use App\Models\User;
use App\Support\HostPermissions;
use App\Support\HostPositionTitles;
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

    public function test_restricted_default_position_limits_new_host_access(): void
    {
        $grants = [
            'bookings.list' => ['read'],
        ];

        HostPositionTitle::query()->updateOrCreate(
            ['label' => 'میزبان'],
            [
                'is_system'              => true,
                'sort_order'             => 0,
                'host_panel_permissions' => $grants,
            ],
        );

        $host = User::create([
            'name'                   => 'میزبان محدود',
            'mobile'                 => '09100000011',
            'host_position_title'    => 'میزبان',
            'host_panel_permissions' => HostPositionTitles::grantsForPositionLabel('میزبان'),
        ]);
        $host->assignRole('host');

        $this->assertTrue($host->hostCan('bookings.list', 'read'));
        $this->assertFalse($host->hostCan('accommodations.list', 'read'));

        $this->actingAs($host)
            ->get(route('host.accommodations.index'))
            ->assertRedirect(route('host.bookings.index'));
    }

    public function test_legacy_host_with_null_position_follows_default_template(): void
    {
        $grants = [
            'bookings.list' => ['read'],
        ];

        HostPositionTitle::query()->updateOrCreate(
            ['label' => 'میزبان'],
            [
                'is_system'              => true,
                'sort_order'             => 0,
                'host_panel_permissions' => $grants,
            ],
        );

        $host = User::create([
            'name'                   => 'میزبان قدیمی',
            'mobile'                 => '09100000012',
            'host_position_title'    => null,
            'host_panel_permissions' => HostPermissions::fullAccessGrants(),
        ]);
        $host->assignRole('host');

        $this->assertTrue($host->usesDefaultHostPosition());
        $this->assertTrue($host->hostCan('bookings.list', 'read'));
        $this->assertFalse($host->hostCan('accommodations.list', 'read'));
    }

    public function test_admin_can_save_granular_host_panel_permissions_via_position_settings(): void
    {
        $admin = User::create(['name' => 'ادمین', 'mobile' => '09100000001']);
        $admin->assignRole('super_admin');

        $position = HostPositionTitle::query()->where('label', 'کارشناس پشتیبانی')->firstOrFail();

        $grants = [
            'bookings.list' => ['read', 'edit'],
            'users.list'    => ['read'],
        ];

        $this->actingAs($admin);

        Livewire::test(\App\Livewire\Admin\HostPositionPermissionIndex::class)
            ->call('selectPosition', $position->id)
            ->set('hostPermissionForm', HostPermissions::grantsToFormState($grants))
            ->call('save')
            ->assertHasNoErrors();

        $host = User::create([
            'name'  => 'میزبان',
            'mobile'=> '09100000002',
            'host_position_title' => 'کارشناس پشتیبانی',
            'host_panel_permissions' => HostPositionTitles::grantsForPositionLabel('کارشناس پشتیبانی'),
        ]);
        $host->assignRole('host');

        $this->assertSame($grants, $host->host_panel_permissions);
        $this->assertTrue($host->hasHostPanelAccess('bookings'));
        $this->assertTrue($host->hostCan('bookings.list', 'read'));
        $this->assertTrue($host->hostCan('bookings.list', 'edit'));
        $this->assertFalse($host->hostCan('bookings.list', 'delete'));
        $this->assertFalse($host->hasHostPanelAccess('accommodations'));
    }

    public function test_legacy_module_permissions_expand_to_full_module_access(): void
    {
        $host = User::create([
            'name'                   => 'میزبان',
            'mobile'                 => '09100000008',
            'host_panel_permissions' => ['bookings', 'users'],
        ]);
        $host->assignRole('host');

        $this->assertTrue($host->hostCan('bookings.list', 'read'));
        $this->assertTrue($host->hostCan('bookings.export', 'read'));
        $this->assertTrue($host->hostCan('cancellation-requests.list', 'read'));
        $this->assertTrue($host->hostCan('cancellation-requests.decide', 'edit'));
        $this->assertTrue($host->hostCan('cancellation-requests.settle', 'edit'));
        $this->assertFalse($host->hostCan('accommodations.list', 'read'));
    }

    public function test_host_without_permission_is_redirected_from_accommodations(): void
    {
        $host = User::create([
            'name'                   => 'میزبان محدود',
            'mobile'                 => '09100000003',
            'host_panel_permissions' => [
                'bookings.list' => ['read'],
            ],
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
            'host_panel_permissions' => [
                'users.list' => ['read'],
            ],
        ]);
        $host->assignRole('host');

        $this->actingAs($host)
            ->get(route('host.users.index'))
            ->assertOk();
    }

    public function test_host_without_write_permission_cannot_open_create_accommodation_page(): void
    {
        $host = User::create([
            'name'                   => 'میزبان',
            'mobile'                 => '09100000009',
            'host_panel_permissions' => [
                'accommodations.list' => ['read'],
            ],
        ]);
        $host->assignRole('host');

        $this->actingAs($host)
            ->get(route('host.accommodations.create'))
            ->assertRedirect(route('host.accommodations.index'));
    }

    public function test_null_permissions_use_default_template_without_user_management(): void
    {
        $host = User::create(['name' => 'میزبان', 'mobile' => '09100000005']);
        $host->assignRole('host');

        $this->assertSame(HostPermissions::defaults(), $host->effectiveHostPermissionGrants());
        $this->assertFalse($host->hostCan('users.list', 'read'));
        $this->assertTrue($host->hostCan('bookings.list', 'read'));
    }

    public function test_host_dashboard_shows_only_permitted_widgets(): void
    {
        $host = User::create([
            'name'                   => 'میزبان',
            'mobile'                 => '09100000010',
            'host_panel_permissions' => [
                'dashboard.overview'          => ['read'],
                'dashboard.kpi-accommodations' => ['read'],
                'dashboard.recent-bookings'   => ['read'],
            ],
        ]);
        $host->assignRole('host');

        $this->actingAs($host)
            ->get(route('host.dashboard'))
            ->assertOk()
            ->assertSee('اقامتگاه‌های من')
            ->assertSee('آخرین رزروها')
            ->assertDontSee('درآمد و رزرو')
            ->assertDontSee('وضعیت اتاق‌ها');
    }

    public function test_host_login_redirects_to_dashboard_when_dashboard_permission_is_granted(): void
    {
        $host = User::create([
            'name'                   => 'میزبان',
            'mobile'                 => '09100000006',
            'host_panel_permissions' => [
                'dashboard.overview' => ['read'],
                'bookings.list'=> ['read'],
                'users.list'   => ['read'],
            ],
        ]);
        $host->assignRole('host');

        $this->assertSame(route('host.dashboard'), $host->staffDashboardUrl());
    }

    public function test_host_login_skips_dashboard_when_dashboard_permission_is_missing(): void
    {
        $host = User::create([
            'name'                   => 'میزبان',
            'mobile'                 => '09100000007',
            'host_panel_permissions' => [
                'bookings.list' => ['read'],
                'users.list'    => ['read'],
            ],
        ]);
        $host->assignRole('host');

        $this->assertSame(route('host.bookings.index'), $host->staffDashboardUrl());
    }
}
