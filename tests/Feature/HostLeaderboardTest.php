<?php

namespace Tests\Feature;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\User;
use App\Services\HostLeaderboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HostLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    private User $hostA;

    private User $hostB;

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

        $this->hostA = User::create(['name' => 'میزبان آلفا', 'mobile' => '09121000001']);
        $this->hostA->assignRole('host');
        $this->hostB = User::create(['name' => 'میزبان بتا', 'mobile' => '09121000002']);
        $this->hostB->assignRole('host');

        $this->accommodationA = Accommodation::create([
            'city_id' => $cityId, 'name' => 'هتل آلفا', 'price_per_night' => 1_000_000,
            'capacity' => 10, 'rooms' => 5, 'is_active' => true,
        ]);
        $this->accommodationB = Accommodation::create([
            'city_id' => $cityId, 'name' => 'هتل بتا', 'price_per_night' => 2_000_000,
            'capacity' => 8, 'rooms' => 4, 'is_active' => true,
        ]);
        $this->accommodationA->grantHostAccess($this->hostA);
        $this->accommodationB->grantHostAccess($this->hostB);
    }

    public function test_build_ranks_hosts_by_monthly_revenue_as_reserver(): void
    {
        $month = '2026-06-01';
        Carbon::setTestNow('2026-06-15');

        $this->createConfirmedBooking($this->hostA, $this->accommodationA, 3_000_000, '2026-06-10');
        $this->createConfirmedBooking($this->hostB, $this->accommodationB, 5_000_000, '2026-06-12');

        $data = app(HostLeaderboardService::class)->build('2026-06');

        $this->assertSame('2026-06', $data['month']);
        $this->assertCount(2, $data['hosts']);
        $this->assertSame($this->hostB->id, $data['hosts'][0]->id);
        $this->assertSame(5_000_000.0, $data['hosts'][0]->revenue);
        $this->assertSame(['هتل بتا'], $data['hosts'][0]->accommodations);

        Carbon::setTestNow();
    }

    public function test_build_calculates_growth_vs_previous_month(): void
    {
        Carbon::setTestNow('2026-06-15');

        $this->createConfirmedBooking($this->hostA, $this->accommodationA, 2_000_000, '2026-05-10');
        $this->createConfirmedBooking($this->hostA, $this->accommodationA, 4_000_000, '2026-06-10');
        $this->createConfirmedBooking($this->hostA, $this->accommodationA, 1_000_000, '2026-06-20');

        $host = app(HostLeaderboardService::class)->build('2026-06')['hosts']->first();

        $this->assertSame($this->hostA->id, $host->id);
        $this->assertSame(150.0, $host->revenue_growth_pct);
        $this->assertSame(1, $host->bookings_delta);

        Carbon::setTestNow();
    }

    public function test_livewire_month_filter_changes_results(): void
    {
        Carbon::setTestNow('2026-06-15');

        $admin = User::create(['name' => 'ادمین', 'mobile' => '09129999999']);
        $admin->assignRole('super_admin');

        $this->createConfirmedBooking($this->hostA, $this->accommodationA, 1_000_000, '2026-06-10');

        Livewire::actingAs($admin)
            ->test(\App\Livewire\Admin\HostLeaderboard::class)
            ->call('showMonthly')
            ->assertSee('میزبان آلفا')
            ->assertSee('هتل آلفا')
            ->set('month', '2026-05')
            ->assertDontSee('میزبان آلفا');

        Carbon::setTestNow();
    }

    public function test_build_all_time_ranks_across_all_dates(): void
    {
        Carbon::setTestNow('2026-06-15');

        $this->createConfirmedBooking($this->hostA, $this->accommodationA, 2_000_000, '2026-05-10');
        $this->createConfirmedBooking($this->hostB, $this->accommodationB, 5_000_000, '2026-06-12');

        $data = app(HostLeaderboardService::class)->build(HostLeaderboardService::ALL_TIME_KEY);

        $this->assertTrue($data['all_time']);
        $this->assertSame('همه تاریخ‌ها', $data['month_label']);
        $this->assertNull($data['check_in_from']);
        $this->assertCount(2, $data['hosts']);
        $this->assertSame($this->hostB->id, $data['hosts'][0]->id);
        $this->assertSame(5_000_000.0, $data['hosts'][0]->revenue);

        Carbon::setTestNow();
    }

    public function test_livewire_all_time_toggle(): void
    {
        Carbon::setTestNow('2026-06-15');

        $admin = User::create(['name' => 'ادمین', 'mobile' => '09129999998']);
        $admin->assignRole('super_admin');

        $this->createConfirmedBooking($this->hostA, $this->accommodationA, 2_000_000, '2026-05-10');

        Livewire::actingAs($admin)
            ->test(\App\Livewire\Admin\HostLeaderboard::class)
            ->call('showAllTime')
            ->assertSet('allTime', true)
            ->assertSee('همه تاریخ‌ها')
            ->assertSee('میزبان آلفا')
            ->call('showMonthly')
            ->assertSet('allTime', false);

        Carbon::setTestNow();
    }

    private function createConfirmedBooking(User $reserver, Accommodation $accommodation, int $price, string $createdAt): Booking
    {
        $guest = User::create([
            'name'   => 'مهمان ' . $price,
            'mobile' => '0913' . str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT),
        ]);
        $guest->assignRole('guest');

        return tap(Booking::create([
            'user_id'          => $guest->id,
            'created_by'       => $reserver->id,
            'accommodation_id' => $accommodation->id,
            'check_in'         => '2026-06-01',
            'check_out'        => '2026-06-03',
            'guests'           => 2,
            'nights'           => 2,
            'base_price'       => $price,
            'discount_percentage' => 0,
            'discount_amount'  => 0,
            'total_price'      => $price,
            'status'           => 'confirmed',
            'tracking_code'    => 'LB' . strtoupper(substr(uniqid(), -6)),
        ]), function (Booking $booking) use ($createdAt) {
            $booking->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();
        });
    }
}
