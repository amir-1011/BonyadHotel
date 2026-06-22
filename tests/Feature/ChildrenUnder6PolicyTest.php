<?php

namespace Tests\Feature;

use App\Models\Accommodation;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use App\Services\BookingPricingService;
use App\Services\ManualBookingService;
use Carbon\Carbon;
use Database\Seeders\VeteranPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChildrenUnder6PolicyTest extends TestCase
{
    use RefreshDatabase;

    private Accommodation $accommodation;
    private RoomType $roomType;
    private RoomRate $roomRate;
    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VeteranPolicySeeder::class);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $this->adminUser = User::create([
            'name'   => 'ادمین تست',
            'mobile' => '09000000002',
        ]);
        $this->adminUser->assignRole('super_admin');

        $provinceId = DB::table('provinces')->insertGetId(['name' => 'استان تست', 'created_at' => now(), 'updated_at' => now()]);
        $cityId = DB::table('cities')->insertGetId(['province_id' => $provinceId, 'name' => 'شهر تست', 'created_at' => now(), 'updated_at' => now()]);

        $this->accommodation = Accommodation::create([
            'city_id'         => $cityId,
            'name'            => 'اقامتگاه کودک',
            'price_per_night' => 1_000_000,
            'capacity'        => 10,
            'rooms'           => 5,
            'is_active'       => true,
        ]);

        $this->roomType = RoomType::create([
            'accommodation_id' => $this->accommodation->id,
            'name'             => 'دو تخته',
            'capacity'         => 2,
            'room_count'       => 5,
            'is_active'        => true,
        ]);

        $this->roomRate = RoomRate::create([
            'room_type_id'    => $this->roomType->id,
            'name'            => 'نرخ پایه',
            'price_per_night' => 1_000_000,
            'is_active'       => true,
        ]);
    }

    public function test_default_child_discount_is_50_percent(): void
    {
        $pricing = $this->calculatePricing([
            'guests'           => 2,
            'children_under_6' => 1,
            'nights'           => 2,
        ]);

        $this->assertSame(50, $pricing['children_under_6_discount_percentage']);
        $this->assertSame(3_000_000, $pricing['room_subtotal']);
        $this->assertSame(1_000_000, $pricing['children_discount_amount']);
        $this->assertSame(3_000_000, $pricing['total_price']);
    }

    public function test_custom_child_discount_percentage(): void
    {
        $this->accommodation->update(['children_under_6_discount_percentage' => 30]);

        $pricing = $this->calculatePricing([
            'guests'           => 2,
            'children_under_6' => 1,
            'nights'           => 2,
        ]);

        $this->assertSame(30, $pricing['children_under_6_discount_percentage']);
        // 1 adult full + 1 child at 70% = 1.7M per night × 2 nights
        $this->assertSame(3_400_000, $pricing['room_subtotal']);
        $this->assertSame(600_000, $pricing['children_discount_amount']);
        $this->assertSame(3_400_000, $pricing['total_price']);
    }

    public function test_zero_child_discount_charges_full_rate(): void
    {
        $this->accommodation->update(['children_under_6_discount_percentage' => 0]);

        $pricing = $this->calculatePricing([
            'guests'           => 2,
            'children_under_6' => 1,
            'nights'           => 1,
        ]);

        $this->assertSame(0, $pricing['children_discount_amount']);
        $this->assertSame(2_000_000, $pricing['total_price']);
    }

    public function test_child_without_bed_does_not_increase_rooms_needed(): void
    {
        $this->accommodation->update(['children_under_6_allocate_bed' => false]);

        $pricing = $this->calculatePricing([
            'guests'           => 3,
            'children_under_6' => 1,
            'room_type'        => $this->roomType,
            'room_rate'        => $this->roomRate,
            'nights'           => 1,
        ]);

        $this->assertSame(1, $pricing['rooms_needed']);
        $this->assertSame(3, $pricing['billing_guests']);
        $this->assertSame(1, $pricing['children_under_6']);
        $this->assertSame(2_500_000, $pricing['total_price']);
    }

    public function test_child_with_bed_increases_rooms_needed(): void
    {
        $this->accommodation->update(['children_under_6_allocate_bed' => true]);

        $pricing = $this->calculatePricing([
            'guests'           => 3,
            'children_under_6' => 1,
            'room_type'        => $this->roomType,
            'room_rate'        => $this->roomRate,
            'nights'           => 1,
        ]);

        $this->assertSame(2, $pricing['rooms_needed']);
        $this->assertSame(2_500_000, $pricing['total_price']);
    }

    public function test_pricing_same_with_or_without_bed_allocation(): void
    {
        $withBed = $this->calculatePricing([
            'guests'           => 3,
            'children_under_6' => 1,
            'room_type'        => $this->roomType,
            'room_rate'        => $this->roomRate,
            'nights'           => 2,
        ]);

        $this->accommodation->update(['children_under_6_allocate_bed' => false]);

        $withoutBed = $this->calculatePricing([
            'guests'           => 3,
            'children_under_6' => 1,
            'room_type'        => $this->roomType,
            'room_rate'        => $this->roomRate,
            'nights'           => 2,
        ]);

        $this->assertSame($withBed['total_price'], $withoutBed['total_price']);
        $this->assertSame($withBed['children_discount_amount'], $withoutBed['children_discount_amount']);
        $this->assertNotSame($withBed['rooms_needed'], $withoutBed['rooms_needed']);
    }

    public function test_child_discount_combines_with_veteran_discount(): void
    {
        $pricing = $this->calculatePricing([
            'veteran_type'     => 'veteran_50_69_dependents',
            'guests'           => 2,
            'children_under_6' => 1,
            'nights'           => 2,
        ]);

        $this->assertSame(1_000_000, $pricing['children_discount_amount']);
        $this->assertSame(1_500_000, $pricing['total_price']);
    }

    public function test_guests_for_bed_allocation_helper(): void
    {
        $service = app(BookingPricingService::class);

        $this->accommodation->update(['children_under_6_allocate_bed' => false]);
        $this->assertSame(2, $service->guestsForBedAllocation(3, 1, $this->accommodation->fresh()));

        $this->accommodation->update(['children_under_6_allocate_bed' => true]);
        $this->assertSame(3, $service->guestsForBedAllocation(3, 1, $this->accommodation->fresh()));
    }

    public function test_manual_booking_persists_child_policy_pricing(): void
    {
        $this->accommodation->update([
            'children_under_6_allocate_bed' => false,
            'children_under_6_discount_percentage' => 40,
        ]);

        $checkIn = now()->addDays(10)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');

        $booking = app(ManualBookingService::class)->create($this->accommodation, [
            'room_lines' => [[
                'room_type_id'     => $this->roomType->id,
                'room_rate_id'     => $this->roomRate->id,
                'adults'           => 2,
                'children_under_6' => 1,
                'guests'           => 3,
                'extra_guests'     => 0,
                'bill_full_rooms'  => false,
            ]],
            'check_in'             => $checkIn,
            'check_out'            => $checkOut,
            'guests'               => 3,
            'children_under_6'     => 1,
            'extra_guests'         => 0,
            'veteran_type'         => null,
            'booker_national_id'   => '1234567890',
            'payment_method'       => 'cash',
            'guest_contact_name'   => 'مهمان تست',
            'guest_contact_mobile' => '09123456789',
            'services'             => [],
            'guest_details'        => [
                ['full_name' => 'مهمان تست', 'national_id' => '1234567890', 'mobile' => '09123456789', 'relation' => 'رزرو‌کننده'],
                ['full_name' => '', 'national_id' => '', 'mobile' => '', 'relation' => ''],
                ['full_name' => '', 'national_id' => '', 'mobile' => '', 'relation' => ''],
            ],
        ], $this->adminUser);

        $this->assertSame(1, $booking->rooms_consumed);
        $this->assertSame(1, $booking->children_under_6);
        $this->assertSame(5_200_000, $booking->total_price);
    }

    private function calculatePricing(array $params): array
    {
        $nights = $params['nights'] ?? 2;
        $checkIn = $params['check_in'] ?? now()->addDays(5)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays($nights)->format('Y-m-d');

        return app(BookingPricingService::class)->calculate([
            'check_in'        => $checkIn,
            'check_out'       => $checkOut,
            'guests'          => $params['guests'] ?? 1,
            'children_under_6'=> $params['children_under_6'] ?? 0,
            'extra_guests'    => $params['extra_guests'] ?? 0,
            'bill_full_rooms' => $params['bill_full_rooms'] ?? false,
            'veteran_type'    => $params['veteran_type'] ?? null,
            'services'        => $params['services'] ?? [],
            'accommodation'   => $this->accommodation->fresh(),
            'room_type'       => $params['room_type'] ?? null,
            'room_rate'       => $params['room_rate'] ?? null,
            'national_id'     => $params['national_id'] ?? null,
            'user_id'         => $params['user_id'] ?? null,
            'non_veteran_discount_guests' => $params['non_veteran_discount_guests'] ?? 0,
            'per_guest_slots'             => $params['per_guest_slots'] ?? null,
        ]);
    }
}
