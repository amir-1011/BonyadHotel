<?php

namespace Tests\Unit;

use App\Support\HostPermissions;
use Tests\TestCase;

class HostPermissionsTest extends TestCase
{
    public function test_legacy_module_list_expands_to_page_grants(): void
    {
        $grants = HostPermissions::expandLegacyModules(['bookings', 'users']);

        $this->assertTrue(HostPermissions::grantsAllow('bookings.list', 'read', $grants));
        $this->assertTrue(HostPermissions::grantsAllow('cancellation-requests.list', 'read', $grants));
        $this->assertTrue(HostPermissions::grantsAllow('cancellation-requests.decide', 'edit', $grants));
        $this->assertTrue(HostPermissions::grantsAllow('cancellation-requests.settle', 'edit', $grants));
        $this->assertTrue(HostPermissions::grantsAllow('users.export', 'read', $grants));
        $this->assertFalse(HostPermissions::grantsAllow('accommodations.list', 'read', $grants));
    }

    public function test_form_state_round_trip_preserves_grants(): void
    {
        $grants = [
            'accommodations.list' => ['read'],
            'bookings.show'       => ['read', 'edit'],
        ];

        $form = HostPermissions::grantsToFormState($grants);
        $roundTrip = HostPermissions::grantsFromFormState($form);

        $this->assertSame($grants, $roundTrip);
    }

    public function test_route_permission_mapping_for_room_type_store(): void
    {
        $required = HostPermissions::permissionForRoute('host.room-types.store', 'POST');

        $this->assertSame('room-types.create', $required['page']);
        $this->assertSame('write', $required['action']);
    }

    public function test_legacy_cancellation_list_edit_migrates_to_decide_and_settle(): void
    {
        $grants = HostPermissions::sanitizeGrants([
            'cancellation-requests.list' => ['read', 'edit'],
        ]);

        $this->assertSame(['read'], $grants['cancellation-requests.list']);
        $this->assertTrue(HostPermissions::grantsAllow('cancellation-requests.decide', 'edit', $grants));
        $this->assertTrue(HostPermissions::grantsAllow('cancellation-requests.settle', 'edit', $grants));
        $this->assertFalse(HostPermissions::grantsAllow('cancellation-requests.list', 'edit', $grants));
    }

    public function test_legacy_dashboard_grants_expand_to_widget_pages(): void
    {
        $grants = HostPermissions::sanitizeGrants([
            'dashboard' => ['read', 'edit'],
        ]);

        $this->assertTrue(HostPermissions::grantsAllow('dashboard.kpi-accommodations', 'read', $grants));
        $this->assertTrue(HostPermissions::grantsAllow('dashboard.room-status-board', 'read', $grants));
        $this->assertTrue(HostPermissions::grantsAllow('dashboard.room-status-board', 'edit', $grants));
        $this->assertTrue(HostPermissions::grantsAllow('dashboard.booking-actions', 'edit', $grants));
        $this->assertFalse(HostPermissions::grantsAllow('dashboard.booking-actions', 'read', $grants));
        $this->assertArrayNotHasKey('dashboard', $grants);
    }

    public function test_grants_have_dashboard_read_access(): void
    {
        $grants = [
            'dashboard.recent-bookings' => ['read'],
        ];

        $this->assertTrue(HostPermissions::grantsHaveDashboardReadAccess($grants));
        $this->assertTrue(HostPermissions::grantsHaveDashboardAccess($grants));
    }

    public function test_guest_edit_grants_are_added_when_bookings_module_is_enabled(): void
    {
        $grants = HostPermissions::backfillGuestEditGrants([
            'bookings.show' => ['read', 'edit'],
        ]);

        $this->assertTrue(HostPermissions::grantsAllow('bookings.guests', 'read', $grants));
        $this->assertTrue(HostPermissions::grantsAllow('bookings.guests', 'edit', $grants));
    }

    public function test_guest_edit_grants_are_added_when_programs_module_is_enabled(): void
    {
        $grants = HostPermissions::backfillGuestEditGrants([
            'programs.show' => ['read'],
        ]);

        $this->assertTrue(HostPermissions::grantsAllow('programs.guests', 'read', $grants));
        $this->assertTrue(HostPermissions::grantsAllow('programs.guests', 'edit', $grants));
        $this->assertTrue(HostPermissions::grantsAllow('programs.pricing', 'read', $grants));
        $this->assertTrue(HostPermissions::grantsAllow('programs.pricing', 'edit', $grants));
    }

