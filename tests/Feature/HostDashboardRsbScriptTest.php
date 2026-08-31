<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HostDashboardRsbScriptTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_dashboard_includes_rsb_layout_sort_script(): void
    {
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);

        $host = User::create(['name' => 'میزبان', 'mobile' => '09129999999']);
        $host->assignRole('host');

        $html = $this->actingAs($host)
            ->get(route('host.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('rsb-layout-sort', $html);
        $this->assertStringContainsString('persian-digits', $html);
    }
}
