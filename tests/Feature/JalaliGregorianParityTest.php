<?php

namespace Tests\Feature;

use App\Support\JalaliCalendarGrid;
use Morilog\Jalali\Jalalian;
use Tests\TestCase;

/**
 * Ensures PHP Jalali grid uses the same gregorian dates as the booking calendar
 * (persianDate + noon local convention). Reference values from persian-date library.
 */
class JalaliGregorianParityTest extends TestCase
{
    /** @var array<string, string> jalali Y/m/d => gregorian Y-m-d (verified against persian-date) */
    private const REFERENCE_MAP = [
        '1404/01/01' => '2025-03-21',
        '1404/01/31' => '2025-04-20',
        '1404/04/01' => '2025-06-22',
        '1405/04/01' => '2026-06-22', // Monday — manual booking offset regression
        '1404/04/13' => '2025-07-04', // Friday
        '1404/04/20' => '2025-07-11', // Friday
        '1404/12/29' => '2026-03-20',
        '1403/12/30' => '2025-03-20', // leap year end
    ];

    public function test_morilog_matches_reference_gregorian_dates(): void
    {
        foreach (self::REFERENCE_MAP as $jalali => $expectedGreg) {
            [$y, $m, $d] = array_map('intval', explode('/', $jalali));
            $greg = Jalalian::fromFormat('Y/m/d', $jalali)->toCarbon()->format('Y-m-d');

            $this->assertSame(
                $expectedGreg,
                $greg,
                "Morilog conversion for {$jalali} should match persian-date reference",
            );
        }
    }

    public function test_calendar_grid_cells_use_same_gregorian_as_morilog(): void
    {
        foreach (JalaliCalendarGrid::upcomingMonths(6) as $month) {
            foreach ($month['cells'] as $cell) {
                if (!$cell) {
                    continue;
                }

                $expected = Jalalian::fromFormat(
                    'Y/m/d',
                    sprintf('%d/%02d/%02d', $month['year'], $month['month'], $cell['jalali_day']),
                )->toCarbon()->format('Y-m-d');

                $this->assertSame(
                    $expected,
                    $cell['greg'],
                    sprintf('Grid cell %d/%02d/%02d greg mismatch', $month['year'], $month['month'], $cell['jalali_day']),
                );
            }
        }
    }

    public function test_friday_iso_weekday_matches_gregorian_date_in_grid(): void
    {
        foreach (JalaliCalendarGrid::upcomingMonths(6) as $month) {
            foreach ($month['cells'] as $cell) {
                if (!$cell) {
                    continue;
                }

                $isoFromGreg = (int) (new \DateTime($cell['greg']))->format('N');

                $this->assertSame(
                    $cell['iso_weekday'],
                    $isoFromGreg,
                    "ISO weekday mismatch for {$cell['greg']}",
                );

                if ($isoFromGreg === 5) {
                    $this->assertSame(6, $cell['column'], "Friday {$cell['greg']} must be in column ج");
                }
            }
        }
    }

    public function test_month_start_offset_matches_js_formula_for_monday_first_tir(): void
    {
        $grid = JalaliCalendarGrid::monthGrid(1405, 4);
        $cell = collect($grid['cells'])->first(fn ($c) => $c && $c['jalali_day'] === 1);

        $this->assertNotNull($cell);
        $this->assertSame(1, $cell['iso_weekday'], '1405/04/01 must be Monday');
        $this->assertSame(2, $cell['column'], 'Monday must appear under column د');
        $this->assertSame(2, $grid['offset']);

        $jsDow = (int) (new \DateTime($cell['greg'] . ' 12:00:00'))->format('w');
        $this->assertSame($grid['offset'], ($jsDow + 1) % 7, 'JS monthStartOffset formula must match PHP grid');
    }
}
