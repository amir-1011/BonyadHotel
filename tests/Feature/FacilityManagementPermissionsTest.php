<?php

namespace Tests\Feature;

use App\Models\FacilityExchangeItem;
use App\Models\User;
use App\Support\HostPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithFacilityExchange;
use Tests\TestCase;

class FacilityManagementPermissionsTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithFacilityExchange;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupFacilityExchangeContext();
    }

    public function test_all_facility_host_routes_are_mapped_in_permissions(): void
    {
        $routes = [
            'host.facility.surplus.index'  => ['page' => 'facility-surplus.list', 'action' => 'read'],
            'host.facility.surplus.create' => ['page' => 'facility-surplus.create', 'action' => 'write'],
            'host.facility.surplus.edit'   => ['page' => 'facility-surplus.edit', 'action' => 'read'],
            'host.facility.needed.index'   => ['page' => 'facility-needed.list', 'action' => 'read'],
            'host.facility.needed.create'  => ['page' => 'facility-needed.create', 'action' => 'write'],
            'host.facility.needed.edit'    => ['page' => 'facility-needed.edit', 'action' => 'read'],
        ];

        foreach ($routes as $routeName => $expected) {
            $mapped = HostPermissions::permissionForRoute($routeName, 'GET');
            $this->assertNotNull($mapped, "Route {$routeName} is not mapped");
            $this->assertSame($expected['page'], $mapped['page']);
            $this->assertSame($expected['action'], $mapped['action']);
        }
    }

    public function test_facility_module_landing_route_points_to_surplus_index(): void
    {
        $this->assertSame(
            route('host.facility.surplus.index'),
            HostPermissions::landingRoute('facility-management'),
        );
    }

    public function test_read_only_host_can_view_surplus_but_not_create(): void
    {
        $host = $this->hostWithGrants([
            'facility-surplus.list' => ['read'],
        ], '09120000101');

        $this->actingAs($host)
            ->get(route('host.facility.surplus.index'))
            ->assertOk();

        $this->actingAs($host)
            ->get(route('host.facility.surplus.create'))
            ->assertRedirect(route('host.facility.surplus.index'));
    }

    public function test_write_only_surplus_host_can_create_but_not_edit_others(): void
    {
        $host = $this->hostWithGrants([
            'facility-surplus.list'   => ['read'],
            'facility-surplus.create' => ['write'],
        ], '09120000102');

        $this->actingAs($host)
            ->get(route('host.facility.surplus.create'))
            ->assertOk();

        $item = $this->makeSurplusItem(['user_id' => $this->facilityOtherHost->id]);

        $this->actingAs($host)
            ->get(route('host.facility.surplus.edit', $item))
            ->assertRedirect(route('host.facility.surplus.index'));
    }

    public function test_needed_permissions_are_independent_from_surplus(): void
    {
        $host = $this->hostWithGrants([
            'facility-needed.list'   => ['read'],
            'facility-needed.create' => ['write'],
        ], '09120000103');

        $this->actingAs($host)
            ->get(route('host.facility.needed.index'))
            ->assertOk();

        $this->actingAs($host)
            ->get(route('host.facility.surplus.index'))
            ->assertRedirect(route('host.facility.needed.index'));
    }

    public function test_guest_user_cannot_access_facility_routes(): void
    {
        $guest = User::create(['name' => 'مهمان', 'mobile' => '09120000104']);
        $guest->assignRole(Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']));

        $this->actingAs($guest)
            ->get(route('host.facility.surplus.index'))
            ->assertForbidden();
    }

    public function test_admin_host_position_matrix_includes_facility_pages(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::create(['name' => 'ادمین', 'mobile' => '09120000105']);
        $admin->assignRole('super_admin');

        $this->actingAs($admin)
            ->get(route('admin.host-positions.index'))
            ->assertOk()
            ->assertSee('مدیریت اماکن')
            ->assertSee('لیست اقلام مازاد')
            ->assertSee('ثبت اقلام مورد نیاز');
    }

    public function test_form_state_round_trip_includes_facility_grants(): void
    {
        $grants = [
            'facility-surplus.list'   => ['read'],
            'facility-surplus.create' => ['write'],
            'facility-needed.edit'    => ['read', 'edit', 'delete'],
        ];

        $form = HostPermissions::grantsToFormState($grants);
        $roundTrip = HostPermissions::grantsFromFormState($form);

        $this->assertSame($grants, $roundTrip);
    }

    public function test_backfill_does_not_grant_facility_when_no_other_modules(): void
    {
        $grants = ['bookings.list' => ['read']];
        $backfilled = HostPermissions::backfillFacilityManagementGrants($grants);

        $this->assertArrayNotHasKey('facility-surplus.list', $backfilled);
    }
}
