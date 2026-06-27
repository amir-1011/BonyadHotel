<?php

namespace App\Services;

/**
 * Combines veteran service discounts from up to two groups.
 *
 * Each group maintains its own session ladder; for every unit the engine picks
 * whichever group offers the higher discount. Ties go to the higher-priority group.
 */
class MultiGroupServiceDiscountEngine
{
    /**
     * @param  array<int, array<string, mixed>>  $groups  Sorted by priority (highest first)
     * @param  array<string, int>  $weeklyConsumedPerGroup
     * @param  array<string, int>  $bookingConsumedPerGroup
     * @return array{
     *   discount_amount: int,
     *   free_units: int,
     *   effective_discount_percentage: int,
     *   discount_breakdown: array<int, array<string, mixed>>,
     *   group_usage: array<string, int>
     * }
     */
    public static function calculateLine(
        int $unitPrice,
        int $quantity,
        array $groups,
        array $weeklyConsumedPerGroup,
        array $bookingConsumedPerGroup,
    ): array {
        $unitPrice = max(0, $unitPrice);
        $quantity = max(0, $quantity);
        $groups = self::normalizeGroups($groups);

        if ($quantity === 0 || empty($groups)) {
            return self::emptyResult($bookingConsumedPerGroup);
        }

        if (count($groups) === 1) {
            $group = $groups[0];
            $key = (string) ($group['key'] ?? '');

            if (!empty($group['use_tiered_discount']) && !empty(self::tiersForGroup($group))) {
                $result = ServiceDiscountTierEngine::calculateLine(
                    $unitPrice,
                    $quantity,
                    (int) ($weeklyConsumedPerGroup[$key] ?? 0),
                    (int) ($bookingConsumedPerGroup[$key] ?? 0),
                    self::tiersForGroup($group),
                );

                $usage = $bookingConsumedPerGroup;
                $usage[$key] = ($usage[$key] ?? 0) + $quantity;

                return [
                    'discount_amount'               => $result['discount_amount'],
                    'free_units'                    => $result['free_units'],
                    'effective_discount_percentage' => $result['effective_discount_percentage'],
                    'discount_breakdown'            => $result['discount_breakdown'],
                    'group_usage'                   => $usage,
                ];
            }

            return self::calculateLegacySingleGroup(
                $unitPrice,
                $quantity,
                $group,
                $weeklyConsumedPerGroup,
                $bookingConsumedPerGroup,
            );
        }

        $discountAmount = 0;
        $freeUnits = 0;
        $rawBreakdown = [];
        $usage = $bookingConsumedPerGroup;

        for ($i = 0; $i < $quantity; $i++) {
            $winner = self::pickBestGroupForUnit($unitPrice, $groups, $weeklyConsumedPerGroup, $usage);
            $discountAmount += $winner['discount_amount'];
            if (($winner['tier']['type'] ?? '') === ServiceDiscountTierEngine::TYPE_FREE) {
                $freeUnits++;
            }

            $rawBreakdown[] = array_merge($winner['tier'], [
                'discount_amount' => $winner['discount_amount'],
                'veteran_group_key' => $winner['group_key'],
            ]);

            $usage[$winner['group_key']] = ($usage[$winner['group_key']] ?? 0) + 1;
        }

        $lineSubtotal = $quantity * $unitPrice;

        return [
            'discount_amount'               => $discountAmount,
            'free_units'                    => $freeUnits,
            'effective_discount_percentage' => $lineSubtotal > 0
                ? (int) round($discountAmount / $lineSubtotal * 100)
                : 0,
            'discount_breakdown'            => self::collapseBreakdown($rawBreakdown),
            'group_usage'                   => $usage,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeGroups(array $groups): array
    {
        return collect($groups)
            ->filter(fn ($g) => !empty($g['key']))
            ->sortByDesc(fn ($g) => (int) ($g['priority'] ?? 0))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $group
     * @param  array<string, int>  $weeklyConsumedPerGroup
     * @param  array<string, int>  $bookingConsumedPerGroup
     * @return array{
     *   discount_amount: int,
     *   free_units: int,
     *   effective_discount_percentage: int,
     *   discount_breakdown: array<int, array<string, mixed>>,
     *   group_usage: array<string, int>
     * }
     */
    private static function calculateLegacySingleGroup(
        int $unitPrice,
        int $quantity,
        array $group,
        array $weeklyConsumedPerGroup,
        array $bookingConsumedPerGroup,
    ): array {
        $key = (string) $group['key'];
        $weeklyFree = (int) ($group['weekly_free_sessions'] ?? 0);
        $freeEligible = (bool) ($group['free_sessions_eligible'] ?? false);
        $discountPct = (int) ($group['discount_percentage'] ?? 0);

        $alreadyFreeThisWeek = (int) ($weeklyConsumedPerGroup[$key] ?? 0);
        $alreadyInBooking = (int) ($bookingConsumedPerGroup[$key] ?? 0);
        $remainingFree = ($freeEligible && $weeklyFree > 0)
            ? max(0, $weeklyFree - $alreadyFreeThisWeek - $alreadyInBooking)
            : 0;

        $freeUnits = min($quantity, $remainingFree);
        $legacy = ServiceDiscountTierEngine::legacyLineBreakdown($unitPrice, $quantity, $freeUnits, $discountPct);

        $usage = $bookingConsumedPerGroup;
        $usage[$key] = ($usage[$key] ?? 0) + $quantity;

        return [
            'discount_amount'               => $legacy['discount_amount'],
            'free_units'                    => $legacy['free_units'],
            'effective_discount_percentage' => $legacy['effective_discount_percentage'],
            'discount_breakdown'            => $legacy['discount_breakdown'],
            'group_usage'                   => $usage,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     * @param  array<string, int>  $weeklyConsumedPerGroup
     * @param  array<string, int>  $bookingConsumedPerGroup
     * @return array{group_key:string, tier:array<string, mixed>, discount_amount:int}
     */
    private static function pickBestGroupForUnit(
        int $unitPrice,
        array $groups,
        array $weeklyConsumedPerGroup,
        array $bookingConsumedPerGroup,
    ): array {
        $bestDiscount = -1;
        $bestGroupKey = (string) ($groups[0]['key'] ?? '');
        $bestTier = ['type' => ServiceDiscountTierEngine::TYPE_PERCENTAGE, 'discount_percentage' => 0];

        foreach ($groups as $group) {
            $key = (string) ($group['key'] ?? '');
            $sessionIndex = (int) ($weeklyConsumedPerGroup[$key] ?? 0)
                + (int) ($bookingConsumedPerGroup[$key] ?? 0)
                + 1;

            $tier = self::tierForGroupAtIndex($group, $sessionIndex);
            $discount = ServiceDiscountTierEngine::unitDiscount($unitPrice, $tier);

            if ($discount > $bestDiscount) {
                $bestDiscount = $discount;
                $bestGroupKey = $key;
                $bestTier = $tier;
            }
        }

        return [
            'group_key'       => $bestGroupKey,
            'tier'            => $bestTier,
            'discount_amount' => max(0, $bestDiscount),
        ];
    }

    /**
     * @param  array<string, mixed>  $group
     * @return array<int, array<string, mixed>>
     */
    private static function tiersForGroup(array $group): array
    {
        $tiers = $group['tiers'] ?? $group['discount_tiers'] ?? [];

        return is_array($tiers) ? $tiers : [];
    }

    /**
     * @param  array<string, mixed>  $group
     * @return array<string, mixed>
     */
    private static function tierForGroupAtIndex(array $group, int $sessionIndex): array
    {
        $tiers = self::tiersForGroup($group);

        if (!empty($group['use_tiered_discount']) && !empty($tiers)) {
            return ServiceDiscountTierEngine::tierForSessionIndex(
                $sessionIndex,
                $tiers,
            );
        }

        $legacyTiers = ServiceDiscountTierEngine::tiersFromLegacyRule([
            'free_sessions_eligible' => (bool) ($group['free_sessions_eligible'] ?? false),
            'weekly_free_sessions'   => (int) ($group['weekly_free_sessions'] ?? 0),
            'discount_percentage'    => (int) ($group['discount_percentage'] ?? 0),
        ]);

        return ServiceDiscountTierEngine::tierForSessionIndex($sessionIndex, $legacyTiers);
    }

    /**
     * @param  array<int, array<string, mixed>>  $raw
     * @return array<int, array<string, mixed>>
     */
    private static function collapseBreakdown(array $raw): array
    {
        $grouped = [];

        foreach ($raw as $item) {
            $key = ($item['type'] ?? '')
                . '|' . ($item['pay_amount'] ?? '')
                . '|' . ($item['discount_percentage'] ?? '')
                . '|' . ($item['veteran_group_key'] ?? '');

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'type'                => $item['type'] ?? '',
                    'units'               => 0,
                    'discount_amount'     => 0,
                    'pay_amount'          => $item['pay_amount'] ?? null,
                    'discount_percentage' => $item['discount_percentage'] ?? null,
                    'veteran_group_key'   => $item['veteran_group_key'] ?? null,
                ];
            }

            $grouped[$key]['units']++;
            $grouped[$key]['discount_amount'] += (int) ($item['discount_amount'] ?? 0);
        }

        return array_values($grouped);
    }

    /**
     * @param  array<string, int>  $bookingConsumedPerGroup
     * @return array{
     *   discount_amount: int,
     *   free_units: int,
     *   effective_discount_percentage: int,
     *   discount_breakdown: array<int, array<string, mixed>>,
     *   group_usage: array<string, int>
     * }
     */
    private static function emptyResult(array $bookingConsumedPerGroup): array
    {
        return [
            'discount_amount'               => 0,
            'free_units'                    => 0,
            'effective_discount_percentage' => 0,
            'discount_breakdown'            => [],
            'group_usage'                   => $bookingConsumedPerGroup,
        ];
    }
}
