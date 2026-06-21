<?php

namespace Tests\Feature;

use App\Exports\HostUsersExport;
use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\User;
use App\Support\AdminBookingFilter;
use App\Support\HostUserFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HostBookingFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    private Accommodation $ownedAccommodation;

    private Accommodation $otherAccommodation;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);

        $provinceId = DB::table('provinces')->insertGetId([
            'name' => 'استان تست', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $cityId = DB::table('cities')->insertGetId([
            'province_id' => $provinceId, 'name' => 'شهر تست', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->host = User::create(['name' => 'میزبان', 'mobile' => '09120000001']);
        $this->host->assignRole('host');

        $this->ownedAccommodation = Accommodation::create([
            'city_id'         => $cityId,
            'name'            => 'هتل میزبان',
            'price_per_night' => 1_000_000,
            'capacity'        => 10,
            'rooms'           => 5,
            'is_active'       => true,
        ]);

        $this->otherAccommodation = Accommodation::create([
            'city_id'         => $cityId,
            'name'            => 'هتل دیگر',
            'price_per_night' => 1_000_000,
            'capacity'        => 10,
            'rooms'           => 5,
            'is_active'       => true,
        ]);

        $this->host->accommodations()->attach($this->ownedAccommodation->id);
    }

    public function test_host_booking_filter_scopes_to_managed_accommodations(): void
    {
        $guest = User::create(['name' => 'مهمان', 'mobile' => '09120000002']);

        Booking::create([
            'user_id'              => $guest->id,
            'accommodation_id'     => $this->ownedAccommodation->id,
            'tracking_code'        => 'HOSTOWNED1',
            'check_in'             => '2025-06-01',
            'check_out'            => '2025-06-02',
            'nights'               => 1,
            'guests'               => 2,
            'base_price'           => 1_000_000,
            'discount_percentage'  => 0,
            'discount_amount'      => 0,
            'total_price'          => 1_000_000,
            'status'               => 'confirmed',
        ]);

        Booking::create([
            'user_id'              => $guest->id,
            'accommodation_id'     => $this->otherAccommodation->id,
            'tracking_code'        => 'HOSTOTHER1',
            'check_in'             => '2025-06-01',
            'check_out'            => '2025-06-02',
            'nights'               => 1,
            'guests'               => 2,
            'base_price'           => 2_000_000,
            'discount_percentage'  => 0,
            'discount_amount'      => 0,
            'total_price'          => 2_000_000,
            'status'               => 'confirmed',
        ]);

        $query = Booking::query();
        AdminBookingFilter::make([], [$this->ownedAccommodation->id])->apply($query, withSort: false);

        $this->assertSame(1, $query->count());
        $this->assertSame('HOSTOWNED1', $query->first()->tracking_code);
    }

    public function test_host_can_export_scoped_bookings(): void
    {
        Excel::fake();

        $this->actingAs($this->host)
            ->get(route('host.bookings.export', ['status' => 'confirmed']))
            ->assertOk();

        Excel::assertDownloaded('host-bookings.xlsx');
    }

    public function test_host_user_filter_limits_to_guests_of_managed_accommodations(): void
    {
        $guestA = User::create(['name' => 'مهمان الف', 'mobile' => '09120000003']);
        $guestB = User::create(['name' => 'مهمان ب', 'mobile' => '09120000004']);

        Booking::create([
            'user_id'              => $guestA->id,
            'accommodation_id'     => $this->ownedAccommodation->id,
            'tracking_code'        => 'GUESTA001',
            'check_in'             => '2025-06-01',
            'check_out'            => '2025-06-02',
            'nights'               => 1,
            'guests'               => 2,
            'base_price'           => 1_000_000,
            'discount_percentage'  => 0,
            'discount_amount'      => 0,
            'total_price'          => 1_000_000,
            'status'               => 'confirmed',
        ]);

        Booking::create([
            'user_id'              => $guestB->id,
            'accommodation_id'     => $this->otherAccommodation->id,
            'tracking_code'        => 'GUESTB001',
            'check_in'             => '2025-06-01',
            'check_out'            => '2025-06-02',
            'nights'               => 1,
            'guests'               => 2,
            'base_price'           => 1_000_000,
            'discount_percentage'  => 0,
            'discount_amount'      => 0,
            'total_price'          => 1_000_000,
            'status'               => 'confirmed',
        ]);

        $query = User::query();
        HostUserFilter::make([], [$this->ownedAccommodation->id])->apply($query);

        $this->assertSame(1, $query->count());
        $this->assertSame($guestA->id, $query->first()->id);
    }

    public function test_host_can_export_users(): void
    {
        Excel::fake();

        $this->actingAs($this->host)
            ->get(route('host.users.export'))
            ->assertOk();

        Excel::assertDownloaded('host-users.xlsx', function (HostUsersExport $export) {
            return true;
        });
    }
}
