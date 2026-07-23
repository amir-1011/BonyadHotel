<?php

namespace App\Services;

/**
 * Weekly session ladder for veteran service discounts.
 *
 * Tiers apply in order; each tier consumes up to session_count sessions per week
 * (null session_count = all remaining sessions in that tier).
 */
class ServiceDiscountTierEngine
{
    public const TYPE_FREE = 'free';

    public const TYPE_FIXED_PAY = 'fixed_pay';

    public const TYPE_PERCENTAGE = 'percentage';

    /**
     * @param  array<int, array<string, mixed>>  $tiers
     * @return array{
     *   discount_amount: int,
     *   free_units: int,
     *   effective_discount_percentage: int,
     *   discount_breakdown: array<int, array<string, mixed>>
     * }
     */
    public static function calculateLine(
        int $unitPrice,
        int $quantity,
        int $sessionsUsedInWeek,
        int $sessionsUsedInBooking,
        array $tiers,
    ): array {
        $tiers = self::normalizeTiers($tiers);
        $quantity = max(0, $quantity);
        $unitPrice = max(0, $unitPrice);

        if ($quantity === 0 || empty($tiers)) {
            return self::emptyLineResult();
        }

        $discountAmount = 0;
        $freeUnits = 0;
        $lineSubtotal = $quantity * $unitPrice;
        $rawBreakdown = [];

        for ($i = 0; $i < $quantity; $i++) {
            $sessionIndex = $sessionsUsedInWeek + $sessionsUsedInBooking + $i + 1;
            $tier = self::tierForSessionIndex($sessionIndex, $tiers);
            $unitDisc = self::unitDiscount($unitPrice, $tier);
            $discountAmount += $unitDisc;

            if (($tier['type'] ?? '') === self::TYPE_FREE) {
                $freeUnits++;
            }

            $rawBreakdown[] = [
                'type'                => $tier['type'] ?? '',
                'discount_amount'     => $unitDisc,
                'pay_amount'          => ($tier['type'] ?? '') === self::TYPE_FIXED_PAY
                    ? max(0, (int) ($tier['pay_amount'] ?? 0))
                    : null,
                'discount_percentage' => ($tier['type'] ?? '') === self::TYPE_PERCENTAGE
                    ? max(0, min(100, (int) ($tier['discount_percentage'] ?? 0)))
                    : null,
            ];
        }

        return [
            'discount_amount'               => $discountAmount,
            'free_units'                    => $freeUnits,
            'effective_discount_percentage' => $lineSubtotal > 0
                ? (int) round($discountAmount / $lineSubtotal * 100)
                : 0,
            'discount_breakdown'            => self::collapseBreakdown($rawBreakdown),
        ];
    }

    /**
     * @return array{
     *   discount_amount: int,
     *   free_units: int,
     *   effective_discount_percentage: int,
     *   discount_breakdown: array<int, array<string, mixed>>
     * }
     */
    public static function legacyLineBreakdown(
        int $unitPrice,
        int $quantity,
        int $freeUnits,
        int $discountPercentage,
    ): array {
        $unitPrice = max(0, $unitPrice);
        $quantity = max(0, $quantity);
        $freeUnits = min($quantity, max(0, $freeUnits));
        $paidUnits = $quantity - $freeUnits;
        $lineSubtotal = $quantity * $unitPrice;

        $freeDiscount = $freeUnits * $unitPrice;
        $paidDiscount = (int) round($paidUnits * $unitPrice * max(0, min(100, $discountPercentage)) / 100);
        $discountAmount = $freeDiscount + $paidDiscount;

        $breakdown = [];
        if ($freeUnits > 0) {
            $breakdown[] = [
                'type'            => self::TYPE_FREE,
                'units'           => $freeUnits,
                'discount_amount' => $freeDiscount,
            ];
        }
        if ($paidUnits > 0 && $discountPercentage > 0) {
            $breakdown[] = [
                'type'                => self::TYPE_PERCENTAGE,
                'units'               => $paidUnits,
                'discount_amount'     => $paidDiscount,
                'discount_percentage' => $discountPercentage,
            ];
        }

        return [
            'discount_amount'               => $discountAmount,
            'free_units'                    => $freeUnits,
            'effective_discount_percentage' => $lineSubtotal > 0
                ? (int) round($discountAmount / $lineSubtotal * 100)
                : 0,
            'discount_breakdown'            => $breakdown,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $raw
     * @return array<int, array<string, mixed>>
     */
    public static function collapseBreakdown(array $raw): array
    {
        $grouped = [];

        foreach ($raw as $item) {
            $key = ($item['type'] ?? '')
                . '|' . ($item['pay_amount'] ?? '')
                . '|' . ($item['discount_percentage'] ?? '');

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'type'                => $item['type'] ?? '',
                    'units'               => 0,
                    'discount_amount'     => 0,
                    'pay_amount'          => $item['pay_amount'] ?? null,
                    'discount_percentage' => $item['discount_percentage'] ?? null,
                ];
            }

            $grouped[$key]['units']++;
            $grouped[$key]['discount_amount'] += (int) ($item['discount_amount'] ?? 0);
        }

        return array_values($grouped);
    }

