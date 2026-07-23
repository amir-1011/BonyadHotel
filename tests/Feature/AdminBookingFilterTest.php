<?php

namespace Tests\Feature;

use App\Livewire\Admin\BookingIndex;
use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingService;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\ServiceCatalog;
use App\Models\ServiceCatalogVariant;
use App\Models\User;
use App\Support\AdminBookingFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Morilog\Jalali\Jalalian;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminBookingFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Accommodation $accommodationA;

    private Accommodation $accommodationB;

    private int $cityAId;

    private int $cityBId;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);

        $this->admin = User::create([
            'name'   => 'ادمین رزرو',
            'mobile' => '09000000999',
        ]);
        $this->admin->assignRole('super_admin');

        $provinceId = DB::table('provinces')->insertGetId([
            'name' => 'استان تست', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->cityAId = DB::table('cities')->insertGetId([
            'province_id' => $provinceId, 'name' => 'شهر الف', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->cityBId = DB::table('cities')->insertGetId([
            'province_id' => $provinceId, 'name' => 'شهر ب', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->accommodationA = Accommodation::create([
            'city_id'         => $this->cityAId,
            'name'            => 'هتل آلفا',
            'price_per_night' => 1_000_000,
            'capacity'        => 10,
            'rooms'           => 5,
            'is_active'       => true,
        ]);

        $this->accommodationB = Accommodation::create([
            'city_id'         => $this->cityBId,
            'name'            => 'هتل بتا',
            'price_per_night' => 2_000_000,
            'capacity'        => 8,
            'rooms'           => 4,
            'is_active'       => true,
        ]);
    }

    public function test_search_matches_tracking_code_guest_contact_and_accommodation_name(): void
    {
        $guest = $this->createGuest('علی رضایی', '09121111111');
        $other = $this->createGuest('سارا احمدی', '09122222222');

        $match = $this->createBooking([
            'tracking_code'        => 'TRACKALPHA1',
            'user_id'              => $guest->id,
            'accommodation_id'     => $this->accommodationA->id,
            'guest_contact_name'   => 'مهمان تماس',
            'guest_contact_mobile' => '09123333333',
        ]);

        $this->createBooking([
            'tracking_code'    => 'OTHERBOOK1',
            'user_id'          => $other->id,
            'accommodation_id' => $this->accommodationB->id,
        ]);

        $this->assertFilterCount(1, ['search' => 'TRACKALPHA']);
        $this->assertFilterCount(1, ['search' => 'مهمان تماس']);
        $this->assertFilterCount(1, ['search' => '09123333333']);
        $this->assertFilterCount(1, ['search' => 'علی']);
        $this->assertFilterCount(1, ['search' => '09121111111']);
        $this->assertFilterCount(1, ['search' => 'آلفا']);
        $this->assertFilterCount(2, ['search' => '']);
        $this->assertContains($match->id, $this->filterIds(['search' => 'TRACKALPHA']));
    }

    public function test_status_filter(): void
    {
        $this->createBooking(['status' => 'pending', 'tracking_code' => 'PEND000001']);
        $this->createBooking(['status' => 'confirmed', 'tracking_code' => 'CONF000001']);
        $this->createBooking(['status' => 'cancelled', 'tracking_code' => 'CANC000001']);

        $this->assertFilterCount(1, ['status' => 'pending']);
        $this->assertFilterCount(1, ['status' => 'confirmed']);
        $this->assertFilterCount(1, ['status' => 'cancelled']);
        $this->assertFilterCount(3, ['status' => '']);
    }

    public function test_accommodation_and_city_filters(): void
    {
        $this->createBooking(['accommodation_id' => $this->accommodationA->id, 'tracking_code' => 'ACCA000001']);
        $this->createBooking(['accommodation_id' => $this->accommodationB->id, 'tracking_code' => 'ACCB000001']);

        $this->assertFilterCount(1, ['accommodation_id' => $this->accommodationA->id]);
        $this->assertFilterCount(1, ['city_id' => $this->cityAId]);
        $this->assertFilterCount(1, ['city_id' => $this->cityBId]);
        $this->assertFilterCount(2, ['city_id' => '']);
    }

    public function test_county_filter(): void
    {
        $provinceId = DB::table('cities')->where('id', $this->cityAId)->value('province_id');
        $countyAId = DB::table('counties')->insertGetId([
            'province_id' => $provinceId,
            'name'        => 'شهرستان الف',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        $countyBId = DB::table('counties')->insertGetId([
            'province_id' => $provinceId,
            'name'        => 'شهرستان ب',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->accommodationA->update(['county_id' => $countyAId]);
        $this->accommodationB->update(['county_id' => $countyBId]);

        $bookingA = $this->createBooking([
            'accommodation_id' => $this->accommodationA->id,
            'tracking_code'    => 'CNTYA00001',
        ]);
        $bookingB = $this->createBooking([
            'accommodation_id' => $this->accommodationB->id,
            'tracking_code'    => 'CNTYB00001',
        ]);

        $noCountyAcc = Accommodation::create([
            'city_id'         => $this->cityAId,
            'county_id'       => null,
            'name'            => 'هتل بدون شهرستان',
            'price_per_night' => 1_000_000,
            'capacity'        => 10,
            'rooms'           => 5,
            'is_active'       => true,
        ]);
        $noCountyBooking = $this->createBooking([
            'accommodation_id' => $noCountyAcc->id,
            'tracking_code'    => 'CNTYN00001',
        ]);

        $this->assertFilterCount(1, ['county_id' => $countyAId]);
        $this->assertSame([$bookingA->id], $this->filterIds(['county_id' => $countyAId]));
        $this->assertFilterCount(1, ['county_id' => $countyBId]);
        $this->assertSame([$bookingB->id], $this->filterIds(['county_id' => $countyBId]));
        $this->assertNotContains($noCountyBooking->id, $this->filterIds(['county_id' => $countyAId]));
        $this->assertFilterCount(3, ['county_id' => '']);
    }

    public function test_county_and_city_filters_combine(): void
    {
        $provinceId = DB::table('cities')->where('id', $this->cityAId)->value('province_id');
        $countyId = DB::table('counties')->insertGetId([
            'province_id' => $provinceId,
            'name'        => 'شهرستان مشترک',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->accommodationA->update(['county_id' => $countyId]);
        $this->accommodationB->update(['county_id' => $countyId]);

        $match = $this->createBooking([
            'accommodation_id' => $this->accommodationA->id,
            'tracking_code'    => 'COMBCNTY01',
        ]);
        $this->createBooking([
            'accommodation_id' => $this->accommodationB->id,
            'tracking_code'    => 'COMBCNTY02',
        ]);

        $ids = $this->filterIds([
            'city_id'   => $this->cityAId,
            'county_id' => $countyId,
        ]);

        $this->assertSame([$match->id], $ids);
    }

    public function test_check_in_date_range_with_jalali_dates(): void
    {
        $early = $this->createBooking([
            'check_in'      => '2025-01-10',
            'check_out'     => '2025-01-12',
            'nights'        => 2,
            'tracking_code' => 'IN01000001',
        ]);
        $late = $this->createBooking([
            'check_in'      => '2025-02-15',
            'check_out'     => '2025-02-18',
            'nights'        => 3,
            'tracking_code' => 'IN02000001',
        ]);

        $from = Jalalian::fromCarbon(\Carbon\Carbon::parse('2025-01-01'))->format('Y/m/d');
        $to = Jalalian::fromCarbon(\Carbon\Carbon::parse('2025-01-31'))->format('Y/m/d');

        $ids = $this->filterIds(['check_in_from' => $from, 'check_in_to' => $to]);
        $this->assertSame([$early->id], $ids);
        $this->assertNotContains($late->id, $ids);
    }

    public function test_check_out_date_range(): void
    {
        $jan = $this->createBooking([
            'check_in'      => '2025-01-05',
            'check_out'     => '2025-01-08',
            'nights'        => 3,
            'tracking_code' => 'OUT0100001',
        ]);
        $mar = $this->createBooking([
            'check_in'      => '2025-03-01',
            'check_out'     => '2025-03-05',
            'nights'        => 4,
            'tracking_code' => 'OUT0300001',
        ]);

        $from = Jalalian::fromCarbon(\Carbon\Carbon::parse('2025-03-01'))->format('Y/m/d');
        $ids = $this->filterIds(['check_out_from' => $from]);
        $this->assertSame([$mar->id], $ids);
        $this->assertNotContains($jan->id, $ids);
    }

    public function test_nights_price_and_guests_ranges(): void
    {
        $small = $this->createBooking([
            'nights'        => 2,
            'guests'        => 2,
            'total_price'   => 500_000,
            'tracking_code' => 'RNGS000001',
        ]);
        $large = $this->createBooking([
            'nights'        => 7,
            'guests'        => 5,
            'total_price'   => 5_000_000,
            'tracking_code' => 'RNGL000001',
        ]);

        $this->assertSame([$small->id], $this->filterIds(['nights_max' => 3]));
        $this->assertSame([$large->id], $this->filterIds(['nights_min' => 5]));
        $this->assertSame([$large->id], $this->filterIds(['guests_min' => 4]));
        $this->assertSame([$small->id], $this->filterIds(['price_max' => 1_000_000]));
        $this->assertSame([$large->id], $this->filterIds(['price_min' => 2_000_000]));
    }

    public function test_has_discount_filter(): void
    {
        $discounted = $this->createBooking([
            'discount_percentage' => 20,
            'tracking_code'       => 'DISC000001',
        ]);
        $regular = $this->createBooking([
            'discount_percentage' => 0,
            'tracking_code'       => 'REGU000001',
        ]);

        $ids = $this->filterIds(['has_discount' => true]);
        $this->assertSame([$discounted->id], $ids);
        $this->assertNotContains($regular->id, $ids);
    }

    public function test_combined_filters_narrow_results(): void
    {
        $match = $this->createBooking([
            'accommodation_id'      => $this->accommodationA->id,
            'status'                => 'confirmed',
            'nights'                => 4,
            'guests'                => 3,
            'total_price'           => 2_000_000,
            'discount_percentage'   => 10,
            'tracking_code'         => 'COMB000001',
        ]);

        $this->createBooking([
            'accommodation_id'    => $this->accommodationA->id,
            'status'              => 'pending',
            'nights'              => 4,
            'guests'              => 3,
            'total_price'         => 2_000_000,
            'discount_percentage' => 10,
            'tracking_code'       => 'COMB000002',
        ]);

        $this->createBooking([
            'accommodation_id'      => $this->accommodationB->id,
            'status'                => 'confirmed',
            'tracking_code'         => 'COMB000003',
        ]);

        $ids = $this->filterIds([
            'accommodation_id' => $this->accommodationA->id,
            'status'           => 'confirmed',
            'nights_min'       => 3,
            'guests_min'       => 2,
            'price_min'        => 1_000_000,
            'has_discount'     => true,
        ]);

        $this->assertSame([$match->id], $ids);
    }

    public function test_invalid_jalali_date_is_ignored(): void
    {
        $this->createBooking(['tracking_code' => 'INVALDATE1']);

        $this->assertFilterCount(1, ['check_in_from' => 'not-a-date']);
    }

    public function test_bookings_page_reads_snake_case_url_filters(): void
    {
        $booking = $this->createBooking([
            'status'        => 'pending',
            'tracking_code' => 'URLFILT001',
            'accommodation_id' => $this->accommodationA->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.bookings.index', [
            'status'           => 'pending',
            'accommodation_id' => $this->accommodationA->id,
            'search'           => 'URLFILT001',
        ]));

        $response->assertOk();
        $response->assertSee('URLFILT001');
        $response->assertSee('badge bg-primary');
    }

    public function test_livewire_apply_filters_updates_results(): void
    {
        $pending = $this->createBooking(['status' => 'pending', 'tracking_code' => 'LWPEND0001']);
        $confirmed = $this->createBooking(['status' => 'confirmed', 'tracking_code' => 'LWCONF0001']);

        Livewire::actingAs($this->admin)
            ->test(BookingIndex::class)
            ->set('draftStatus', 'pending')
            ->call('applyFilters')
            ->assertSee($pending->tracking_code)
            ->assertSee('1 رزرو');
    }

    public function test_livewire_apply_county_filter(): void
    {
        $provinceId = DB::table('cities')->where('id', $this->cityAId)->value('province_id');
        $countyId = DB::table('counties')->insertGetId([
            'province_id' => $provinceId,
            'name'        => 'شمیرانات',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->accommodationA->update(['county_id' => $countyId]);

        $match = $this->createBooking([
            'accommodation_id' => $this->accommodationA->id,
            'tracking_code'    => 'LWCNTY0001',
        ]);
        $other = $this->createBooking([
            'accommodation_id' => $this->accommodationB->id,
            'tracking_code'    => 'LWCNTY0002',
        ]);

        Livewire::actingAs($this->admin)
            ->test(BookingIndex::class)
            ->set('draftCountyId', (string) $countyId)
            ->call('applyFilters')
            ->assertSet('countyId', (string) $countyId)
            ->assertSee($match->tracking_code)
            ->assertSee('1 رزرو');
    }

    public function test_livewire_reset_filters_clears_county(): void
    {
        $provinceId = DB::table('cities')->where('id', $this->cityAId)->value('province_id');
        $countyId = DB::table('counties')->insertGetId([
            'province_id' => $provinceId,
            'name'        => 'ری',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->accommodationA->update(['county_id' => $countyId]);
        $this->createBooking([
            'accommodation_id' => $this->accommodationA->id,
            'tracking_code'    => 'LWRESETCT1',
        ]);
        $other = $this->createBooking([
            'accommodation_id' => $this->accommodationB->id,
            'tracking_code'    => 'LWRESETCT2',
        ]);

        Livewire::actingAs($this->admin)
            ->test(BookingIndex::class)
            ->set('draftCountyId', (string) $countyId)
            ->call('applyFilters')
            ->call('resetFilters')
            ->assertSet('countyId', '')
            ->assertSet('draftCountyId', '')
            ->assertSee($other->tracking_code);
    }

    public function test_bookings_page_reads_county_url_filter(): void
    {
        $provinceId = DB::table('cities')->where('id', $this->cityAId)->value('province_id');
        $countyId = DB::table('counties')->insertGetId([
            'province_id' => $provinceId,
            'name'        => 'دماوند',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->accommodationA->update(['county_id' => $countyId]);

        $booking = $this->createBooking([
            'accommodation_id' => $this->accommodationA->id,
            'tracking_code'    => 'URLCNTYAAA',
        ]);
        $this->createBooking([
            'accommodation_id' => $this->accommodationB->id,
            'tracking_code'    => 'URLCNTYBBB',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.bookings.index', [
            'county_id' => $countyId,
        ]));

        $response->assertOk();
        $response->assertSee('URLCNTYAAA');
        $response->assertSee('۱ رزرو');
    }

    public function test_admin_can_export_bookings_with_county_filter(): void
    {
        $provinceId = DB::table('cities')->where('id', $this->cityAId)->value('province_id');
        $countyId = DB::table('counties')->insertGetId([
            'province_id' => $provinceId,
            'name'        => 'فیروزکوه',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->accommodationA->update(['county_id' => $countyId]);
        $this->createBooking(['status' => 'confirmed', 'tracking_code' => 'EXPCNTY001']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.bookings.export', ['county_id' => $countyId]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_service_parent_filter(): void
    {
        $poolA = $this->createService($this->accommodationA, 'pool_a', 'استخر');
        $gymB = $this->createService($this->accommodationB, 'gym_b', 'بدنسازی');

        $poolBooking = $this->createBookingWithService($this->accommodationA, $poolA, null, 'SRVPOOLAAA');
        $this->createBookingWithService($this->accommodationB, $gymB, null, 'SRVGYMBBBB');
        $this->createBooking(['tracking_code' => 'SRVNOSERVC']);

        $this->assertFilterCount(1, ['service_catalog_id' => $poolA->id]);
        $this->assertSame([$poolBooking->id], $this->filterIds(['service_catalog_id' => $poolA->id]));
        $this->assertFilterCount(3, ['service_catalog_id' => '']);
    }

    public function test_service_variant_filter(): void
    {
        $pool = $this->createService($this->accommodationA, 'pool', 'استخر');
        $variantA = $this->createVariant($pool, 'type_a', 'استخر نشاط');
        $variantB = $this->createVariant($pool, 'type_b', 'استخر پارک آبی');

        $match = $this->createBookingWithService($this->accommodationA, $pool, $variantA, 'SRVVARAAAA');
        $this->createBookingWithService($this->accommodationA, $pool, $variantB, 'SRVVARBBBB');

        $this->assertFilterCount(1, [
            'service_catalog_id'         => $pool->id,
            'service_catalog_variant_id' => $variantA->id,
        ]);
        $this->assertSame([$match->id], $this->filterIds([
            'service_catalog_id'         => $pool->id,
            'service_catalog_variant_id' => $variantA->id,
        ]));
    }

    public function test_service_filters_combine_with_accommodation(): void
    {
        $poolA = $this->createService($this->accommodationA, 'pool', 'استخر');
        $poolB = $this->createService($this->accommodationB, 'pool_b', 'استخر');

        $match = $this->createBookingWithService($this->accommodationA, $poolA, null, 'SRVCOMBAAA');
        $this->createBookingWithService($this->accommodationB, $poolB, null, 'SRVCOMBBBB');

        $ids = $this->filterIds([
            'accommodation_id'  => $this->accommodationA->id,
            'service_catalog_id'=> $poolA->id,
        ]);

        $this->assertSame([$match->id], $ids);
    }

    public function test_livewire_service_cascade_resets_variant(): void
    {
        $pool = $this->createService($this->accommodationA, 'pool', 'استخر');
        $variant = $this->createVariant($pool, 'type_a', 'نوع الف');

        Livewire::actingAs($this->admin)
            ->test(BookingIndex::class)
            ->set('draftServiceCatalogId', (string) $pool->id)
            ->set('draftServiceCatalogVariantId', (string) $variant->id)
            ->set('draftServiceCatalogId', '')
            ->assertSet('draftServiceCatalogVariantId', '');
    }

    public function test_livewire_accommodation_change_resets_service_drafts(): void
    {
        $pool = $this->createService($this->accommodationA, 'pool', 'استخر');
        $variant = $this->createVariant($pool, 'type_a', 'نوع الف');

        Livewire::actingAs($this->admin)
            ->test(BookingIndex::class)
            ->set('draftServiceCatalogId', (string) $pool->id)
            ->set('draftServiceCatalogVariantId', (string) $variant->id)
            ->set('draftReserverId', '99')
            ->set('draftAccommodationId', (string) $this->accommodationB->id)
            ->assertSet('draftServiceCatalogId', '')
            ->assertSet('draftServiceCatalogVariantId', '')
            ->assertSet('draftReserverId', '');
    }

    public function test_livewire_accommodation_scopes_reserver_and_service_options(): void
    {
        $reserverA = User::create(['name' => 'رزروکننده آلفا', 'mobile' => '09123000001']);
        $reserverB = User::create(['name' => 'رزروکننده بتا', 'mobile' => '09123000002']);

        $poolA = $this->createService($this->accommodationA, 'pool_a', 'استخر آلفا');
        $poolB = $this->createService($this->accommodationB, 'pool_b', 'استخر بتا');

        $this->createBooking([
            'accommodation_id' => $this->accommodationA->id,
            'created_by'       => $reserverA->id,
            'booking_source'   => 'manual',
            'tracking_code'    => 'SCOPERSVA',
        ]);
        $this->createBooking([
            'accommodation_id' => $this->accommodationB->id,
            'created_by'       => $reserverB->id,
            'booking_source'   => 'manual',
            'tracking_code'    => 'SCOPERSVB',
        ]);

        $catalog = app(\App\Support\BookingReserverFilterCatalog::class);
        $this->assertCount(2, $catalog->reservers(null));
        $this->assertCount(1, $catalog->reservers((string) $this->accommodationA->id));
        $this->assertSame($reserverA->id, $catalog->reservers((string) $this->accommodationA->id)->first()->id);

        $serviceCatalog = app(\App\Support\BookingServiceFilterCatalog::class);
        $this->assertCount(2, $serviceCatalog->parentServices(null, null, null, null));
        $this->assertCount(1, $serviceCatalog->parentServices((string) $this->accommodationA->id, null, null, null));
        $this->assertSame($poolA->id, $serviceCatalog->parentServices((string) $this->accommodationA->id, null, null, null)->first()->id);

        Livewire::actingAs($this->admin)
            ->test(BookingIndex::class)
            ->assertSee('همه رزروکنندگان')
            ->set('draftAccommodationId', (string) $this->accommodationA->id)
            ->assertSee('همه رزروکنندگان این اقامتگاه')
            ->assertSee('همه خدمات این اقامتگاه')
            ->set('draftServiceCatalogId', (string) $poolA->id)
            ->assertSet('draftServiceCatalogId', (string) $poolA->id);

        $this->assertNotContains($poolB->id, $serviceCatalog->parentServices((string) $this->accommodationA->id, null, null, null)->pluck('id'));
    }

    public function test_livewire_apply_service_filter(): void
    {
        $pool = $this->createService($this->accommodationA, 'pool', 'استخر');
        $variant = $this->createVariant($pool, 'type_a', 'نوع الف');

        $match = $this->createBookingWithService($this->accommodationA, $pool, $variant, 'LWSRVFILTA');
        $this->createBooking(['tracking_code' => 'LWSRVFILTB']);

        Livewire::actingAs($this->admin)
            ->test(BookingIndex::class)
            ->set('draftServiceCatalogId', (string) $pool->id)
            ->set('draftServiceCatalogVariantId', (string) $variant->id)
            ->call('applyFilters')
            ->assertSee($match->tracking_code)
            ->assertSee('1 رزرو');
    }

    public function test_admin_can_export_bookings_with_service_filter(): void
    {
        $pool = $this->createService($this->accommodationA, 'pool', 'استخر');
        $this->createBookingWithService($this->accommodationA, $pool, null, 'EXPSRVAAAA');

        $response = $this->actingAs($this->admin)
            ->get(route('admin.bookings.export', ['service_catalog_id' => $pool->id]));

        $response->assertOk();
    }

    public function test_province_filter(): void
    {
        $provinceId = DB::table('cities')->where('id', $this->cityAId)->value('province_id');

        $this->createBooking(['accommodation_id' => $this->accommodationA->id, 'tracking_code' => 'PRVA000001']);
        $this->createBooking(['accommodation_id' => $this->accommodationB->id, 'tracking_code' => 'PRVB000001']);

        $this->assertFilterCount(2, ['province_id' => $provinceId]);
        $this->assertFilterCount(2, ['province_id' => '']);
    }

    public function test_livewire_province_cascade_resets_city_and_county(): void
    {
        $provinceId = DB::table('cities')->where('id', $this->cityAId)->value('province_id');
        $otherProvinceId = DB::table('provinces')->insertGetId([
            'name' => 'اصفهان', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $countyAId = DB::table('counties')->insertGetId([
            'province_id' => $provinceId,
            'name'        => 'شهرستان الف',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(BookingIndex::class)
            ->set('draftProvinceId', (string) $provinceId)
            ->set('draftCityId', (string) $this->cityAId)
            ->set('draftCountyId', (string) $countyAId)
            ->set('draftProvinceId', (string) $otherProvinceId)
            ->assertSet('draftCityId', '')
            ->assertSet('draftCountyId', '');
    }

    public function test_livewire_province_change_updates_county_options(): void
    {
        $provinceAId = DB::table('cities')->where('id', $this->cityAId)->value('province_id');
        $provinceBId = DB::table('provinces')->insertGetId([
            'name' => 'گیلان', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $countyAId = DB::table('counties')->insertGetId([
            'province_id' => $provinceAId,
            'name'        => 'شمیرانات',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        $countyBId = DB::table('counties')->insertGetId([
            'province_id' => $provinceBId,
            'name'        => 'رشت',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(BookingIndex::class)
            ->set('draftProvinceId', (string) $provinceAId)
            ->assertSee('شمیرانات')
            ->assertDontSee('رشت')
            ->set('draftProvinceId', (string) $provinceBId)
            ->assertSee('رشت')
            ->assertDontSee('شمیرانات');
    }

    public function test_livewire_reset_filters_clears_state(): void
    {
        $this->createBooking(['status' => 'pending', 'tracking_code' => 'LWRESET001']);
        $confirmed = $this->createBooking(['status' => 'confirmed', 'tracking_code' => 'LWRESET002']);

        Livewire::actingAs($this->admin)
            ->test(BookingIndex::class)
            ->set('draftStatus', 'pending')
            ->call('applyFilters')
            ->call('resetFilters')
            ->assertSet('status', '')
            ->assertSee($confirmed->tracking_code);
    }

    public function test_livewire_sort_by_toggles_direction(): void
    {
        Livewire::actingAs($this->admin)
            ->test(BookingIndex::class)
            ->call('sortBy', 'total_price')
            ->assertSet('sort', 'total_price')
            ->assertSet('dir', 'asc')
            ->call('sortBy', 'total_price')
            ->assertSet('dir', 'desc');
    }

    public function test_admin_can_export_bookings_with_filters(): void
    {
        $this->createBooking(['status' => 'confirmed', 'tracking_code' => 'EXP0000001']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.bookings.export', ['status' => 'confirmed']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_filter_by_host_id_limits_to_host_accommodations(): void
    {
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);

        $host = User::create(['name' => 'میزبان الف', 'mobile' => '09120000001']);
        $host->assignRole('host');
        $this->accommodationA->grantHostAccess($host);

        $match = $this->createBooking(['accommodation_id' => $this->accommodationA->id, 'tracking_code' => 'HOSTFLT01']);
        $other = $this->createBooking(['accommodation_id' => $this->accommodationB->id, 'tracking_code' => 'HOSTFLT02']);

        $this->assertSame([$match->id], $this->filterIds(['host_id' => $host->id]));
        $this->assertNotContains($other->id, $this->filterIds(['host_id' => $host->id]));
    }

    public function test_filter_by_reserver_id_matches_created_by_or_self_booking(): void
    {
        $staff = User::create(['name' => 'کارمند رزرو', 'mobile' => '09120000002']);
        $guest = $this->createGuest('مهمان خودرزرو', '09120000003');

        $manual = $this->createBooking([
            'user_id'        => $guest->id,
            'created_by'     => $staff->id,
            'booking_source' => 'manual',
            'tracking_code'  => 'RSRVFLT01',
        ]);
        $selfBooked = $this->createBooking([
            'user_id'        => $guest->id,
            'created_by'     => null,
            'booking_source' => 'manual',
            'tracking_code'  => 'RSRVFLT02',
        ]);
        $other = $this->createBooking(['tracking_code' => 'RSRVFLT03']);

        $this->assertSame([$manual->id], $this->filterIds(['reserver_id' => $staff->id]));
        $this->assertSame([$selfBooked->id], $this->filterIds(['reserver_id' => $guest->id]));
        $this->assertNotContains($other->id, $this->filterIds(['reserver_id' => $staff->id]));
    }

    public function test_online_bookings_are_excluded_from_reserver_catalog_and_filter(): void
    {
        $staff = User::create(['name' => 'کارمند حضوری', 'mobile' => '09120000010']);
        $guest = $this->createGuest('مهمان آنلاین', '09120000011');

        $manual = $this->createBooking([
            'user_id'        => $guest->id,
            'created_by'     => $staff->id,
            'booking_source' => 'manual',
            'tracking_code'  => 'RSRVMAN01',
        ]);
        $online = $this->createBooking([
            'user_id'        => $guest->id,
            'created_by'     => null,
            'booking_source' => 'online',
            'tracking_code'  => 'RSRVONL01',
        ]);

        $catalog = app(\App\Support\BookingReserverFilterCatalog::class);
        $this->assertSame([$staff->id], $catalog->reservers(null)->pluck('id')->all());
        $this->assertNotContains($guest->id, $catalog->reservers(null)->pluck('id')->all());

        $this->assertSame([$manual->id], $this->filterIds(['reserver_id' => $staff->id]));
        $this->assertNotContains($online->id, $this->filterIds(['reserver_id' => $guest->id]));
    }

    public function test_selecting_online_booking_source_clears_reserver_filter(): void
    {
        $reserver = User::create(['name' => 'رزروکننده حضوری', 'mobile' => '09120000012']);

        Livewire::actingAs($this->admin)
            ->test(BookingIndex::class)
            ->set('draftReserverId', (string) $reserver->id)
            ->set('draftBookingSource', 'online')
            ->assertSet('draftReserverId', '')
            ->call('applyFilters')
            ->assertSet('reserverId', '');
    }

    public function test_livewire_loads_reserver_id_from_url(): void
    {
        $reserver = User::create(['name' => 'میزبان بتا', 'mobile' => '09120000004']);

        $match = $this->createBooking([
            'created_by'     => $reserver->id,
            'booking_source' => 'manual',
            'tracking_code'  => 'URLRSRV01',
        ]);
        $this->createBooking(['tracking_code' => 'URLRSRV02']);

        Livewire::actingAs($this->admin)
            ->withQueryParams(['reserver_id' => (string) $reserver->id])
            ->test(BookingIndex::class)
            ->assertSet('reserverId', (string) $reserver->id)
            ->assertSet('draftReserverId', (string) $reserver->id)
            ->assertSee($match->tracking_code);
    }

    public function test_filter_by_room_category_matches_booking_and_booking_room_lines(): void
    {
        $suite = $this->createRoomType($this->accommodationA, 'گروه سوئیت', 'سوئیت');
        $standard = $this->createRoomType($this->accommodationA, 'گروه استاندارد', 'استاندارد');

        $direct = $this->createBooking([
            'room_type_id' => $suite->id,
            'tracking_code' => 'RTDIRECT01',
        ]);
        $lineBooking = $this->createBooking(['tracking_code' => 'RTLINE0001']);
        BookingRoom::create([
            'booking_id'   => $lineBooking->id,
            'room_type_id' => $standard->id,
            'guests'       => 2,
            'sort_order'   => 0,
        ]);
        $other = $this->createBooking(['tracking_code' => 'RTOTHER001']);

        $this->assertSame([$direct->id], $this->filterIds(['room_category' => 'سوئیت']));
        $this->assertSame([$lineBooking->id], $this->filterIds(['room_category' => 'استاندارد']));
        $this->assertNotContains($other->id, $this->filterIds(['room_category' => 'سوئیت']));
    }

    public function test_filter_by_physical_room_id(): void
    {
        $roomType = $this->createRoomType($this->accommodationA, 'گروه دو تخته', 'دو تخته');
        $room101 = Room::create(['room_type_id' => $roomType->id, 'name' => '۱۰۱', 'sort_order' => 1, 'is_active' => true]);
        $room102 = Room::create(['room_type_id' => $roomType->id, 'name' => '۱۰۲', 'sort_order' => 2, 'is_active' => true]);

        $match = $this->createBooking(['tracking_code' => 'ROOM101001']);
        BookingRoom::create([
            'booking_id'   => $match->id,
            'room_type_id' => $roomType->id,
            'room_id'      => $room101->id,
            'guests'       => 2,
            'sort_order'   => 0,
        ]);

        $other = $this->createBooking(['tracking_code' => 'ROOM102001']);
        BookingRoom::create([
            'booking_id'   => $other->id,
            'room_type_id' => $roomType->id,
            'room_id'      => $room102->id,
            'guests'       => 2,
            'sort_order'   => 0,
        ]);

        $this->assertSame([$match->id], $this->filterIds(['room_id' => $room101->id]));
        $this->assertNotContains($other->id, $this->filterIds(['room_id' => $room101->id]));
    }

    public function test_filter_by_booking_source(): void
    {
        $manual = $this->createBooking(['booking_source' => 'manual', 'tracking_code' => 'SRCMANUAL1']);
        $online = $this->createBooking(['booking_source' => 'online', 'tracking_code' => 'SRCONLINE1']);

        $this->assertSame([$manual->id], $this->filterIds(['booking_source' => 'manual']));
        $this->assertSame([$online->id], $this->filterIds(['booking_source' => 'online']));
    }

    public function test_filter_by_veteran_type_on_booking_snapshot(): void
    {
        $match = $this->createBooking([
            'veteran_type_applied' => 'veteran_70_spouses',
            'tracking_code'        => 'VETSNAP001',
        ]);
        $other = $this->createBooking(['tracking_code' => 'VETSNAP002']);

        $this->assertSame([$match->id], $this->filterIds(['veteran_type' => 'veteran_70_spouses']));
        $this->assertNotContains($other->id, $this->filterIds(['veteran_type' => 'veteran_70_spouses']));
    }

    public function test_filter_by_veteran_type_falls_back_to_user_profile(): void
    {
        $guest = $this->createGuest('ایثارگر مهمان', '09129990001');
        $guest->update(['veteran_type' => 'martyr_children']);

        $match = $this->createBooking([
            'user_id'       => $guest->id,
            'tracking_code' => 'VETUSER001',
        ]);
        $other = $this->createBooking(['tracking_code' => 'VETUSER002']);

        $this->assertSame([$match->id], $this->filterIds(['veteran_type' => 'martyr_children']));
        $this->assertNotContains($other->id, $this->filterIds(['veteran_type' => 'martyr_children']));
    }

    public function test_filter_by_veteran_none_excludes_veteran_bookings(): void
    {
        $veteranGuest = $this->createGuest('ایثارگر', '09129990002');
        $veteranGuest->update(['veteran_type' => 'veteran_70_spouses']);

        $normal = $this->createBooking(['tracking_code' => 'VETNONE001']);
        $fromUser = $this->createBooking([
            'user_id'       => $veteranGuest->id,
            'tracking_code' => 'VETNONE002',
        ]);
        $fromSnapshot = $this->createBooking([
            'veteran_type_applied' => 'martyr_children',
            'tracking_code'        => 'VETNONE003',
        ]);

        $ids = $this->filterIds(['veteran_type' => '__none__']);

        $this->assertContains($normal->id, $ids);
        $this->assertNotContains($fromUser->id, $ids);
        $this->assertNotContains($fromSnapshot->id, $ids);
    }

    public function test_livewire_apply_room_and_booking_source_filters(): void
    {
        $roomType = $this->createRoomType($this->accommodationA, 'گروه فیلتر', 'دو تخته');
        $match = $this->createBooking([
            'room_type_id'   => $roomType->id,
            'booking_source' => 'manual',
            'tracking_code'  => 'LWFILTROOM',
        ]);
        $this->createBooking([
            'booking_source' => 'online',
            'tracking_code'  => 'LWFILTONLN',
        ]);

        Livewire::actingAs($this->admin)
            ->test(BookingIndex::class)
            ->set('draftRoomCategory', 'دو تخته')
            ->set('draftBookingSource', 'manual')
            ->call('applyFilters')
            ->assertSee($match->tracking_code)
            ->assertSee('1 رزرو');
    }

    public function test_livewire_accommodation_change_resets_room_filters(): void
    {
        $roomType = $this->createRoomType($this->accommodationA, 'گروه آلفا', 'دو تخته');
        $room = Room::create(['room_type_id' => $roomType->id, 'name' => '۲۰۱', 'sort_order' => 1, 'is_active' => true]);

        Livewire::actingAs($this->admin)
            ->test(BookingIndex::class)
            ->set('draftAccommodationId', (string) $this->accommodationA->id)
            ->set('draftRoomCategory', 'دو تخته')
            ->set('draftRoomId', (string) $room->id)
            ->set('draftAccommodationId', (string) $this->accommodationB->id)
            ->assertSet('draftRoomCategory', '')
            ->assertSet('draftRoomId', '');
    }

    public function test_physical_room_display_shows_group_then_physical_name(): void
    {
        $roomType = $this->createRoomType($this->accommodationA, 'گروه نمایشی', 'سوئیت');
        $room = Room::create(['room_type_id' => $roomType->id, 'name' => '۳۰۵', 'sort_order' => 1, 'is_active' => true]);
        $booking = $this->createBooking(['tracking_code' => 'PHYROOM001']);
        BookingRoom::create([
            'booking_id'   => $booking->id,
            'room_type_id' => $roomType->id,
            'room_id'      => $room->id,
            'guests'       => 2,
            'sort_order'   => 0,
        ]);

        $booking->load('bookingRooms.room', 'bookingRooms.roomType');

        $this->assertSame('گروه نمایشی · ۳۰۵', $booking->physicalRoomNamesDisplay());
    }

    public function test_room_filter_catalog_scopes_categories_by_accommodation(): void
    {
        $this->createRoomType($this->accommodationA, 'گروه آلفا', 'دو تخته');
        $this->createRoomType($this->accommodationB, 'گروه بتا', 'سوئیت');

        $catalog = app(\App\Support\BookingRoomFilterCatalog::class);

        $this->assertSame(['دو تخته'], $catalog->categories((string) $this->accommodationA->id)->all());
    }

    public function test_admin_can_export_bookings_with_new_filters(): void
    {
        $this->createBooking([
            'booking_source' => 'manual',
            'tracking_code'  => 'EXPNEWFILT',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.bookings.export', ['booking_source' => 'manual']));

        $response->assertOk();
    }

    /** @param  array<string, mixed>  $overrides */
    private function createBooking(array $overrides = []): Booking
    {
        $tracking = $overrides['tracking_code'] ?? 'BOOK'.strtoupper(substr(uniqid(), -6));
        $guest = $overrides['user'] ?? $this->createGuest('مهمان '.$tracking, '0912'.str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT));

        return Booking::create(array_merge([
            'user_id'              => $guest->id,
            'accommodation_id'     => $this->accommodationA->id,
            'check_in'             => '2025-06-01',
            'check_out'            => '2025-06-04',
            'guests'               => 2,
            'nights'               => 3,
            'base_price'           => 3_000_000,
            'discount_percentage'  => 0,
            'discount_amount'      => 0,
            'total_price'          => 3_000_000,
            'status'               => 'confirmed',
            'tracking_code'        => $tracking,
        ], collect($overrides)->except('user')->all()));
    }

    private function createGuest(string $name, string $mobile): User
    {
        $user = User::create([
            'name'   => $name,
            'mobile' => $mobile,
        ]);
        $user->assignRole('guest');

        return $user;
    }

    /** @param  array<string, mixed>  $filters */
    private function filterIds(array $filters): array
    {
        $query = Booking::query();
        AdminBookingFilter::make($filters)->apply($query, withSort: false);

        return $query->orderBy('id')->pluck('id')->all();
    }

    /** @param  array<string, mixed>  $filters */
    private function assertFilterCount(int $expected, array $filters): void
    {
        $query = Booking::query();
        AdminBookingFilter::make($filters)->apply($query, withSort: false);
        $this->assertSame($expected, $query->count(), 'Filter failed: '.json_encode($filters, JSON_UNESCAPED_UNICODE));
    }

    private function createService(Accommodation $accommodation, string $key, string $name): ServiceCatalog
    {
        return ServiceCatalog::create([
            'accommodation_id' => $accommodation->id,
            'key'              => $key,
            'name'             => $name,
            'default_price'    => 500_000,
            'is_active'        => true,
        ]);
    }

    private function createVariant(ServiceCatalog $service, string $key, string $name): ServiceCatalogVariant
    {
        return ServiceCatalogVariant::create([
            'service_catalog_id' => $service->id,
            'key'                => $key,
            'name'               => $name,
            'price'              => 500_000,
            'is_active'          => true,
        ]);
    }

    private function createBookingWithService(
        Accommodation $accommodation,
        ServiceCatalog $service,
        ?ServiceCatalogVariant $variant,
        string $trackingCode,
    ): Booking {
        $booking = $this->createBooking([
            'accommodation_id' => $accommodation->id,
            'tracking_code'    => $trackingCode,
        ]);

        BookingService::create([
            'booking_id'                 => $booking->id,
            'service_catalog_id'         => $service->id,
            'service_catalog_variant_id' => $variant?->id,
            'name'                       => $variant?->name ?? $service->name,
            'unit_price'                 => $variant?->price ?? $service->default_price,
            'quantity'                   => 1,
            'free_units'                 => 0,
            'total'                      => $variant?->price ?? $service->default_price,
            'sort_order'                 => 1,
        ]);

        return $booking;
    }

    private function createRoomType(Accommodation $accommodation, string $name, ?string $bedType = null): RoomType
    {
        return RoomType::create([
            'accommodation_id' => $accommodation->id,
            'name'             => $name,
            'bed_type'         => $bedType ?? $name,
            'capacity'         => 2,
            'room_count'       => 2,
            'is_active'        => true,
        ]);
    }
}
