<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffSessionTimeoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
    }

    public function test_admin_stays_logged_in_within_timeout(): void
    {
        $admin = User::create(['name' => 'ادمین', 'mobile' => '09100000001']);
        $admin->assignRole('super_admin');

        $this->actingAs($admin)
            ->withSession(['staff_last_activity' => now()->subHours(2)->timestamp])
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_admin_logged_out_after_three_hours_inactivity(): void
    {
        $admin = User::create(['name' => 'ادمین', 'mobile' => '09100000001']);
        $admin->assignRole('super_admin');

        $this->actingAs($admin)
            ->withSession(['staff_last_activity' => now()->subHours(4)->timestamp])
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }

    public function test_host_logged_out_after_three_hours_inactivity(): void
    {
        $host = User::create(['name' => 'میزبان', 'mobile' => '09100000002']);
        $host->assignRole('host');

        $this->actingAs($host)
            ->withSession(['staff_last_activity' => now()->subHours(4)->timestamp])
            ->get(route('host.dashboard'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }

    public function test_simple_user_not_affected_by_staff_timeout(): void
    {
        config(['staff_mode.enabled' => false]);

        $user = User::create(['name' => 'کاربر', 'mobile' => '09100000003', 'mobile_verified_at' => now()]);

        $this->actingAs($user)
            ->withSession(['staff_last_activity' => now()->subHours(4)->timestamp])
            ->get(route('bookings.index'));

        $this->assertAuthenticatedAs($user);
    }
}
