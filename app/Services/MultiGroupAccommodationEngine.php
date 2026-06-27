<?php

namespace App\Services;

/**
 * Allocates accommodation discount nights across up to two veteran groups.
 *
 * Higher accommodation-discount groups consume their period/total quota first;
 * remaining nights may use the next group's quota at that group's discount rate.
 */
class MultiGroupAccommodationEngine
{
    /**
     * @param  array<int, array{
     *   key: string,
     *   accommodation_discount: int,
     *   remaining_period: int,
     *   remaining_total: int
     * }>  $groups  Sorted by accommodation_discount descending
     * @return array{
     *   night_discounts: array<int, int>,
     *   night_group_keys: array<int, string|null>,
     *   group_usage: array<string, int>
     * }
     */
    public static function allocateNights(int $requestedNights, array $groups): array
    {
        $requestedNights = max(0, $requestedNights);
        $nightDiscounts = array_fill(0, $requestedNights, 0);
        $nightGroupKeys = array_fill(0, $requestedNights, null);
        $groupUsage = [];
        $nightIndex = 0;

        foreach ($groups as $group) {
            if ($nightIndex >= $requestedNights) {
                break;
            }

            $key = (string) ($group['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $pct = max(0, min(100, (int) ($group['accommodation_discount'] ?? 0)));
            if ($pct <= 0) {
                continue;
            }

            $cap = min(
                max(0, (int) ($group['remaining_period'] ?? 0)),
                max(0, (int) ($group['remaining_total'] ?? 0)),
            );

            $allocate = min($requestedNights - $nightIndex, $cap);
            for ($i = 0; $i < $allocate; $i++) {
                $nightDiscounts[$nightIndex] = $pct;
                $nightGroupKeys[$nightIndex] = $key;
                $nightIndex++;
            }

            if ($allocate > 0) {
                $groupUsage[$key] = ($groupUsage[$key] ?? 0) + $allocate;
            }
        }

        return [
            'night_discounts'  => $nightDiscounts,
            'night_group_keys' => $nightGroupKeys,
            'group_usage'      => $groupUsage,
        ];
    }
}
