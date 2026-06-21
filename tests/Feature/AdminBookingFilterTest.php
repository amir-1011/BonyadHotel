<?php

namespace Tests\Feature;

use App\Livewire\Admin\BookingIndex;
use App\Models\Accommodation;
use App\Models\Booking;
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
            ->assertDontSee($confirmed->tracking_code);
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
}
