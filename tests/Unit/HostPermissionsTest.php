<?php

namespace Tests\Unit;

use App\Support\HostPermissions;
use PHPUnit\Framework\TestCase;

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
}