    public function test_sanitize_grants_does_not_force_guest_edit_permissions(): void
    {
        $grants = HostPermissions::sanitizeGrants([
            'bookings.show' => ['read'],
        ]);

        $this->assertFalse(HostPermissions::grantsAllow('bookings.guests', 'edit', $grants));
    }

    public function test_defaults_include_guest_edit_permissions(): void
    {
        $defaults = HostPermissions::defaults();

        $this->assertTrue(HostPermissions::grantsAllow('bookings.guests', 'edit', $defaults));
        $this->assertTrue(HostPermissions::grantsAllow('programs.guests', 'edit', $defaults));
        $this->assertTrue(HostPermissions::grantsAllow('bookings.dates', 'edit', $defaults));
        $this->assertTrue(HostPermissions::grantsAllow('programs.dates', 'edit', $defaults));
        $this->assertTrue(HostPermissions::grantsAllow('programs.pricing', 'edit', $defaults));
        $this->assertFalse(HostPermissions::grantsAllow('users.list', 'read', $defaults));
    }

    public function test_landing_route_for_grants_prefers_needed_when_surplus_unavailable(): void
    {
        $grants = [
            'facility-needed.list'   => ['read'],
            'facility-needed.create' => ['write'],
        ];

        $this->assertSame(
            route('host.facility.needed.index'),
            HostPermissions::landingRouteForGrants('facility-management', $grants),
        );
    }

    public function test_route_permission_mapping_for_facility_surplus_routes(): void
    {
        $index = HostPermissions::permissionForRoute('host.facility.surplus.index');
        $create = HostPermissions::permissionForRoute('host.facility.surplus.create');
        $edit = HostPermissions::permissionForRoute('host.facility.surplus.edit');

        $this->assertSame(['page' => 'facility-surplus.list', 'action' => 'read'], $index);
        $this->assertSame(['page' => 'facility-surplus.create', 'action' => 'write'], $create);
        $this->assertSame(['page' => 'facility-surplus.edit', 'action' => 'read'], $edit);
    }

    public function test_strip_opt_in_pages_removes_user_management_from_defaults(): void
    {
        $stripped = HostPermissions::stripOptInPages(HostPermissions::fullAccessGrants());

        $this->assertFalse(HostPermissions::grantsAllow('users.list', 'read', $stripped));
        $this->assertTrue(HostPermissions::grantsAllow('bookings.list', 'read', $stripped));
    }

    public function test_route_permission_mapping_for_host_user_management_routes(): void
    {
        $this->assertSame(
            ['page' => 'users.show', 'action' => 'read'],
            HostPermissions::permissionForRoute('host.users.show'),
        );
        $this->assertSame(
            ['page' => 'users.edit', 'action' => 'read'],
            HostPermissions::permissionForRoute('host.users.edit'),
        );
        $this->assertSame(
            ['page' => 'users.create-host', 'action' => 'write'],
            HostPermissions::permissionForRoute('host.users.create-host'),
        );
    }

    public function test_backfill_facility_management_grants(): void
    {
        $grants = HostPermissions::moduleFullAccessGrants('accommodations');
        $backfilled = HostPermissions::backfillFacilityManagementGrants($grants);

        $this->assertArrayHasKey('facility-surplus.list', $backfilled);
        $this->assertArrayHasKey('facility-needed.create', $backfilled);
    }

    public function test_backfill_medical_accommodation_grants(): void
    {
        $grants = [
            'accommodations.veteran-policy' => ['read', 'edit'],
        ];
        $backfilled = HostPermissions::backfillMedicalAccommodationGrants($grants);

        $this->assertArrayHasKey('accommodations.medical-accommodation', $backfilled);
        $this->assertTrue(HostPermissions::grantsAllow('accommodations.medical-accommodation', 'read', $backfilled));
        $this->assertTrue(HostPermissions::grantsAllow('accommodations.medical-accommodation', 'edit', $backfilled));
        $this->assertSame(
            ['page' => 'accommodations.medical-accommodation', 'action' => 'read'],
            HostPermissions::permissionForRoute('host.accommodations.medical-accommodation'),
        );
    }

    public function test_backfill_medical_accommodation_report_grants(): void
    {
        $grants = [
            'bookings.list' => ['read'],
        ];
        $backfilled = HostPermissions::backfillMedicalAccommodationReportGrants($grants);

        $this->assertArrayHasKey('bookings.medical-accommodation-report', $backfilled);
        $this->assertTrue(HostPermissions::grantsAllow('bookings.medical-accommodation-report', 'read', $backfilled));
        $this->assertSame(
            ['page' => 'bookings.medical-accommodation-report', 'action' => 'read'],
            HostPermissions::permissionForRoute('host.medical-accommodation-report'),
        );
    }
}
