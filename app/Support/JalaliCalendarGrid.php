<?php

namespace App\Support;

use Morilog\Jalali\Jalalian;

/**
 * Jalali month grids aligned with booking calendar (headers: ش…ج, Saturday-first).
 */
class JalaliCalendarGrid
{
    public const WEEKDAY_HEADERS = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];

    /** PHP date('N'): 1=Mon … 5=Fri, 6=Sat, 7=Sun → column 0=Sat … 6=Fri */
    public static function satFirstColumnForIsoWeekday(int $isoN): int
    {
        return ($isoN + 1) % 7;
    }

    /** Carbon dayOfWeek (0=Sun … 6=Sat) → Sat-first column */
    public static function satFirstColumnForCarbonDow(int $carbonDow): int
    {
        return ($carbonDow + 1) % 7;
    }

    /** persianDate.day() convention: 0=Sat … 6=Fri */
    public static function satFirstColumnForPersianDow(int $persianDow): int
    {
        return $persianDow;
    }

    /**
     * @return array<int, array{
     *   year:int, month:int, label:string, offset:int, days:int,
     *   cells: array<int, array{greg:string, jalali_day:int, iso_weekday:int, column:int}|null>
     * }>
     */
    public static function upcomingMonths(int $count = 3, ?Jalalian $from = null): array
    {
        $from      = $from ?? Jalalian::now();
        $year      = $from->getYear();
        $month     = $from->getMonth();
        $months    = [];

        for ($i = 0; $i < $count; $i++) {
            $m = $month + $i;
            $y = $year;
            while ($m > 12) {
                $m -= 12;
                $y++;
            }
            $months[] = self::monthGrid($y, $m);
        }

        return $months;
    }

    /**
     * @return array{
     *   year:int, month:int, label:string, offset:int, days:int,
     *   cells: array<int, array{greg:string, jalali_day:int, iso_weekday:int, column:int}|null>
     * }
     */
    public static function monthGrid(int $jYear, int $jMonth): array
    {
        $jalali      = Jalalian::fromFormat('Y/m/d', sprintf('%d/%02d/01', $jYear, $jMonth));
        $daysInMonth = $jalali->getMonthDays();
        $firstGreg   = $jalali->toCarbon()->startOfDay();
        $offset      = self::satFirstColumnForCarbonDow((int) $firstGreg->dayOfWeek);

        $cells = [];
        for ($i = 0; $i < $offset; $i++) {
            $cells[] = null;
        }

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dayJ = Jalalian::fromFormat('Y/m/d', sprintf('%d/%02d/%02d', $jYear, $jMonth, $d));
            $greg = $dayJ->toCarbon()->format('Y-m-d');
            $isoN = (int) $dayJ->toCarbon()->format('N');

            $cells[] = [
                'greg'         => $greg,
                'jalali_day'   => $d,
                'iso_weekday'  => $isoN,
                'column'       => self::satFirstColumnForIsoWeekday($isoN),
            ];
        }

        return [
            'year'   => $jYear,
            'month'  => $jMonth,
            'label'  => $jalali->format('F Y'),
            'offset' => $offset,
            'days'   => $daysInMonth,
            'cells'  => $cells,
        ];
    }

    /** @return array<int, string> Gregorian YYYY-MM months needed to load a jalali month in booking UI */
    public static function gregorianMonthsForJalaliMonth(int $jYear, int $jMonth): array
    {
        $nextY = $jYear;
        $nextM = $jMonth + 1;
        if ($nextM > 12) {
            $nextM = 1;
            $nextY++;
        }

        $first = Jalalian::fromFormat('Y/m/d', sprintf('%d/%02d/01', $jYear, $jMonth))
            ->toCarbon()->format('Y-m');
        $second = Jalalian::fromFormat('Y/m/d', sprintf('%d/%02d/01', $nextY, $nextM))
            ->toCarbon()->format('Y-m');

        return array_values(array_unique([$first, $second]));
    }

    /** @return array{0:string, 1:string} [fromGreg, toGregExclusive] */
    public static function gregorianRangeForUpcomingMonths(int $count = 3, ?Jalalian $from = null): array
    {
        $fromGreg = now()->toDateString();
        $months   = self::upcomingMonths($count, $from);
        $lastGreg = $fromGreg;

        foreach ($months as $month) {
            foreach ($month['cells'] as $cell) {
                if ($cell && $cell['greg'] > $lastGreg) {
                    $lastGreg = $cell['greg'];
                }
            }
        }

        $toGreg = (new \DateTime($lastGreg))->modify('+1 day')->format('Y-m-d');

        return [$fromGreg, $toGreg];
    }

    /**
     * Verify each date sits in the weekday column matching its ISO weekday.
     *
     * @return array<int, string> list of error messages (empty = OK)
     */
    public static function validateMonthAlignment(int $jYear, int $jMonth): array
    {
        $grid   = self::monthGrid($jYear, $jMonth);
        $errors = [];
        $col    = 0;

        foreach ($grid['cells'] as $cell) {
            if ($cell === null) {
                $col++;
                continue;
            }

            $positionCol = $col % 7;
            $expectedCol = $cell['column'];

            if ($expectedCol !== $positionCol) {
                $errors[] = sprintf(
                    '%s (ISO dow %d) at visual column %d, expected column %d',
                    $cell['greg'],
                    $cell['iso_weekday'],
                    $positionCol,
                    $expectedCol,
                );
            }
            $col++;
        }

        return $errors;
    }
}
