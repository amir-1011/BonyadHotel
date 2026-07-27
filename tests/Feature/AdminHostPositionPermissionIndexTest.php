<?php

namespace Tests\Feature;

use App\Livewire\Admin\HostPositionPermissionIndex;
use App\Livewire\Admin\UserEdit;
use App\Models\HostPositionTitle;
use App\Models\User;
use App\Support\HostPermissions;
use App\Support\HostPositionTitles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminHostPositionPermissionIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
    }

    private function admin(): User
    {
        $admin = User::create(['name' => 'ادمین', 'mobile' => '09100000001']);
        $admin->assignRole('super_admin');

        return $admin;
    }

    public function test_admin_can_view_host_positions_page(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.host-positions.index'))
            ->assertOk();
    }

    public function test_admin_can_save_permissions_for_position(): void
    {
        $position = HostPositionTitle::query()->where('label', 'مدیر مالی')->firstOrFail();

        $grants = [
            'dashboard.overview' => ['read'],
            'bookings.list' => ['read', 'edit'],
            'users.list'    => ['read'],
        ];

        $legacyHost = User::create([
            'name'                   => 'میزبان قدیمی',
            'mobile'                 => '09120000050',
            'host_position_title'    => null,
            'host_panel_permissions' => HostPermissions::fullAccessGrants(),
        ]);
        $legacyHost->assignRole('host');

        $this->actingAs($this->admin());

        Livewire::test(HostPositionPermissionIndex::class)
            ->call('selectPosition', $position->id)
            ->set('hostPermissionForm', HostPermissions::grantsToFormState($grants))
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast', type: 'success');

        $position->refresh();

        $this->assertSame($grants, $position->host_panel_permissions);
    }

    public function test_saving_default_position_syncs_legacy_hosts(): void
    {
        $position = HostPositionTitle::query()->where('label', 'میزبان')->firstOrFail();

        $legacyHost = User::create([
            'name'                   => 'میزبان قدیمی',
            'mobile'                 => '09120000051',
            'host_position_title'    => null,
            'host_panel_permissions' => HostPermissions::fullAccessGrants(),
        ]);
        $legacyHost->assignRole('host');

        $grants = [
            'bookings.list' => ['read'],
        ];

        $this->actingAs($this->admin());

        Livewire::test(HostPositionPermissionIndex::class)
            ->call('selectPosition', $position->id)
            ->set('hostPermissionForm', HostPermissions::grantsToFormState($grants))
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast', type: 'success');

        $legacyHost->refresh();

        $this->assertSame('میزبان', $legacyHost->host_position_title);
        $this->assertSame($grants, $legacyHost->host_panel_permissions);
        $this->assertFalse($legacyHost->hostCan('accommodations.list', 'read'));
    }

    public function test_admin_can_add_new_position_from_settings_page(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(HostPositionPermissionIndex::class)
            ->set('newPositionLabel', 'سرپرست شیفت')
            ->call('addPosition')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('host_position_titles', ['label' => 'سرپرست شیفت']);

        $position = HostPositionTitle::query()->where('label', 'سرپرست شیفت')->first();

        $this->assertNotNull($position);
        $this->assertSame(HostPermissions::defaults(), $position->host_panel_permissions);
    }

    public function test_duplicate_position_label_is_rejected(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(HostPositionPermissionIndex::class)
            ->set('newPositionLabel', 'مدیر مالی')
            ->call('addPosition')
            ->assertHasErrors(['newPositionLabel']);
    }

    public function test_new_position_appears_in_host_create_form(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(HostPositionPermissionIndex::class)
            ->set('newPositionLabel', 'سمت دائمی')
            ->call('addPosition')
            ->assertHasNoErrors();

        Livewire::test(\App\Livewire\Admin\HostCreate::class)
            ->assertViewHas('hostPositionOptions', function (array $options): bool {
                return in_array('سمت دائمی', $options, true);
            });
    }

    public function test_support_class_grants_for_position_label(): void
    {
        $grants = [
            'dashboard.overview' => ['read'],
            'users.list' => ['read'],
        ];

        HostPositionTitles::savePermissionsForLabel('کارشناس فروش', $grants);

        $this->assertSame($grants, HostPositionTitles::grantsForPositionLabel('کارشناس فروش'));
        $this->assertSame(HostPermissions::defaults(), HostPositionTitles::grantsForPositionLabel(null));
        $this->assertSame(HostPermissions::defaults(), HostPositionTitles::grantsForPositionLabel('میزبان'));
    }

    public function test_default_host_position_is_seeded_and_listed_first(): void
    {
        $this->assertDatabaseHas('host_position_titles', [
            'label'     => 'میزبان',
            'is_system' => true,
        ]);

        $options = HostPositionTitles::options();

        $this->assertSame('میزبان', $options[0]);
    }

    public function test_default_host_position_cannot_be_renamed(): void
    {
        $position = HostPositionTitle::query()->where('label', 'میزبان')->firstOrFail();

        $this->actingAs($this->admin());

        Livewire::test(HostPositionPermissionIndex::class)
            ->call('selectPosition', $position->id)
            ->set('editingPositionLabel', 'میزبان ارشد')
            ->call('updatePositionLabel')
            ->assertHasErrors(['editingPositionLabel']);
    }

    public function test_user_edit_applies_position_template_on_save(): void
    {
        $grants = [
            'bookings.list' => ['read', 'edit'],
            'users.list'    => ['read'],
        ];

        HostPositionTitle::query()->updateOrCreate(
            ['label' => 'مدیر داخلی'],
            [
                'is_system'              => true,
                'sort_order'             => 6,
                'host_panel_permissions' => $grants,
            ],
        );

        $host = User::create([
            'name'                   => 'میزبان',
            'mobile'                 => '09120000030',
            'host_position_title'    => 'مدیر مجموعه',
            'host_panel_permissions' => ['dashboard.overview' => ['read']],
        ]);
        $host->assignRole('host');

        $this->actingAs($this->admin());

        Livewire::test(UserEdit::class, ['user' => $host])
            ->set('role', 'host')
            ->set('hostPositionPreset', 'مدیر داخلی')
            ->call('update')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $host->refresh();

        $this->assertSame('مدیر داخلی', $host->host_position_title);
        $this->assertSame($grants, $host->host_panel_permissions);
    }

    public function test_admin_can_rename_position_and_update_linked_users(): void
    {
        $position = HostPositionTitle::query()->where('label', 'مدیر مالی')->firstOrFail();

        $host = User::create([
            'name'                => 'میزبان',
            'mobile'              => '09120000040',
            'host_position_title' => 'مدیر مالی',
        ]);
        $host->assignRole('host');

        $this->actingAs($this->admin());

        Livewire::test(HostPositionPermissionIndex::class)
            ->call('selectPosition', $position->id)
            ->set('editingPositionLabel', 'مدیر مالی و حسابداری')
            ->call('updatePositionLabel')
            ->assertHasNoErrors()
            ->assertDispatched('toast', type: 'success');

        $position->refresh();
        $host->refresh();

        $this->assertSame('مدیر مالی و حسابداری', $position->label);
        $this->assertSame('مدیر مالی و حسابداری', $host->host_position_title);
    }

    public function test_rename_position_rejects_duplicate_label(): void
    {
        $position = HostPositionTitle::query()->where('label', 'مدیر داخلی')->firstOrFail();

        $this->actingAs($this->admin());

        Livewire::test(HostPositionPermissionIndex::class)
            ->call('selectPosition', $position->id)
            ->set('editingPositionLabel', 'مدیر مالی')
            ->call('updatePositionLabel')
            ->assertHasErrors(['editingPositionLabel']);
    }
}
