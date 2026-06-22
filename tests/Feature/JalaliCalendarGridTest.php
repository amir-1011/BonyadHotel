<?php

namespace Tests\Feature;

use App\Models\RoomTypeWeeklyPriceRule;
use App\Support\JalaliCalendarGrid;
use Morilog\Jalali\Jalalian;
use Tests\TestCase;

class JalaliCalendarGridTest extends TestCase
{
    public function test_iso_weekday_maps_to_sat_first_columns(): void
    {
        $this->assertSame(0, JalaliCalendarGrid::satFirstColumnForIsoWeekday(6)); // Sat
        $this->assertSame(1, JalaliCalendarGrid::satFirstColumnForIsoWeekday(7)); // Sun
        $this->assertSame(5, JalaliCalendarGrid::satFirstColumnForIsoWeekday(4)); // Thu
        $this->assertSame(6, JalaliCalendarGrid::satFirstColumnForIsoWeekday(5)); // Fri
    }

    public function test_persian_dow_matches_iso_column_mapping(): void
    {
        foreach ([6, 7, 1, 2, 3, 4, 5] as $iso) {
            $persian = match ($iso) {
                6 => 0, 7 => 1, 1 => 2, 2 => 3, 3 => 4, 4 => 5, 5 => 6,
            };
            $this->assertSame(
                JalaliCalendarGrid::satFirstColumnForIsoWeekday($iso),
                JalaliCalendarGrid::satFirstColumnForPersianDow($persian),
                "ISO {$iso} should match persian dow {$persian}",
            );
        }
    }

    public function test_multiple_jalali_months_have_no_weekday_misalignment(): void
    {
        for ($m = 1; $m <= 12; $m++) {
            $errors = JalaliCalendarGrid::validateMonthAlignment(1404, $m);
            $this->assertSame([], $errors, "Month 1404/{$m} misaligned: " . implode('; ', $errors));
        }
    }

    public function test_friday_cells_are_in_j_column_for_upcoming_months(): void
    {
        foreach (JalaliCalendarGrid::upcomingMonths(3) as $month) {
            foreach ($month['cells'] as $cell) {
                if (!$cell) {
                    continue;
                }
                if ($cell['iso_weekday'] === 5) {
                    $this->assertSame(6, $cell['column'], "Friday {$cell['greg']} must be column 6 (ج)");
                }
                if ($cell['column'] === 6) {
                    $this->assertSame(5, $cell['iso_weekday'], "Column ج must be Friday for {$cell['greg']}");
                }
            }
        }
    }

    public function test_jalali_month_grid_uses_jalali_boundaries_not_gregorian(): void
    {
        $now   = Jalalian::now();
        $jGrid = JalaliCalendarGrid::monthGrid($now->getYear(), $now->getMonth());

        $this->assertSame($now->format('F Y'), $jGrid['label']);

        $jDays = collect($jGrid['cells'])->filter()->pluck('jalali_day')->unique()->sort()->values()->all();
        $this->assertSame(1, $jDays[0]);
        $this->assertSame($jGrid['days'], end($jDays));
    }
}
