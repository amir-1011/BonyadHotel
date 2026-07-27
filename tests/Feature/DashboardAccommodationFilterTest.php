<?php

namespace Tests\Feature;

use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Host\Dashboard as HostDashboard;
use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\User;
use App\Services\AdminDashboardDataService;
use App\Services\HostDashboardDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardAccommodationFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    private User $admin;

    private Accommodation $accommodationA;

    private Accommodation $accommodationB;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $provinceId = $this->ensureTestProvinceId();
        $cityId = $this->ensureTestCityId($provinceId);

        $this->host = User::create(['name' => 'میزبان', 'mobile' => '09123000001']);
        $this->host->assignRole('host');

        $this->admin = User::create(['name' => 'ادمین', 'mobile' => '09123000002']);
        $this->admin->assignRole('super_admin');

        $this->accommodationA = Accommodation::create([
            'city_id' => $cityId,
            'name' => 'هتل آلفا',
            'price_per_night' => 1_000_000,
            'capacity' => 10,
            'rooms' => 5,
            'is_active' => true,
        ]);
        $this->accommodationB = Accommodation::create([
            'city_id' => $cityId,
            'name' => 'هتل بتا',
            'price_per_night' => 2_000_000,
            'capacity' => 8,
            'rooms' => 4,
            'is_active' => true,
        ]);

        $this->accommodationA->grantHostAccess($this->host);
        $this->accommodationB->grantHostAccess($this->host);
    }

    public function test_host_dashboard_defaults_to_all_accommodations(): void
    {
        $this->createConfirmedBooking($this->accommodationA, 1_000_000);
        $this->createConfirmedBooking($this->accommodationB, 2_000_000);

        Livewire::actingAs($this->host)
            ->test(HostDashboard::class)
            ->assertSet('dashboardAccommodationAllSelected', true)
            ->assertSee('همه اقامتگاه‌ها (2)')
            ->assertSee('3,000,000');
    }

    public function test_host_dashboard_filters_stats_by_selected_accommodation(): void
    {
        $this->createConfirmedBooking($this->accommodationA, 1_000_000);
        $this->createConfirmedBooking($this->accommodationB, 2_000_000);

        Livewire::actingAs($this->host)
            ->test(HostDashboard::class)
            ->call('toggleDraftDashboardAccommodation', $this->accommodationB->id)
            ->call('applyDashboardAccommodationFilter')
            ->assertSet('dashboardAccommodationAllSelected', false)
            ->assertSee('1,000,000')
            ->assertDontSee('3,000,000');
    }

    public function test_host_dashboard_select_all_restores_full_stats(): void
    {
        $this->createConfirmedBooking($this->accommodationA, 1_000_000);
        $this->createConfirmedBooking($this->accommodationB, 2_000_000);

        Livewire::actingAs($this->host)
            ->test(HostDashboard::class)
            ->call('toggleDraftDashboardAccommodation', $this->accommodationB->id)
            ->call('applyDashboardAccommodationFilter')
            ->call('selectAllDraftDashboardAccommodations')
            ->call('applyDashboardAccommodationFilter')
            ->assertSet('dashboardAccommodationAllSelected', true)
            ->assertSee('3,000,000');
    }

    public function test_clear_draft_unchecks_all_without_applying(): void
    {
        $this->createConfirmedBooking($this->accommodationA, 1_000_000);
        $this->createConfirmedBooking($this->accommodationB, 2_000_000);

        Livewire::actingAs($this->host)
            ->test(HostDashboard::class)
            ->call('clearDraftDashboardAccommodations')
            ->assertSet('draftDashboardAccommodationAllSelected', false)
            ->assertSet('draftDashboardAccommodationIds', [])
            ->assertSet('dashboardAccommodationAllSelected', true)
            ->assertSee('3,000,000');
    }

    public function test_clear_draft_unchecks_all_before_apply(): void
    {
        $this->createConfirmedBooking($this->accommodationA, 1_000_000);

        Livewire::actingAs($this->host)
            ->test(HostDashboard::class)
            ->call('clearDraftDashboardAccommodations')
            ->assertSet('draftDashboardAccommodationAllSelected', false)
            ->assertSet('draftDashboardAccommodationIds', [])
            ->assertSet('dashboardAccommodationAllSelected', true)
            ->call('applyDashboardAccommodationFilter')
            ->assertSet('dashboardAccommodationAllSelected', false)
            ->assertSee('هیچ اقامتگاهی انتخاب نشده');
    }

    public function test_host_dashboard_data_service_scopes_revenue(): void
    {
        $this->createConfirmedBooking($this->accommodationA, 1_000_000);
        $this->createConfirmedBooking($this->accommodationB, 2_000_000);

        $all = app(HostDashboardDataService::class)->build($this->host, [
            $this->accommodationA->id,
            $this->accommodationB->id,
        ]);
        $scoped = app(HostDashboardDataService::class)->build($this->host, [
            $this->accommodationA->id,
        ]);

        $this->assertSame(3_000_000, (int) $all['stats']['revenue']);
        $this->assertSame(1_000_000, (int) $scoped['stats']['revenue']);
        $this->assertCount(1, $scoped['myAccommodations']);
    }

    public function test_admin_dashboard_filters_booking_stats(): void
    {
        $this->createConfirmedBooking($this->accommodationA, 1_000_000);
        $this->createConfirmedBooking($this->accommodationB, 2_000_000);

        Livewire::actingAs($this->admin)
            ->test(AdminDashboard::class)
            ->call('toggleDraftDashboardAccommodation', $this->accommodationB->id)
            ->call('applyDashboardAccommodationFilter')
            ->assertSee('1,000,000')
            ->assertDontSee('3,000,000');
    }

    public function test_admin_dashboard_data_service_scopes_bookings(): void
    {
        $this->createConfirmedBooking($this->accommodationA, 1_000_000);
        $this->createConfirmedBooking($this->accommodationB, 2_000_000);

        $all = app(AdminDashboardDataService::class)->build([
            $this->accommodationA->id,
            $this->accommodationB->id,
        ]);
        $scoped = app(AdminDashboardDataService::class)->build([
            $this->accommodationA->id,
        ]);

        $this->assertSame(2, $all['stats']['confirmed']);
        $this->assertSame(3_000_000, (int) $all['stats']['revenue']);
        $this->assertSame(1, $scoped['stats']['confirmed']);
        $this->assertSame(1_000_000, (int) $scoped['stats']['revenue']);
    }

    private function createConfirmedBooking(Accommodation $accommodation, int $totalPrice): Booking
    {
        $guest = User::create([
            'name' => 'مهمان',
            'mobile' => '0912' . random_int(1000000, 9999999),
        ]);
        $guest->assignRole('guest');

        return Booking::create([
            'user_id' => $guest->id,
            'accommodation_id' => $accommodation->id,
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'guests' => 2,
            'nights' => 1,
            'base_price' => $totalPrice,
            'discount_percentage' => 0,
            'discount_amount' => 0,
            'total_price' => $totalPrice,
            'status' => 'confirmed',
            'tracking_code' => 'T' . random_int(100000, 999999),
        ]);
    }
}