    public static function describeBreakdownItem(array $item): string
    {
        $units = (int) ($item['units'] ?? 0);
        $amount = (int) ($item['discount_amount'] ?? 0);
        $unitLabel = $units === 1 ? '۱ جلسه' : $units . ' جلسه';

        return match ($item['type'] ?? '') {
            self::TYPE_FREE => $unitLabel . ' رایگان (تخفیف ' . number_format($amount) . ' ت)',
            self::TYPE_FIXED_PAY => $unitLabel . ' با مبلغ ثابت '
                . number_format((int) ($item['pay_amount'] ?? 0)) . ' ت '
                . '(تخفیف ' . number_format($amount) . ' ت)',
            self::TYPE_PERCENTAGE => $unitLabel . ' با '
                . (int) ($item['discount_percentage'] ?? 0) . '٪ تخفیف '
                . '(− ' . number_format($amount) . ' ت)',
            default => 'تخفیف ' . number_format($amount) . ' ت',
        };
    }

    /**
     * @return array{
     *   discount_amount: int,
     *   free_units: int,
     *   effective_discount_percentage: int,
     *   discount_breakdown: array<int, array<string, mixed>>
     * }
     */
    private static function emptyLineResult(): array
    {
        return [
            'discount_amount'               => 0,
            'free_units'                    => 0,
            'effective_discount_percentage' => 0,
            'discount_breakdown'            => [],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $tiers
     * @return array<string, mixed>
     */
    public static function tierForSessionIndex(int $sessionIndex, array $tiers): array
    {
        $tiers = self::normalizeTiers($tiers);
        if ($sessionIndex < 1 || empty($tiers)) {
            return ['type' => self::TYPE_PERCENTAGE, 'discount_percentage' => 0];
        }

        $offset = 0;
        foreach ($tiers as $tier) {
            $limit = $tier['session_count'] ?? null;
            if ($limit === null || $limit === '') {
                return $tier;
            }

            $limit = max(0, (int) $limit);
            if ($limit === 0) {
                continue;
            }

            if ($sessionIndex <= $offset + $limit) {
                return $tier;
            }

            $offset += $limit;
        }

        // All finite tiers exhausted. Only tiers with null session_count apply beyond
        // (handled inside the loop). Otherwise no discount remains on the ladder.
        return ['type' => self::TYPE_PERCENTAGE, 'discount_percentage' => 0];
    }

    /**
     * @param  array<string, mixed>  $tier
     */
    public static function unitDiscount(int $unitPrice, array $tier): int
    {
        $unitPrice = max(0, $unitPrice);

        return match ($tier['type'] ?? '') {
            self::TYPE_FREE => $unitPrice,
            self::TYPE_FIXED_PAY => max(0, $unitPrice - max(0, (int) ($tier['pay_amount'] ?? 0))),
            self::TYPE_PERCENTAGE => (int) round(
                $unitPrice * max(0, min(100, (int) ($tier['discount_percentage'] ?? 0))) / 100
            ),
            default => 0,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $tiers
     */
    public static function freeTierQuota(array $tiers): int
    {
        $total = 0;

        foreach (self::normalizeTiers($tiers) as $tier) {
            if (($tier['type'] ?? '') !== self::TYPE_FREE) {
                continue;
            }

            $count = $tier['session_count'] ?? null;
            if ($count === null || $count === '') {
                break;
            }

            $total += max(0, (int) $count);
        }

        return $total;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tiers
     */
    public static function fallbackPercentage(array $tiers): int
    {
        $tiers = self::normalizeTiers($tiers);

        for ($i = count($tiers) - 1; $i >= 0; $i--) {
            if (($tiers[$i]['type'] ?? '') === self::TYPE_PERCENTAGE) {
                return max(0, min(100, (int) ($tiers[$i]['discount_percentage'] ?? 0)));
            }
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array<int, array<string, mixed>>
     */
    public static function tiersFromLegacyRule(array $rule): array
    {
        $tiers = [];

        if (($rule['free_sessions_eligible'] ?? false) && (int) ($rule['weekly_free_sessions'] ?? 0) > 0) {
            $tiers[] = [
                'type'          => self::TYPE_FREE,
                'session_count' => (int) $rule['weekly_free_sessions'],
            ];
        }

        $tiers[] = [
            'type'                => self::TYPE_PERCENTAGE,
            'session_count'       => null,
            'discount_percentage' => (int) ($rule['discount_percentage'] ?? 0),
        ];

        return $tiers;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tiers
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeTiers(array $tiers): array
    {
        return array_values(array_filter($tiers, function (array $tier) {
            $type = $tier['type'] ?? '';

            return in_array($type, [self::TYPE_FREE, self::TYPE_FIXED_PAY, self::TYPE_PERCENTAGE], true);
        }));
    }

    /**
     * Normalize a DB / legacy row for the discount-matrix UI.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function matrixRowFromPersistence(array $row): array
    {
        $useTiered = (bool) ($row['use_tiered_discount'] ?? false);
        $tiers = self::normalizeTiers($row['discount_tiers'] ?? []);

        if ($useTiered && !empty($tiers)) {
            return self::matrixRowUiState($row, true, $tiers);
        }

        if (!empty($tiers)) {
            return self::matrixRowUiState($row, true, $tiers);
        }

        $hasLegacyLadder = ($row['free_sessions_eligible'] ?? false)
            && (int) ($row['weekly_free_sessions'] ?? 0) > 0;

        if ($hasLegacyLadder) {
            return self::matrixRowUiState(
                $row,
                true,
                self::tiersFromLegacyRule($row),
            );
        }

        return self::matrixRowUiState($row, false, []);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, array<string, mixed>>  $tiers
     * @return array<string, mixed>
     */
    private static function matrixRowUiState(array $row, bool $useTiered, array $tiers): array
    {
        return [
            'discount_percentage'    => (int) ($row['discount_percentage'] ?? 0),
            'free_sessions_eligible' => (bool) ($row['free_sessions_eligible'] ?? false),
            'weekly_free_sessions'   => (int) ($row['weekly_free_sessions'] ?? 0),
            'use_tiered_discount'    => $useTiered,
            'discount_tiers'         => $tiers,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function matrixRowToPersistence(array $row): array
    {
        $useTiered = (bool) ($row['use_tiered_discount'] ?? false);
        $tiers = self::normalizeTiers($row['discount_tiers'] ?? []);

        if (!$useTiered || empty($tiers)) {
            $eligible = (bool) ($row['free_sessions_eligible'] ?? false);

            return [
                'use_tiered_discount'    => false,
                'discount_tiers'         => null,
                'discount_percentage'    => (int) ($row['discount_percentage'] ?? 0),
                'free_sessions_eligible' => $eligible,
                'weekly_free_sessions'   => $eligible ? (int) ($row['weekly_free_sessions'] ?? 0) : 0,
            ];
        }

        $freeQuota = self::freeTierQuota($tiers);

        return [
            'use_tiered_discount'    => true,
            'discount_tiers'         => $tiers,
            'discount_percentage'    => self::fallbackPercentage($tiers),
            'free_sessions_eligible' => $freeQuota > 0,
            'weekly_free_sessions'   => $freeQuota,
        ];
    }
}
