<?php

namespace Tests\Feature;

use App\Livewire\Host\BookingIndex;
use App\Exports\HostUsersExport;
use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\BookingService;
use App\Models\County;
use App\Models\ServiceCatalog;
use App\Models\ServiceCatalogVariant;
use App\Models\User;
use App\Support\AdminBookingFilter;
use App\Support\HostUserFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
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

    public function test_host_county_filter_scopes_to_managed_accommodations(): void
    {
        $provinceId = DB::table('cities')->where('id', $this->ownedAccommodation->city_id)->value('province_id');
        $countyId = County::create([
            'province_id' => $provinceId,
            'name'        => 'شهرستان میزبان',
        ])->id;

        $this->ownedAccommodation->update(['county_id' => $countyId]);
        $this->otherAccommodation->update(['county_id' => $countyId]);

        $guest = User::create(['name' => 'مهمان', 'mobile' => '09120000005']);

        Booking::create([
            'user_id'              => $guest->id,
            'accommodation_id'     => $this->ownedAccommodation->id,
            'tracking_code'        => 'HOSTCNTY01',
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
            'tracking_code'        => 'HOSTCNTY02',
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
        AdminBookingFilter::make(['county_id' => $countyId], [$this->ownedAccommodation->id])->apply($query, withSort: false);

        $this->assertSame(1, $query->count());
        $this->assertSame('HOSTCNTY01', $query->first()->tracking_code);
    }

    public function test_host_livewire_county_filter(): void
    {
        $provinceId = DB::table('cities')->where('id', $this->ownedAccommodation->city_id)->value('province_id');
        $countyId = County::create([
            'province_id' => $provinceId,
            'name'        => 'پردیس',
        ])->id;

        $this->ownedAccommodation->update(['county_id' => $countyId]);

        $guest = User::create(['name' => 'مهمان', 'mobile' => '09120000006']);

        Booking::create([
            'user_id'              => $guest->id,
            'accommodation_id'     => $this->ownedAccommodation->id,
            'tracking_code'        => 'HOSTLWCNT1',
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
            'tracking_code'        => 'HOSTLWCNT2',
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

        Livewire::actingAs($this->host)
            ->test(BookingIndex::class)
            ->set('draftCountyId', (string) $countyId)
            ->call('applyFilters')
            ->assertSee('HOSTLWCNT1')
            ->assertDontSee('HOSTLWCNT2');
    }

    public function test_host_livewire_service_filter(): void
    {
        $pool = ServiceCatalog::create([
            'accommodation_id' => $this->ownedAccommodation->id,
            'key'              => 'pool',
            'name'             => 'استخر',
            'default_price'    => 500_000,
            'is_active'        => true,
        ]);
        $variant = ServiceCatalogVariant::create([
            'service_catalog_id' => $pool->id,
            'key'                => 'active',
            'name'               => 'استخر نشاط',
            'price'              => 500_000,
            'is_active'          => true,
        ]);

        $guest = User::create(['name' => 'مهمان', 'mobile' => '09120000007']);

        $ownedBooking = Booking::create([
            'user_id'              => $guest->id,
            'accommodation_id'     => $this->ownedAccommodation->id,
            'tracking_code'        => 'HOSTSRVOWN',
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
        BookingService::create([
            'booking_id'                 => $ownedBooking->id,
            'service_catalog_id'         => $pool->id,
            'service_catalog_variant_id' => $variant->id,
            'name'                       => $variant->name,
            'unit_price'                 => 500_000,
            'quantity'                   => 1,
            'free_units'                 => 0,
            'total'                      => 500_000,
            'sort_order'                 => 1,
        ]);

        $otherBooking = Booking::create([
            'user_id'              => $guest->id,
            'accommodation_id'     => $this->otherAccommodation->id,
            'tracking_code'        => 'HOSTSRVOTH',
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

        Livewire::actingAs($this->host)
            ->test(BookingIndex::class)
            ->set('draftServiceCatalogId', (string) $pool->id)
            ->set('draftServiceCatalogVariantId', (string) $variant->id)
            ->call('applyFilters')
            ->assertSee('HOSTSRVOWN')
            ->assertDontSee('HOSTSRVOTH');
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
