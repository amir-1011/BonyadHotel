<?php

namespace Tests\Feature;

use App\Models\Accommodation;
use App\Models\RoomRate;
use App\Models\RoomRateDailyPriceOverride;
use App\Models\RoomRateWeeklyPriceRule;
use App\Models\RoomType;
use App\Services\BookingPricingService;
use App\Services\DailyAvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerRateDailyPricingTest extends TestCase
{
    use RefreshDatabase;

    private Accommodation $accommodation;
    private RoomType $roomType;
    private RoomRate $rateStandard;
    private RoomRate $ratePremium;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_daily_override_can_set_different_discounts_per_rate(): void
    {
        $date = Carbon::now()->addDays(12);
        $dateJ = \Morilog\Jalali\Jalalian::fromCarbon($date)->format('Y/m/d');

        $result = app(DailyAvailabilityService::class)->store($this->roomType, [
            'date_from'       => $dateJ,
            'date_to'         => $dateJ,
            'available_count' => 4,
            'rate_adjustments' => [
                $this->rateStandard->id => ['discount_percentage' => -50, 'price_label' => 'ویژه استاندارد'],
                $this->ratePremium->id  => ['discount_percentage' => -10, 'price_label' => 'ویژه پریمیوم'],
            ],
        ]);

        $this->assertTrue($result['ok']);

        $dateStr = $date->toDateString();
        $end = $date->copy()->addDay()->toDateString();

        $standardMap = $this->roomType->fresh()->availabilityMap($dateStr, $end, $this->rateStandard);
        $premiumMap  = $this->roomType->fresh()->availabilityMap($dateStr, $end, $this->ratePremium);

        $this->assertSame(500_000, $standardMap[$dateStr]['effective_price']);
        $this->assertSame(-50, $standardMap[$dateStr]['discount_percentage']);
        $this->assertSame('ویژه استاندارد', $standardMap[$dateStr]['price_label']);
        $this->assertSame('rate_daily', $standardMap[$dateStr]['price_source']);

        $this->assertSame(1_350_000, $premiumMap[$dateStr]['effective_price']);
        $this->assertSame(-10, $premiumMap[$dateStr]['discount_percentage']);
        $this->assertSame('ویژه پریمیوم', $premiumMap[$dateStr]['price_label']);
    }

    public function test_permanent_weekly_rules_can_differ_per_rate(): void
    {
        $result = app(DailyAvailabilityService::class)->store($this->roomType, [
            'is_permanent_weekly' => true,
            'weekdays'            => [5],
            'rate_adjustments'    => [
                $this->rateStandard->id => ['discount_percentage' => -30],
                $this->ratePremium->id  => ['discount_percentage' => 10],
            ],
        ]);

        $this->assertTrue($result['ok']);

        $friday = Carbon::now()->next(Carbon::FRIDAY);
        $dateStr = $friday->toDateString();
        $end = $friday->copy()->addDay()->toDateString();

        $standardMap = $this->roomType->fresh()->availabilityMap($dateStr, $end, $this->rateStandard);
        $premiumMap  = $this->roomType->fresh()->availabilityMap($dateStr, $end, $this->ratePremium);

        $this->assertSame(700_000, $standardMap[$dateStr]['effective_price']);
        $this->assertSame(1_650_000, $premiumMap[$dateStr]['effective_price']);
        $this->assertDatabaseHas('room_rate_weekly_price_rules', [
            'room_rate_id'        => $this->rateStandard->id,
            'weekday'             => 5,
            'discount_percentage' => -30,
        ]);
        $this->assertDatabaseHas('room_rate_weekly_price_rules', [
            'room_rate_id'        => $this->ratePremium->id,
            'weekday'             => 5,
            'discount_percentage' => 10,
        ]);
    }

    public function test_booking_pricing_uses_per_rate_daily_override(): void
    {
        $date = Carbon::now()->addDays(18);
        $dateStr = $date->toDateString();
        $checkOut = $date->copy()->addDay()->toDateString();

        RoomRateDailyPriceOverride::create([
            'room_rate_id'        => $this->ratePremium->id,
            'date'                => $dateStr,
            'discount_percentage' => -20,
        ]);

        $pricing = app(BookingPricingService::class)->calculate([
            'check_in'      => $dateStr,
            'check_out'     => $checkOut,
            'guests'        => 1,
            'accommodation' => $this->accommodation,
            'room_type'     => $this->roomType,
            'room_rate'     => $this->ratePremium,
            'services'      => [],
        ]);

        $this->assertSame(1_200_000, $pricing['room_subtotal']);
    }

    public function test_apply_to_all_rates_uses_unified_discount_for_every_rate(): void
    {
        $date = Carbon::now()->addDays(14);
        $dateJ = \Morilog\Jalali\Jalalian::fromCarbon($date)->format('Y/m/d');

        $result = app(DailyAvailabilityService::class)->store($this->roomType, [
            'date_from'          => $dateJ,
            'date_to'            => $dateJ,
            'available_count'    => 4,
            'apply_to_all_rates' => true,
            'discount_percentage'=> -25,
            'price_label'        => 'پیک مشترک',
        ]);

        $this->assertTrue($result['ok']);

        $dateStr = $date->toDateString();
        $end = $date->copy()->addDay()->toDateString();

        $standardMap = $this->roomType->fresh()->availabilityMap($dateStr, $end, $this->rateStandard);
        $premiumMap  = $this->roomType->fresh()->availabilityMap($dateStr, $end, $this->ratePremium);

        $this->assertSame(750_000, $standardMap[$dateStr]['effective_price']);
        $this->assertSame(-25, $standardMap[$dateStr]['discount_percentage']);
        $this->assertSame('پیک مشترک', $standardMap[$dateStr]['price_label']);

        $this->assertSame(1_125_000, $premiumMap[$dateStr]['effective_price']);
        $this->assertSame(-25, $premiumMap[$dateStr]['discount_percentage']);
        $this->assertSame('پیک مشترک', $premiumMap[$dateStr]['price_label']);

        $this->assertDatabaseMissing('room_rate_daily_price_overrides', [
            'room_rate_id' => $this->rateStandard->id,
            'date'         => $dateStr,
        ]);
        $this->assertDatabaseMissing('room_rate_daily_price_overrides', [
            'room_rate_id' => $this->ratePremium->id,
            'date'         => $dateStr,
        ]);
        $this->assertDatabaseHas('room_type_daily_overrides', [
            'room_type_id'        => $this->roomType->id,
            'discount_percentage' => -25,
            'price_label'         => 'پیک مشترک',
        ]);
    }

    public function test_apply_to_all_rates_works_for_permanent_weekly_rules(): void
    {
        $result = app(DailyAvailabilityService::class)->store($this->roomType, [
            'is_permanent_weekly' => true,
            'weekdays'            => [6],
            'apply_to_all_rates'  => true,
            'discount_percentage' => 15,
            'price_label'         => 'جمعه ویژه',
        ]);

        $this->assertTrue($result['ok']);

        $this->assertDatabaseHas('room_type_weekly_price_rules', [
            'room_type_id'        => $this->roomType->id,
            'weekday'             => 6,
            'discount_percentage' => 15,
            'price_label'         => 'جمعه ویژه',
        ]);
        $this->assertDatabaseMissing('room_rate_weekly_price_rules', [
            'room_rate_id' => $this->rateStandard->id,
            'weekday'      => 6,
        ]);
    }

    public function test_per_rate_mode_requires_explicit_flag_when_both_fields_sent(): void
    {
        $date = Carbon::now()->addDays(16);
        $dateJ = \Morilog\Jalali\Jalalian::fromCarbon($date)->format('Y/m/d');

        $result = app(DailyAvailabilityService::class)->store($this->roomType, [
            'date_from'          => $dateJ,
            'date_to'            => $dateJ,
            'available_count'    => 4,
            'apply_to_all_rates' => false,
            'discount_percentage'=> 99,
            'rate_adjustments'   => [
                $this->rateStandard->id => ['discount_percentage' => -10],
                $this->ratePremium->id  => ['discount_percentage' => -20],
            ],
        ]);

        $this->assertTrue($result['ok']);

        $dateStr = $date->toDateString();
        $end = $date->copy()->addDay()->toDateString();

        $standardMap = $this->roomType->fresh()->availabilityMap($dateStr, $end, $this->rateStandard);
        $premiumMap  = $this->roomType->fresh()->availabilityMap($dateStr, $end, $this->ratePremium);

        $this->assertSame(-10, $standardMap[$dateStr]['discount_percentage']);
        $this->assertSame(-20, $premiumMap[$dateStr]['discount_percentage']);
    }
}
