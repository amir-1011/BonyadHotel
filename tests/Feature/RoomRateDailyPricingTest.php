<?php

namespace Tests\Feature;

use App\Livewire\ManualBookingForm;
use App\Models\Accommodation;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\RoomTypeWeeklyPriceRule;
use App\Models\User;
use App\Services\BookingPricingService;
use App\Services\DailyAvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Ensures daily/weekly price maps and booking pricing respect the selected room tariff,
 * not always the cheapest rate on the room type.
 */
class RoomRateDailyPricingTest extends TestCase
{
    use RefreshDatabase;

    private Accommodation $accommodation;
    private RoomType $roomType;
    private RoomRate $rateStandard;
    private RoomRate $ratePremium;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->accommodation = $this->createTestAccommodation();
        $this->roomType = RoomType::create([
            'accommodation_id' => $this->accommodation->id,
            'name'             => 'دو تخته',
            'capacity'         => 2,
            'room_count'       => 4,
            'is_active'        => true,
        ]);

        $this->rateStandard = RoomRate::create([
            'room_type_id'    => $this->roomType->id,
            'name'            => 'تعرفه اول',
            'price_per_night' => 1_000_000,
            'is_active'       => true,
        ]);

        $this->ratePremium = RoomRate::create([
            'room_type_id'    => $this->roomType->id,
            'name'            => 'تعرفه دوم',
            'price_per_night' => 1_500_000,
            'is_active'       => true,
        ]);
    }

    public function test_availability_map_uses_selected_rate_as_base_price(): void
    {
        $date = Carbon::now()->addDays(10)->toDateString();
        $end  = Carbon::parse($date)->addDay()->toDateString();

        $defaultMap = $this->roomType->fresh()->availabilityMap($date, $end);
        $premiumMap = $this->roomType->fresh()->availabilityMap($date, $end, $this->ratePremium);

        $this->assertSame(1_000_000, $defaultMap[$date]['default_price']);
        $this->assertSame(1_000_000, $defaultMap[$date]['base_price']);
        $this->assertSame(1_000_000, $defaultMap[$date]['effective_price']);

        $this->assertSame(1_000_000, $premiumMap[$date]['default_price']);
        $this->assertSame(1_500_000, $premiumMap[$date]['base_price']);
        $this->assertSame(1_500_000, $premiumMap[$date]['effective_price']);
    }

    public function test_weekly_discount_applies_on_selected_rate_not_cheapest_rate(): void
    {
        $tuesday = Carbon::now()->next(Carbon::TUESDAY);
        $dateStr = $tuesday->toDateString();
        $end     = $tuesday->copy()->addDay()->toDateString();

        app(DailyAvailabilityService::class)->store($this->roomType, [
            'is_permanent_weekly' => true,
            'weekdays'            => [2], // Tuesday
            'discount_percentage' => -50,
            'price_label'         => 'سه‌شنبه ویژه',
        ]);

        $defaultMap = $this->roomType->fresh()->availabilityMap($dateStr, $end);
        $premiumMap = $this->roomType->fresh()->availabilityMap($dateStr, $end, $this->ratePremium);

        $this->assertSame(500_000, $defaultMap[$dateStr]['effective_price']);
        $this->assertSame(750_000, $premiumMap[$dateStr]['effective_price']);
        $this->assertSame(-50, $premiumMap[$dateStr]['discount_percentage']);
    }

    public function test_api_availability_accepts_room_rate_id(): void
    {
        $month = Carbon::now()->addMonth()->format('Y-m');

        $defaultResponse = $this->getJson(
            '/api/room-types/' . $this->roomType->id . '/availability?months=' . $month,
        );
        $premiumResponse = $this->getJson(
            '/api/room-types/' . $this->roomType->id . '/availability?months=' . $month
            . '&room_rate_id=' . $this->ratePremium->id,
        );

        $defaultResponse->assertOk();
        $premiumResponse->assertOk();

        $defaultDates = $defaultResponse->json('dates');
        $premiumDates = $premiumResponse->json('dates');

        $this->assertNotEmpty($defaultDates);
        $this->assertNotEmpty($premiumDates);

        $sampleDate = array_key_first($premiumDates);
        $this->assertSame(1_000_000, $defaultDates[$sampleDate]['base_price']);
        $this->assertSame(1_500_000, $premiumDates[$sampleDate]['base_price']);
        $this->assertSame(1_500_000, $premiumDates[$sampleDate]['effective_price']);
    }

    public function test_api_ignores_room_rate_from_other_room_type(): void
    {
        $otherType = RoomType::create([
            'accommodation_id' => $this->accommodation->id,
            'name'             => 'سوئیت',
            'capacity'         => 3,
            'room_count'       => 2,
            'is_active'        => true,
        ]);
        $foreignRate = RoomRate::create([
            'room_type_id'    => $otherType->id,
            'name'            => 'نرخ سوئیت',
            'price_per_night' => 2_000_000,
            'is_active'       => true,
        ]);

        $month = Carbon::now()->addMonth()->format('Y-m');
        $response = $this->getJson(
            '/api/room-types/' . $this->roomType->id . '/availability?months=' . $month
            . '&room_rate_id=' . $foreignRate->id,
        );

        $response->assertOk();
        $sampleDate = array_key_first($response->json('dates'));
        $this->assertSame(1_000_000, $response->json("dates.{$sampleDate}.base_price"));
    }

    public function test_booking_pricing_service_uses_selected_rate_for_nightly_subtotal(): void
    {
        $checkIn  = Carbon::now()->addDays(14)->toDateString();
        $checkOut = Carbon::parse($checkIn)->addDays(2)->toDateString();

        $pricing = app(BookingPricingService::class)->calculate([
            'check_in'      => $checkIn,
            'check_out'     => $checkOut,
            'guests'        => 2,
            'accommodation' => $this->accommodation,
            'room_type'     => $this->roomType,
            'room_rate'     => $this->ratePremium,
            'services'      => [],
        ]);

        $this->assertSame(2, $pricing['nights']);
        $this->assertSame(6_000_000, $pricing['room_subtotal']); // 1_500_000 × 2 guests × 2 nights
    }

    public function test_booking_pricing_with_weekly_rule_on_premium_rate(): void
    {
        $friday = Carbon::now()->next(Carbon::FRIDAY);
        $checkIn  = $friday->toDateString();
        $checkOut = $friday->copy()->addDay()->toDateString();

        RoomTypeWeeklyPriceRule::create([
            'room_type_id'        => $this->roomType->id,
            'weekday'             => 5, // Friday
            'discount_percentage' => -20,
            'price_label'         => 'جمعه ویژه',
        ]);

        $standardPricing = app(BookingPricingService::class)->calculate([
            'check_in'      => $checkIn,
            'check_out'     => $checkOut,
            'guests'        => 1,
            'accommodation' => $this->accommodation,
            'room_type'     => $this->roomType,
            'room_rate'     => $this->rateStandard,
            'services'      => [],
        ]);

        $premiumPricing = app(BookingPricingService::class)->calculate([
            'check_in'      => $checkIn,
            'check_out'     => $checkOut,
            'guests'        => 1,
            'accommodation' => $this->accommodation,
            'room_type'     => $this->roomType,
            'room_rate'     => $this->ratePremium,
            'services'      => [],
        ]);

        $this->assertSame(800_000, $standardPricing['room_subtotal']);
        $this->assertSame(1_200_000, $premiumPricing['room_subtotal']);
    }

    public function test_manual_booking_pricing_preview_uses_second_tariff(): void
    {
        $admin = User::create([
            'name'   => 'ادمین',
            'mobile' => '09001112233',
        ]);
        $admin->assignRole('super_admin');

        $checkIn  = Carbon::now()->addDays(20)->toDateString();
        $checkOut = Carbon::parse($checkIn)->addDays(3)->toDateString();

        $component = Livewire::actingAs($admin)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call(
                'commitRoomFromDrawer',
                $checkIn,
                $checkOut,
                2,
                $this->roomType->id,
                $this->ratePremium->id,
                0,
                false,
                0,
                2,
            );

        $pricing = $component->instance()->pricingPreview;

        $this->assertNotEmpty($pricing);
        $this->assertSame(3, $pricing['nights']);
        $this->assertSame(9_000_000, $pricing['room_subtotal']); // 1_500_000 × 2 guests × 3 nights
    }

    public function test_multi_room_lines_each_keep_their_tariff_pricing(): void
    {
        $checkIn  = Carbon::now()->addDays(25)->toDateString();
        $checkOut = Carbon::parse($checkIn)->addDays(2)->toDateString();

        $pricing = app(BookingPricingService::class)->calculate([
            'check_in'      => $checkIn,
            'check_out'     => $checkOut,
            'guests'        => 4,
            'accommodation' => $this->accommodation,
            'room_lines'    => [
                [
                    'room_type' => $this->roomType,
                    'room_rate' => $this->rateStandard,
                    'guests'    => 2,
                ],
                [
                    'room_type' => $this->roomType,
                    'room_rate' => $this->ratePremium,
                    'guests'    => 2,
                ],
            ],
            'services' => [],
        ]);

        $this->assertSame(2, $pricing['nights']);
        // standard: 1M × 2 guests × 2 nights = 4M; premium: 1.5M × 2 × 2 = 6M
        $this->assertSame(10_000_000, $pricing['room_subtotal']);
    }
}
