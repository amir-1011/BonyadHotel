<?php

namespace Tests\Feature;

use App\Livewire\Host\HostCreate;
use App\Livewire\Host\UserEdit;
use App\Livewire\Host\UserShow;
use App\Models\HostPositionTitle;
use App\Models\User;
use App\Support\HostPermissions;
use App\Support\HostPositionTitles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HostUserManagementPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private $accommodation;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $this->accommodation = $this->createTestAccommodation();
    }

    public function test_defaults_exclude_user_management_pages(): void
    {
        $defaults = HostPermissions::defaults();

        foreach (HostPermissions::optInPageKeys() as $pageKey) {
            $this->assertFalse(
                HostPermissions::grantsAllow($pageKey, 'read', $defaults),
                "Expected {$pageKey} to be excluded from defaults",
            );
        }

        $this->assertTrue(HostPermissions::grantsAllow('bookings.list', 'read', $defaults));
    }

    public function test_full_access_grants_still_include_user_management_for_legacy_hosts(): void
    {
        $grants = HostPermissions::fullAccessGrants();

        $this->assertTrue(HostPermissions::grantsAllow('users.list', 'read', $grants));
        $this->assertTrue(HostPermissions::grantsAllow('users.create-host', 'write', $grants));
    }

    public function test_default_position_template_does_not_grant_users_module(): void
    {
        HostPositionTitle::query()->updateOrCreate(
            ['label' => HostPositionTitles::DEFAULT_LABEL],
            [
                'is_system'              => true,
                'sort_order'             => 0,
                'host_panel_permissions' => HostPermissions::defaults(),
            ],
        );

        $host = User::create([
            'name'                   => 'میزبان پیش‌فرض',
            'mobile'                 => '09110000001',
            'host_position_title'    => HostPositionTitles::DEFAULT_LABEL,
            'host_panel_permissions' => HostPositionTitles::grantsForPositionLabel(HostPositionTitles::DEFAULT_LABEL),
        ]);
        $host->assignRole('host');
        $this->accommodation->grantHostAccess($host);

        $this->assertFalse($host->hostCan('users.list', 'read'));
        $this->assertFalse($host->hostCan('users.show', 'read'));
        $this->assertFalse($host->hostCan('users.edit', 'edit'));
        $this->assertFalse($host->hostCan('users.create-host', 'write'));
        $this->assertFalse($host->hasHostPanelAccess('users'));
    }

    public function test_host_without_users_list_permission_cannot_open_users_index(): void
    {
        $host = $this->makeHostWithGrants([
            'bookings.list' => ['read'],
        ]);

        $this->actingAs($host)
            ->get(route('host.users.index'))
            ->assertRedirect(route('host.bookings.index'));
    }

    public function test_host_with_users_list_permission_can_open_users_index(): void
    {
        $host = $this->makeHostWithGrants([
            'users.list' => ['read'],
        ]);

        $this->actingAs($host)
            ->get(route('host.users.index'))
            ->assertOk();
    }

    public function test_host_without_create_host_permission_cannot_open_create_page(): void
    {
        $host = $this->makeHostWithGrants([
            'users.list' => ['read'],
        ]);

        $this->actingAs($host)
            ->get(route('host.users.create-host'))
            ->assertRedirect(route('host.users.index'));
    }

    public function test_host_with_create_host_permission_can_create_scoped_host(): void
    {
        $manager = $this->makeHostWithGrants([
            'users.list'        => ['read'],
            'users.create-host' => ['write'],
            'users.edit'        => ['read', 'edit'],
        ]);

        $this->actingAs($manager);

        Livewire::test(HostCreate::class)
            ->set('name', 'میزبان جدید')
            ->set('mobile', '09110000020')
            ->set('hostPassword', 'secret12')
            ->set('hostPassword_confirmation', 'secret12')
            ->set('selectedAccommodationIds', [$this->accommodation->id])
            ->set('accountingProvinceId', $this->accommodation->resolvedProvince()?->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $created = User::query()->where('mobile', '09110000020')->first();

        $this->assertNotNull($created);
        $this->assertTrue($created->isHost());
        $this->assertTrue($created->managesAccommodation($this->accommodation));
        $this->assertFalse($created->hostCan('users.list', 'read'));
    }

    public function test_host_cannot_assign_accommodation_outside_their_scope_when_creating_host(): void
    {
        $otherAccommodation = $this->createTestAccommodation(['name' => 'اقامتگاه دیگر']);

        $manager = $this->makeHostWithGrants([
            'users.create-host' => ['write'],
        ]);

        $this->actingAs($manager);

        Livewire::test(HostCreate::class)
            ->set('name', 'میزبان جدید')
            ->set('mobile', '09110000021')
            ->set('hostPassword', 'secret12')
            ->set('hostPassword_confirmation', 'secret12')
            ->set('selectedAccommodationIds', [$otherAccommodation->id])
            ->set('accountingProvinceId', $otherAccommodation->resolvedProvince()?->id)
            ->call('save')
            ->assertHasErrors(['selectedAccommodationIds']);
    }

    public function test_host_without_show_permission_cannot_view_user_details(): void
    {
        $manager = $this->makeHostWithGrants([
            'users.list' => ['read'],
        ]);
        $guest = $this->makeGuestInScope();

        $this->actingAs($manager)
            ->get(route('host.users.show', $guest))
            ->assertRedirect(route('host.users.index'));
    }

    public function test_host_with_show_permission_can_view_scoped_user(): void
    {
        $manager = $this->makeHostWithGrants([
            'users.list' => ['read'],
            'users.show' => ['read'],
        ]);
        $guest = $this->makeGuestInScope();

        $this->actingAs($manager)
            ->get(route('host.users.show', $guest))
            ->assertOk()
            ->assertSee('مهمان');
    }

    public function test_host_cannot_view_user_outside_scope(): void
    {
        $manager = $this->makeHostWithGrants([
            'users.list' => ['read'],
            'users.show' => ['read'],
        ]);

        $outsideGuest = User::create([
            'name'   => 'مهمان خارج از محدوده',
            'mobile' => '09110000030',
        ]);
        $outsideGuest->assignRole('guest');

        $this->actingAs($manager)
            ->get(route('host.users.show', $outsideGuest))
            ->assertNotFound();
    }

    public function test_host_with_edit_read_only_cannot_save_changes(): void
    {
        $manager = $this->makeHostWithGrants([
            'users.list' => ['read'],
            'users.edit' => ['read'],
        ]);
        $guest = $this->makeGuestInScope();

        $this->actingAs($manager);

        Livewire::test(UserEdit::class, ['user' => $guest])
            ->set('name', 'نام جدید')
            ->call('update')
            ->assertStatus(403);

        $guest->refresh();
        $this->assertNotSame('نام جدید', $guest->name);
    }

    public function test_host_with_edit_permission_can_update_scoped_user(): void
    {
        $manager = $this->makeHostWithGrants([
            'users.list' => ['read'],
            'users.show' => ['read'],
            'users.edit' => ['read', 'edit'],
        ]);
        $guest = $this->makeGuestInScope();

        $this->actingAs($manager);

        Livewire::test(UserEdit::class, ['user' => $guest])
            ->set('name', 'نام ویرایش‌شده')
            ->call('update')
            ->assertHasNoErrors()
            ->assertRedirect(route('host.users.show', $guest));

        $this->assertSame('نام ویرایش‌شده', $guest->fresh()->name);
    }

    public function test_admin_can_grant_user_management_permissions_via_position_settings(): void
    {
        $position = HostPositionTitle::query()->where('label', 'مدیر مجموعه')->firstOrFail();

        $grants = array_merge(HostPermissions::defaults(), [
            'users.list'        => ['read'],
            'users.show'        => ['read'],
            'users.edit'        => ['read', 'edit'],
            'users.create-host' => ['write'],
            'users.export'      => ['read'],
        ]);

        $position->update(['host_panel_permissions' => $grants]);

        $host = User::create([
            'name'                   => 'میزبان مجاز',
            'mobile'                 => '09110000040',
            'host_position_title'    => 'مدیر مجموعه',
            'host_panel_permissions' => HostPositionTitles::grantsForPositionLabel('مدیر مجموعه'),
        ]);
        $host->assignRole('host');
        $this->accommodation->grantHostAccess($host);

        $this->assertTrue($host->hostCan('users.list', 'read'));
        $this->assertTrue($host->hostCan('users.show', 'read'));
        $this->assertTrue($host->hostCan('users.edit', 'edit'));
        $this->assertTrue($host->hostCan('users.create-host', 'write'));
    }

    public function test_user_show_component_authorizes_scope(): void
    {
        $manager = $this->makeHostWithGrants([
            'users.show' => ['read'],
        ]);

        $outsideGuest = User::create([
            'name'   => 'خارج از محدوده',
            'mobile' => '09110000050',
        ]);
        $outsideGuest->assignRole('guest');

        $this->actingAs($manager);

        Livewire::test(UserShow::class, ['user' => $outsideGuest])
            ->assertNotFound();
    }

    /** @param array<string, list<string>> $grants */
    private function makeHostWithGrants(array $grants): User
    {
        $host = User::create([
            'name'                   => 'میزبان',
            'mobile'                 => '09' . random_int(100000000, 999999999),
            'host_panel_permissions' => $grants,
        ]);
        $host->assignRole('host');
        $this->accommodation->grantHostAccess($host);

        return $host;
    }

    private function makeGuestInScope(): User
    {
        $guest = User::create([
            'name'   => 'مهمان',
            'mobile' => '09' . random_int(100000000, 999999999),
        ]);
        $guest->assignRole('guest');

        $guest->bookings()->create([
            'accommodation_id' => $this->accommodation->id,
            'check_in'         => now()->subDays(2)->toDateString(),
            'check_out'        => now()->subDay()->toDateString(),
            'guests'           => 1,
            'nights'           => 1,
            'base_price'       => 1_000_000,
            'total_price'      => 1_000_000,
            'status'           => 'confirmed',
            'tracking_code'    => 'SCOPE' . random_int(1000, 9999),
        ]);

        return $guest;
    }
}
