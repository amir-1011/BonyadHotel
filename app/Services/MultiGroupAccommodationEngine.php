<?php

namespace App\Services;

/**
 * Combines veteran accommodation discounts from up to two groups.
 *
 * Each group maintains its own period night ladder; for every night the engine
 * picks whichever group offers the higher discount amount. Ties go to the
 * higher-priority group.
 */
class MultiGroupAccommodationEngine
{
    /**
     * @param  array<int, array{
     *   key: string,
     *   accommodation_discount: int,
     *   priority?: int,
     *   use_tiered_accommodation_discount?: bool,
     *   accommodation_discount_tiers?: array<int, array<string, mixed>>,
     *   used_in_period?: int,
     *   remaining_period: int,
     *   remaining_total: int
     * }>  $groups
     * @return array{
     *   night_discounts: array<int, int>,
     *   night_tiers: array<int, array<string, mixed>>,
     *   night_group_keys: array<int, string|null>,
     *   group_usage: array<string, int>
     * }
     */
    public static function allocateNights(int $requestedNights, array $groups, int $referenceNightPrice = 0): array
    {
        $requestedNights = max(0, $requestedNights);
        $groups = self::normalizeGroups($groups);
        $nightDiscounts = array_fill(0, $requestedNights, 0);
        $nightTiers = array_fill(0, $requestedNights, AccommodationDiscountTierEngine::tierForNightIndex(0, []));
        $nightGroupKeys = array_fill(0, $requestedNights, null);
        $groupUsage = [];
        $bookingConsumed = [];

        for ($nightIndex = 0; $nightIndex < $requestedNights; $nightIndex++) {
            $winner = self::pickBestGroupForNight($groups, $bookingConsumed, $referenceNightPrice);
            if ($winner === null) {
                continue;
            }

            $key = $winner['group_key'];
            $nightDiscounts[$nightIndex] = $winner['discount_percentage'];
            $nightTiers[$nightIndex] = $winner['tier'];
            $nightGroupKeys[$nightIndex] = $key;
            $bookingConsumed[$key] = ($bookingConsumed[$key] ?? 0) + 1;
            $groupUsage[$key] = ($groupUsage[$key] ?? 0) + 1;
        }

        return [
            'night_discounts'  => $nightDiscounts,
            'night_tiers'      => $nightTiers,
            'night_group_keys' => $nightGroupKeys,
            'group_usage'      => $groupUsage,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     * @param  array<string, int>  $bookingConsumed
     * @return array{group_key: string, discount_percentage: int, tier: array<string, mixed>}|null
     */
    private static function pickBestGroupForNight(
        array $groups,
        array $bookingConsumed,
        int $referenceNightPrice,
    ): ?array {
        $bestScore = -1;
        $bestPct = -1;
        $bestKey = null;
        $bestTier = AccommodationDiscountTierEngine::tierForNightIndex(0, []);

        foreach ($groups as $group) {
            $key = (string) ($group['key'] ?? '');
            if ($key === '' || !self::groupHasDiscount($group)) {
                continue;
            }

            if (!self::groupHasRemainingQuota($group, $bookingConsumed)) {
                continue;
            }

            $periodNightIndex = (int) ($group['used_in_period'] ?? 0)
                + (int) ($bookingConsumed[$key] ?? 0)
                + 1;
            $tier = self::tierForGroupAtNightIndex($group, $periodNightIndex);
            if (!AccommodationDiscountTierEngine::tierHasDiscount($tier)) {
                continue;
            }

            $pct = AccommodationDiscountTierEngine::effectivePercentage($referenceNightPrice, $tier);
            $score = $referenceNightPrice > 0
                ? AccommodationDiscountTierEngine::unitDiscount($referenceNightPrice, $tier)
                : $pct;

            if ($score > $bestScore || ($score === $bestScore && $pct > $bestPct)) {
                $bestScore = $score;
                $bestPct = $pct;
                $bestKey = $key;
                $bestTier = $tier;
            }
        }

        if ($bestKey === null || $bestScore <= 0) {
            return null;
        }

        return [
            'group_key'             => $bestKey,
            'discount_percentage'   => $bestPct,
            'tier'                  => $bestTier,
        ];
    }

    /**
     * @param  array<string, mixed>  $group
     */
    private static function groupHasDiscount(array $group): bool
    {
        $useTiered = (bool) ($group['use_tiered_accommodation_discount'] ?? false);
        $tiers = $group['accommodation_discount_tiers'] ?? [];

        if ($useTiered) {
            return !empty(AccommodationDiscountTierEngine::normalizeTiers($tiers));
        }

        return (int) ($group['accommodation_discount'] ?? 0) > 0;
    }

    /**
     * @param  array<string, mixed>  $group
     * @param  array<string, int>  $bookingConsumed
     */
    private static function groupHasRemainingQuota(array $group, array $bookingConsumed): bool
    {
        $key = (string) ($group['key'] ?? '');
        $remainingPeriod = max(0, (int) ($group['remaining_period'] ?? 0));
        $remainingTotal = max(0, (int) ($group['remaining_total'] ?? 0));
        $consumed = (int) ($bookingConsumed[$key] ?? 0);

        return $consumed < min($remainingPeriod, $remainingTotal);
    }

    /**
     * @param  array<string, mixed>  $group
     * @return array<string, mixed>
     */
    private static function tierForGroupAtNightIndex(array $group, int $periodNightIndex): array
    {
        if (!empty($group['use_tiered_accommodation_discount'])) {
            return AccommodationDiscountTierEngine::tierForNightIndex(
                $periodNightIndex,
                $group['accommodation_discount_tiers'] ?? [],
            );
        }

        return [
            'type'                => AccommodationDiscountTierEngine::TYPE_PERCENTAGE,
            'discount_percentage' => max(0, min(100, (int) ($group['accommodation_discount'] ?? 0))),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeGroups(array $groups): array
    {
        return collect($groups)
            ->filter(fn (array $group) => !empty($group['key']))
            ->sortByDesc(fn (array $group) => (int) ($group['priority'] ?? $group['accommodation_discount'] ?? 0))
            ->values()
            ->all();
    }
}
