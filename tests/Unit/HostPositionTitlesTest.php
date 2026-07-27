<?php

namespace Tests\Unit;

use App\Models\HostPositionTitle;
use App\Models\User;
use App\Support\HostPermissions;
use App\Support\HostPositionTitles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HostPositionTitlesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
    }

    public function test_defaults_are_seeded_in_catalog(): void
    {
        $this->assertDatabaseHas('host_position_titles', ['label' => 'میزبان', 'is_system' => true]);
        $this->assertDatabaseHas('host_position_titles', ['label' => 'مدیر مالی', 'is_system' => true]);
        $this->assertContains('کارشناس پشتیبانی', HostPositionTitles::options());
        $this->assertSame('میزبان', HostPositionTitles::options()[0]);
    }

    public function test_remember_persists_custom_title(): void
    {
        $label = HostPositionTitles::remember('سمت ویژه');

        $this->assertSame('سمت ویژه', $label);
        $this->assertDatabaseHas('host_position_titles', ['label' => 'سمت ویژه', 'is_system' => false]);
        $this->assertContains('سمت ویژه', HostPositionTitle::optionLabels());
    }

    public function test_resolve_preset(): void
    {
        $this->assertSame('مدیر داخلی', HostPositionTitles::resolve('مدیر داخلی'));
        $this->assertSame('میزبان', HostPositionTitles::resolve(''));
        $this->assertSame('میزبان', HostPositionTitles::defaultLabel());
    }

    public function test_sync_users_for_default_position_backfills_legacy_hosts(): void
    {
        $host = User::create([
            'name'                   => 'میزبان قدیمی',
            'mobile'                 => '09120009999',
            'host_position_title'    => null,
            'host_panel_permissions' => HostPermissions::fullAccessGrants(),
        ]);
        $host->assignRole('host');

        $grants = [
            'bookings.list' => ['read'],
        ];

        HostPositionTitles::savePermissionsForLabel('میزبان', $grants);

        $synced = HostPositionTitles::syncUsersForPosition('میزبان', $grants);

        $host->refresh();

        $this->assertSame(1, $synced);
        $this->assertSame('میزبان', $host->host_position_title);
        $this->assertSame($grants, $host->host_panel_permissions);
    }

    public function test_is_default_position_label(): void
    {
        $this->assertTrue(HostPositionTitles::isDefaultPositionLabel(null));
        $this->assertTrue(HostPositionTitles::isDefaultPositionLabel(''));
        $this->assertTrue(HostPositionTitles::isDefaultPositionLabel('میزبان'));
        $this->assertFalse(HostPositionTitles::isDefaultPositionLabel('مدیر مالی'));
    }

    public function test_form_state_from_stored_uses_default_label(): void
    {
        $this->assertSame('میزبان', HostPositionTitles::formStateFromStored(null));
        $this->assertSame('مدیر مالی', HostPositionTitles::formStateFromStored('مدیر مالی'));
    }
}
