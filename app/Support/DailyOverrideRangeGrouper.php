<?php

namespace App\Support;

use App\Models\RoomRateDailyPriceOverride;
use App\Models\RoomTypeDailyOverride;
use Illuminate\Support\Collection;

class DailyOverrideRangeGrouper
{
    /**
     * Group consecutive daily overrides with identical settings into date ranges.
     *
     * @param  Collection<int, RoomTypeDailyOverride>  $overrides
     * @param  array<string, Collection<int, RoomRateDailyPriceOverride>>  $rateDailyByDate
     * @return array<int, array{
     *   date_from: string,
     *   date_to: string,
     *   days_count: int,
     *   override_ids: array<int, int>,
     *   override: RoomTypeDailyOverride,
     *   rate_rows: Collection<int, RoomRateDailyPriceOverride>
     * }>
     */
    public static function group(Collection $overrides, array $rateDailyByDate): array
    {
        $sorted = $overrides->sortBy(fn (RoomTypeDailyOverride $o) => $o->date->toDateString())->values();
        $groups = [];
        $current = null;

        foreach ($sorted as $override) {
            $dateStr = $override->date->toDateString();
            $fingerprint = self::fingerprint($override, $rateDailyByDate[$dateStr] ?? collect());

            if ($current === null) {
                $current = self::startGroup($override, $dateStr, $fingerprint, $rateDailyByDate);
                continue;
            }

            $nextExpected = (new \DateTime($current['date_to']))->modify('+1 day')->format('Y-m-d');
            $isConsecutive = $dateStr === $nextExpected;
            $sameSettings = $fingerprint === $current['fingerprint'];

            if ($isConsecutive && $sameSettings) {
                $current['date_to'] = $dateStr;
                $current['override_ids'][] = (int) $override->id;
                $current['days_count']++;
                continue;
            }

            $groups[] = $current;
            $current = self::startGroup($override, $dateStr, $fingerprint, $rateDailyByDate);
        }

        if ($current !== null) {
            $groups[] = $current;
        }

        return array_reverse($groups);
    }

    /**
     * @param  Collection<int, RoomRateDailyPriceOverride>  $rateRows
     */
    private static function fingerprint(RoomTypeDailyOverride $override, Collection $rateRows): string
    {
        $ratePart = $rateRows
            ->sortBy('room_rate_id')
            ->map(fn (RoomRateDailyPriceOverride $row) => implode('|', [
                (int) $row->room_rate_id,
                $row->custom_price ?? '',
                $row->discount_percentage ?? '',
                $row->price_label ?? '',
            ]))
            ->implode(';');

        return implode('::', [
            (int) $override->available_count,
            (string) ($override->reason ?? ''),
            $override->custom_price ?? '',
            $override->discount_percentage ?? '',
            (string) ($override->price_label ?? ''),
            $ratePart,
        ]);
    }

    /**
     * @param  array<string, Collection<int, RoomRateDailyPriceOverride>>  $rateDailyByDate
     * @return array{
     *   date_from: string,
     *   date_to: string,
     *   days_count: int,
     *   override_ids: array<int, int>,
     *   fingerprint: string,
     *   override: RoomTypeDailyOverride,
     *   rate_rows: Collection<int, RoomRateDailyPriceOverride>
     * }
     */
    private static function startGroup(
        RoomTypeDailyOverride $override,
        string $dateStr,
        string $fingerprint,
        array $rateDailyByDate,
    ): array {
        return [
            'date_from'    => $dateStr,
            'date_to'      => $dateStr,
            'days_count'   => 1,
            'override_ids' => [(int) $override->id],
            'fingerprint'  => $fingerprint,
            'override'     => $override,
            'rate_rows'    => $rateDailyByDate[$dateStr] ?? collect(),
        ];
    }
}
