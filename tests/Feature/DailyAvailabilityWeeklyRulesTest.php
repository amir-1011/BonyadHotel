<?php

namespace Tests\Feature;

use App\Models\Accommodation;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\RoomTypeDailyOverride;
use App\Models\RoomTypeWeeklyPriceRule;
use App\Models\User;
use App\Services\BookingPricingService;
use App\Services\DailyAvailabilityService;
use App\Support\JalaliCalendarGrid;
use App\Support\RoomTypePriceResolver;
use Carbon\Carbon;
use Database\Seeders\VeteranPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DailyAvailabilityWeeklyRulesTest extends TestCase
{
    use RefreshDatabase;

    private Accommodation $accommodation;
    private RoomType $roomType;
    private User $adminUser;
    private DailyAvailabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VeteranPolicySeeder::class);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->adminUser = User::create([
            'name'   => 'ادمین تست',
            'mobile' => '09000000099',
        ]);
        $this->adminUser->assignRole('super_admin');

        $provinceId = DB::table('provinces')->insertGetId(['name' => 'استان تست', 'created_at' => now(), 'updated_at' => now()]);
        $cityId = DB::table('cities')->insertGetId(['province_id' => $provinceId, 'name' => 'شهر تست', 'created_at' => now(), 'updated_at' => now()]);

        $this->accommodation = Accommodation::create([
            'city_id'         => $cityId,
            'name'            => 'اقامتگاه هفتگی',
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

        RoomRate::create([
            'room_type_id'    => $this->roomType->id,
            'name'            => 'نرخ پایه',
            'price_per_night' => 1_000_000,
            'is_active'       => true,
        ]);

        $this->service = app(DailyAvailabilityService::class);
    }

    public function test_price_resolver_handles_discount_and_surcharge(): void
    {
        $this->assertSame(500_000, RoomTypePriceResolver::effectivePrice(1_000_000, null, -50));
        $this->assertSame(1_200_000, RoomTypePriceResolver::effectivePrice(1_000_000, null, 20));
        $this->assertSame(800_000, RoomTypePriceResolver::effectivePrice(1_000_000, 800_000, 0));
        $this->assertSame(500_000, RoomTypePriceResolver::effectivePrice(1_000_000, 0, -50));
        $this->assertSame(400_000, RoomTypePriceResolver::effectivePrice(1_000_000, 0, -60));
        $this->assertNull(RoomTypePriceResolver::normalizeCustomPrice(''));
        $this->assertNull(RoomTypePriceResolver::normalizeCustomPrice(0));
        $this->assertNull(RoomTypePriceResolver::normalizeCustomPrice('0'));
        $this->assertSame(500_000, RoomTypePriceResolver::normalizeCustomPrice(500_000));
        $this->assertTrue(RoomTypePriceResolver::hasPriceAdjustment(null, 20));
        $this->assertFalse(RoomTypePriceResolver::hasPriceAdjustment(0, 0));
        $this->assertFalse(RoomTypePriceResolver::hasPriceAdjustment(null, 0));
    }

    public function test_permanent_weekly_rule_applies_to_matching_weekdays(): void
    {
        $result = $this->service->store($this->roomType, [
            'is_permanent_weekly' => true,
            'weekdays'            => [2], // Tuesday
            'discount_percentage' => -50,
            'price_label'         => 'سه‌شنبه ویژه',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertDatabaseHas('room_type_weekly_price_rules', [
            'room_type_id'        => $this->roomType->id,
            'weekday'             => 2,
            'discount_percentage' => -50,
            'price_label'         => 'سه‌شنبه ویژه',
        ]);

        $tuesday = $this->nextWeekday(2);
        $wednesday = (clone $tuesday)->modify('+1 day');

        $map = $this->roomType->fresh()->availabilityMap(
            $tuesday->format('Y-m-d'),
            (clone $wednesday)->modify('+1 day')->format('Y-m-d'),
        );

        $tueKey = $tuesday->format('Y-m-d');
        $wedKey = $wednesday->format('Y-m-d');

        $this->assertSame(500_000, $map[$tueKey]['effective_price']);
        $this->assertTrue($map[$tueKey]['has_weekly_rule']);
        $this->assertSame('weekly', $map[$tueKey]['price_source']);
        $this->assertSame(1_000_000, $map[$wedKey]['effective_price']);
        $this->assertFalse($map[$wedKey]['has_weekly_rule']);
    }

    public function test_weekend_surcharge_rule_makes_thursday_and_friday_more_expensive(): void
    {
        $result = $this->service->store($this->roomType, [
            'is_permanent_weekly' => true,
            'weekdays'            => [4, 5], // Thu, Fri
            'discount_percentage' => 20,
            'price_label'         => 'پیک آخر هفته',
        ]);

        $this->assertTrue($result['ok']);

        $thursday = $this->nextWeekday(4);
        $friday = $this->nextWeekday(5);
        $saturday = $this->nextWeekday(6);

        $end = (clone $saturday)->modify('+1 day');
        $map = $this->roomType->fresh()->availabilityMap($thursday->format('Y-m-d'), $end->format('Y-m-d'));

        $this->assertSame(1_200_000, $map[$thursday->format('Y-m-d')]['effective_price']);
        $this->assertSame(1_200_000, $map[$friday->format('Y-m-d')]['effective_price']);
        $this->assertSame(1_000_000, $map[$saturday->format('Y-m-d')]['effective_price']);
    }

    public function test_daily_override_takes_priority_over_weekly_rule(): void
    {
        $tuesday = $this->nextWeekday(2);
        $dateStr = $tuesday->format('Y-m-d');

        RoomTypeWeeklyPriceRule::create([
            'room_type_id'        => $this->roomType->id,
            'weekday'             => 2,
            'discount_percentage' => -50,
        ]);

        RoomTypeDailyOverride::create([
            'room_type_id'        => $this->roomType->id,
            'date'                => $dateStr,
            'available_count'     => 5,
            'discount_percentage' => -10,
        ]);

        $this->assertDatabaseHas('room_type_daily_overrides', [
            'room_type_id'        => $this->roomType->id,
            'discount_percentage' => -10,
        ]);

        $end = (clone $tuesday)->modify('+1 day');
        $map = $this->roomType->fresh()->availabilityMap($dateStr, $end->format('Y-m-d'));

        $this->assertSame(900_000, $map[$dateStr]['effective_price']);
        $this->assertSame('daily', $map[$dateStr]['price_source']);
        $this->assertTrue($map[$dateStr]['has_override']);
        $this->assertFalse($map[$dateStr]['has_weekly_rule']);
    }

    public function test_weekday_filter_applies_only_to_selected_days_in_date_range(): void
    {
        $from = Carbon::today()->copy()->addDays(3);
        $to = $from->copy()->addDays(13);
        $fromJ = Jalalian::fromCarbon($from)->format('Y/m/d');
        $toJ = Jalalian::fromCarbon($to)->format('Y/m/d');

        $result = $this->service->store($this->roomType, [
            'date_from'           => $fromJ,
            'date_to'             => $toJ,
            'available_count'     => 2,
            'weekdays'            => [5], // Friday only
            'discount_percentage' => -30,
        ]);

        $this->assertTrue($result['ok']);
        $this->assertGreaterThan(0, RoomTypeDailyOverride::count());

        $fridayCount = 0;
        $otherCount = 0;
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $exists = RoomTypeDailyOverride::where('room_type_id', $this->roomType->id)
                ->whereDate('date', $cursor->toDateString())
                ->exists();
            if ((int) $cursor->format('N') === 5) {
                $fridayCount += $exists ? 1 : 0;
            } else {
                $otherCount += $exists ? 1 : 0;
            }
            $cursor->addDay();
        }

        $this->assertGreaterThan(0, $fridayCount);
        $this->assertSame(0, $otherCount);
    }

    public function test_booking_pricing_uses_weekly_rule_prices(): void
    {
        RoomTypeWeeklyPriceRule::create([
            'room_type_id'        => $this->roomType->id,
            'weekday'             => 2,
            'discount_percentage' => -50,
        ]);

        $tuesday = $this->nextWeekday(2);
        $checkIn = $tuesday->format('Y-m-d');
        $checkOut = (clone $tuesday)->modify('+1 day')->format('Y-m-d');

        $pricing = app(BookingPricingService::class)->calculate([
            'accommodation' => $this->accommodation,
            'room_type'     => $this->roomType,
            'room_rate'     => $this->roomType->rates()->first(),
            'check_in'      => $checkIn,
            'check_out'     => $checkOut,
            'guests'        => 1,
        ]);

        $this->assertSame(500_000, $pricing['room_subtotal']);
        $this->assertSame(500_000, $pricing['total_price']);
    }

    public function test_admin_can_store_and_delete_weekly_rule_via_http(): void
    {
        $response = $this->actingAs($this->adminUser)->post(
            route('admin.room-types.daily-availability.store', [$this->accommodation, $this->roomType]),
            [
                'is_permanent_weekly' => '1',
                'weekdays'            => [2],
                'discount_percentage' => -50,
                'price_label'         => 'سه‌شنبه',
            ]
        );

        $response->assertRedirect();
        $rule = RoomTypeWeeklyPriceRule::first();
        $this->assertNotNull($rule);

        $delete = $this->actingAs($this->adminUser)->delete(
            route('admin.room-types.weekly-price-rules.destroy', [$this->accommodation, $this->roomType, $rule])
        );

        $delete->assertRedirect();
        $this->assertDatabaseMissing('room_type_weekly_price_rules', ['id' => $rule->id]);
    }

    public function test_permanent_rule_requires_price_adjustment(): void
    {
        $result = $this->service->store($this->roomType, [
            'is_permanent_weekly' => true,
            'weekdays'            => [2],
        ]);

        $this->assertFalse($result['ok']);
        $this->assertArrayHasKey('rate_adjustments', $result['errors']);
    }

    public function test_negative_discount_on_daily_override_increases_price(): void
    {
        $date = Carbon::today()->addDays(5);
        $dateJ = Jalalian::fromCarbon($date)->format('Y/m/d');

        $result = $this->service->store($this->roomType, [
            'date_from'           => $dateJ,
            'date_to'             => $dateJ,
            'available_count'     => 5,
            'discount_percentage' => 20,
        ]);

        $this->assertTrue($result['ok']);

        $end = (clone $date)->modify('+1 day');
        $map = $this->roomType->fresh()->availabilityMap($date->format('Y-m-d'), $end->format('Y-m-d'));

        $this->assertSame(1_200_000, $map[$date->format('Y-m-d')]['effective_price']);
    }

    public function test_discount_only_with_zero_custom_price_uses_base_rate_not_free(): void
    {
        $date = Carbon::today()->copy()->addDays(7);
        $dateJ = Jalalian::fromCarbon($date)->format('Y/m/d');

        // Simulates money-input submitting "0" for an empty custom price field.
        $result = $this->service->store($this->roomType, [
            'date_from'           => $dateJ,
            'date_to'             => $dateJ,
            'available_count'     => 5,
            'custom_price'        => 0,
            'discount_percentage' => -30,
        ]);

        $this->assertTrue($result['ok']);

        $override = RoomTypeDailyOverride::where('room_type_id', $this->roomType->id)
            ->whereDate('date', $date->toDateString())
            ->first();

        $this->assertNotNull($override);
        $this->assertNull($override->custom_price);

        $end = $date->copy()->addDay();
        $map = $this->roomType->fresh()->availabilityMap($date->toDateString(), $end->toDateString());

        $this->assertSame(700_000, $map[$date->toDateString()]['effective_price']);
        $this->assertSame(-30, $map[$date->toDateString()]['discount_percentage']);
    }

    public function test_legacy_zero_custom_price_in_database_still_prices_from_base_rate(): void
    {
        $date = Carbon::today()->copy()->addDays(9);
        $dateStr = $date->toDateString();

        RoomTypeDailyOverride::create([
            'room_type_id'        => $this->roomType->id,
            'date'                => $dateStr,
            'available_count'     => 5,
            'custom_price'        => 0,
            'discount_percentage' => -25,
        ]);

        $end = $date->copy()->addDay();
        $map = $this->roomType->fresh()->availabilityMap($dateStr, $end->toDateString());

        $this->assertSame(750_000, $map[$dateStr]['effective_price']);
    }

    public function test_http_store_with_empty_custom_price_and_discount_keeps_non_zero_price(): void
    {
        $date = Carbon::today()->copy()->addDays(11);
        $dateJ = Jalalian::fromCarbon($date)->format('Y/m/d');

        $response = $this->actingAs($this->adminUser)->post(
            route('admin.room-types.daily-availability.store', [$this->accommodation, $this->roomType]),
            [
                'date_from'           => $dateJ,
                'date_to'             => $dateJ,
                'available_count'     => 5,
                'custom_price'        => 0,
                'discount_percentage' => -40,
            ]
        );

        $response->assertRedirect();

        $end = $date->copy()->addDay();
        $map = $this->roomType->fresh()->availabilityMap($date->toDateString(), $end->toDateString());

        $this->assertSame(600_000, $map[$date->toDateString()]['effective_price']);
    }

    public function test_thursday_friday_weekly_rules_do_not_apply_to_saturday_sunday(): void
    {
        $this->service->store($this->roomType, [
            'is_permanent_weekly' => true,
            'weekdays'            => [4, 5],
            'discount_percentage' => -25,
            'price_label'         => 'آخر هفته',
        ]);

        $thursday = $this->nextWeekday(4);
        $friday   = (clone $thursday)->modify('+1 day');
        $saturday = (clone $thursday)->modify('+2 days');
        $sunday   = (clone $thursday)->modify('+3 days');

        $end = (clone $sunday)->modify('+1 day');
        $map = $this->roomType->fresh()->availabilityMap(
            $thursday->format('Y-m-d'),
            $end->format('Y-m-d'),
        );

        $thuKey = $thursday->format('Y-m-d');
        $friKey = $friday->format('Y-m-d');
        $satKey = $saturday->format('Y-m-d');
        $sunKey = $sunday->format('Y-m-d');

        $this->assertSame(-25, $map[$thuKey]['discount_percentage']);
        $this->assertSame(-25, $map[$friKey]['discount_percentage']);
        $this->assertSame(750_000, $map[$thuKey]['effective_price']);
        $this->assertSame(750_000, $map[$friKey]['effective_price']);

        $this->assertNull($map[$satKey]['discount_percentage']);
        $this->assertNull($map[$sunKey]['discount_percentage']);
        $this->assertSame(1_000_000, $map[$satKey]['effective_price']);
        $this->assertSame(1_000_000, $map[$sunKey]['effective_price']);
        $this->assertFalse($map[$satKey]['has_weekly_rule']);
        $this->assertFalse($map[$sunKey]['has_weekly_rule']);
    }

    public function test_availability_api_returns_discount_on_correct_weekdays_only(): void
    {
        RoomTypeWeeklyPriceRule::create([
            'room_type_id'        => $this->roomType->id,
            'weekday'             => 4,
            'discount_percentage' => -30,
        ]);
        RoomTypeWeeklyPriceRule::create([
            'room_type_id'        => $this->roomType->id,
            'weekday'             => 5,
            'discount_percentage' => -30,
        ]);

        $thursday = $this->nextWeekday(4);
        $month    = $thursday->format('Y-m');

        $response = $this->getJson('/api/room-types/' . $this->roomType->id . '/availability?months=' . $month);
        $response->assertOk();

        $dates = $response->json('dates');

        foreach ($dates as $dateStr => $day) {
            $dow = (int) (new \DateTime($dateStr))->format('N');
            if (in_array($dow, [4, 5], true)) {
                $this->assertSame(-30, $day['discount_percentage'], "Expected discount on {$dateStr} (dow {$dow})");
            } elseif (in_array($dow, [6, 7], true)) {
                $this->assertNull($day['discount_percentage'], "Unexpected discount on {$dateStr} (dow {$dow})");
            }
        }
    }

    public function test_friday_weekly_discount_only_on_jalali_calendar_friday_column(): void
    {
        RoomTypeWeeklyPriceRule::create([
            'room_type_id'        => $this->roomType->id,
            'weekday'             => 5,
            'discount_percentage' => -40,
            'price_label'         => 'جمعه',
        ]);

        [$fromGreg, $toGreg] = JalaliCalendarGrid::gregorianRangeForUpcomingMonths(3);
        $map                 = $this->roomType->fresh()->availabilityMap($fromGreg, $toGreg);
        $today               = now()->toDateString();

        foreach (JalaliCalendarGrid::upcomingMonths(3) as $month) {
            foreach ($month['cells'] as $cell) {
                if (!$cell || $cell['greg'] < $today) {
                    continue;
                }

                $disc = $map[$cell['greg']]['discount_percentage'] ?? null;

                if ($cell['column'] === 6) {
                    $this->assertSame(-40, $disc, "Friday {$cell['greg']} in column ج must have 40% discount");
                } else {
                    $this->assertNull($disc, "Non-Friday {$cell['greg']} in column {$cell['column']} must not have Friday discount");
                }
            }
        }
    }

    public function test_booking_api_months_cover_all_jalali_month_cells(): void
    {
        RoomTypeWeeklyPriceRule::create([
            'room_type_id'        => $this->roomType->id,
            'weekday'             => 5,
            'discount_percentage' => -50,
        ]);

        $today = now()->toDateString();

        foreach (JalaliCalendarGrid::upcomingMonths(3) as $jMonth) {
            $apiMonths = JalaliCalendarGrid::gregorianMonthsForJalaliMonth($jMonth['year'], $jMonth['month']);

            $response = $this->getJson(
                '/api/room-types/' . $this->roomType->id . '/availability?months=' . implode(',', $apiMonths),
            );
            $response->assertOk();
            $dates = $response->json('dates');

            foreach ($jMonth['cells'] as $cell) {
                if (!$cell || $cell['greg'] < $today) {
                    continue;
                }

                $this->assertArrayHasKey(
                    $cell['greg'],
                    $dates,
                    "Booking API must include {$cell['greg']} for jalali month {$jMonth['year']}/{$jMonth['month']}",
                );

                $disc = $dates[$cell['greg']]['discount_percentage'] ?? null;
                if ($cell['column'] === 6) {
                    $this->assertSame(-50, $disc, "Friday {$cell['greg']} must have 50% in API");
                } else {
                    $this->assertNull($disc, "Non-Friday {$cell['greg']} must not have Friday discount in API");
                }
            }
        }
    }

    public function test_daily_availability_page_shows_friday_discount_in_j_column(): void
    {
        RoomTypeWeeklyPriceRule::create([
            'room_type_id'        => $this->roomType->id,
            'weekday'             => 5,
            'discount_percentage' => -35,
            'price_label'         => 'جمعه',
        ]);

        $response = $this->actingAs($this->adminUser)->get(
            route('admin.room-types.daily-availability', [$this->accommodation, $this->roomType]),
        );
        $response->assertOk();

        $html = $response->getContent();
        $this->assertMatchesRegularExpression(
            '#data-weekday-col="6"[\s\S]*?<div class="rate-pill-row">[\s\S]*?<span class="rate-pill is-disc">[0-9۰-۹٪%↑]+</span>#u',
            $html,
            'Expected a Friday (column ج) cell with per-rate discount pills',
        );
        $this->assertMatchesRegularExpression(
            '/data-weekday-col="6"[\s\S]*?(?:<div class="weekly-badge">هفتگی<\/div>|<span class="day-cal-tip__weekly">قانون هفتگی دائمی<\/span>)/u',
            $html,
            'Expected a Friday cell marked as weekly rule',
        );
    }

    private function nextWeekday(int $isoWeekday): \DateTime
    {
        $cursor = new \DateTime('today');
        for ($i = 0; $i < 14; $i++) {
            if ((int) $cursor->format('N') === $isoWeekday) {
                return clone $cursor;
            }
            $cursor->modify('+1 day');
        }

        $this->fail('Could not find weekday ' . $isoWeekday);
    }
}
