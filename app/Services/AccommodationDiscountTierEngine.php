<?php

namespace App\Services;

/**
 * Period night ladder for veteran accommodation discounts.
 *
 * Tiers apply in order based on cumulative discounted nights in the rolling period
 * (prior bookings plus nights already allocated in the current booking).
 */
class AccommodationDiscountTierEngine
{
    public const TYPE_FREE = 'free';

    public const TYPE_FIXED_PAY = 'fixed_pay';

    public const TYPE_PERCENTAGE = 'percentage';

    /**
     * @param  array<int, array<string, mixed>>  $tiers
     * @return array<int, array<string, mixed>>
     */
    public static function calculateNightTiers(
        int $requestedNights,
        int $nightsUsedInPeriod,
        int $eligibleNights,
        array $tiers,
    ): array {
        $requestedNights = max(0, $requestedNights);
        $eligibleNights = min($requestedNights, max(0, $eligibleNights));
        $nightTiers = array_fill(0, $requestedNights, self::emptyTier());

        for ($i = 0; $i < $eligibleNights; $i++) {
            $periodNightIndex = $nightsUsedInPeriod + $i + 1;
            $nightTiers[$i] = self::tierForNightIndex($periodNightIndex, $tiers);
        }

        return $nightTiers;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tiers
     * @return array<int, int>
     */
    public static function calculateNightDiscounts(
        int $requestedNights,
        int $nightsUsedInPeriod,
        int $eligibleNights,
        array $tiers,
        int $referenceNightPrice = 0,
    ): array {
        $nightTiers = self::calculateNightTiers(
            $requestedNights,
            $nightsUsedInPeriod,
            $eligibleNights,
            $tiers,
        );

        return array_map(
            fn (array $tier) => self::effectivePercentage($referenceNightPrice, $tier),
            $nightTiers,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $tiers
     */
    public static function discountForNightIndex(int $nightIndex, array $tiers, int $referenceNightPrice = 0): int
    {
        return self::effectivePercentage(
            $referenceNightPrice,
            self::tierForNightIndex($nightIndex, $tiers),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $tiers
     * @return array<string, mixed>
     */
    public static function tierForNightIndex(int $nightIndex, array $tiers): array
    {
        $tiers = self::normalizeTiers($tiers);
        if ($nightIndex < 1 || empty($tiers)) {
            return self::emptyTier();
        }

        $offset = 0;
        foreach ($tiers as $tier) {
            $limit = $tier['night_count'] ?? null;
            if ($limit === null || $limit === '') {
                return $tier;
            }

            $limit = max(0, (int) $limit);
            if ($limit === 0) {
                continue;
            }

            if ($nightIndex <= $offset + $limit) {
                return $tier;
            }

            $offset += $limit;
        }

        return self::emptyTier();
    }

    /**
     * @param  array<string, mixed>  $tier
     */
    public static function unitDiscount(int $nightPrice, array $tier): int
    {
        $nightPrice = max(0, $nightPrice);

        return match (self::tierType($tier)) {
            self::TYPE_FREE => $nightPrice,
            self::TYPE_FIXED_PAY => max(0, $nightPrice - max(0, (int) ($tier['pay_amount'] ?? 0))),
            self::TYPE_PERCENTAGE => (int) round(
                $nightPrice * max(0, min(100, (int) ($tier['discount_percentage'] ?? 0))) / 100
            ),
            default => 0,
        };
    }

    /**
     * @param  array<string, mixed>  $tier
     */
    public static function effectivePercentage(int $nightPrice, array $tier): int
    {
        if (!self::tierHasDiscount($tier)) {
            return 0;
        }

        $nightPrice = max(0, $nightPrice);
        if ($nightPrice <= 0) {
            return match (self::tierType($tier)) {
                self::TYPE_PERCENTAGE => max(0, min(100, (int) ($tier['discount_percentage'] ?? 0))),
                self::TYPE_FREE => 100,
                default => 0,
            };
        }

        return max(0, min(100, (int) round(self::unitDiscount($nightPrice, $tier) / $nightPrice * 100)));
    }

    /**
     * Fixed-pay and free tiers apply once per night on the eligible room charge,
     * not independently on each guest slot.
     *
     * @param  array<string, mixed>  $tier
     */
    public static function isRoomLevelTier(array $tier): bool
    {
        return in_array(self::tierType($tier), [self::TYPE_FREE, self::TYPE_FIXED_PAY], true);
    }

    /**
     * @param  array<string, mixed>  $tier
     */
    public static function tierHasDiscount(array $tier): bool
    {
        return match (self::tierType($tier)) {
            self::TYPE_FREE => true,
            self::TYPE_FIXED_PAY => max(0, (int) ($tier['pay_amount'] ?? 0)) > 0,
            self::TYPE_PERCENTAGE => (int) ($tier['discount_percentage'] ?? 0) > 0,
            default => false,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $tiers
     */
    public static function fallbackPercentage(array $tiers): int
    {
        $tiers = self::normalizeTiers($tiers);
        if (empty($tiers)) {
            return 0;
        }

        for ($i = count($tiers) - 1; $i >= 0; $i--) {
            if (self::tierType($tiers[$i]) === self::TYPE_PERCENTAGE) {
                return max(0, min(100, (int) ($tiers[$i]['discount_percentage'] ?? 0)));
            }
        }

        return 0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tiers
     */
    public static function maxPercentage(array $tiers): int
    {
        $max = 0;
        foreach (self::normalizeTiers($tiers) as $tier) {
            if (self::tierType($tier) === self::TYPE_PERCENTAGE) {
                $max = max($max, (int) ($tier['discount_percentage'] ?? 0));
            }
        }

        return min(100, $max);
    }

    /**
     * @param  array<int, array<string, mixed>>  $tiers
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeTiers(array $tiers): array
    {
        return array_values(array_filter(array_map(function (array $tier) {
            if (!array_key_exists('type', $tier) && array_key_exists('discount_percentage', $tier)) {
                $tier['type'] = self::TYPE_PERCENTAGE;
            }

            $type = self::tierType($tier);
            if (!in_array($type, [self::TYPE_FREE, self::TYPE_FIXED_PAY, self::TYPE_PERCENTAGE], true)) {
                return null;
            }

            return $tier;
        }, $tiers)));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function groupRowFromPersistence(array $row): array
    {
        $useTiered = (bool) ($row['use_tiered_accommodation_discount'] ?? false);
        $tiers = self::normalizeTiers($row['accommodation_discount_tiers'] ?? []);

        if ($useTiered && !empty($tiers)) {
            return self::groupRowUiState($row, true, $tiers);
        }

        if (!empty($tiers)) {
            return self::groupRowUiState($row, true, $tiers);
        }

        return self::groupRowUiState($row, false, []);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function groupRowToPersistence(array $row): array
    {
        $useTiered = (bool) ($row['use_tiered_accommodation_discount'] ?? false);
        $tiers = self::normalizeTiers($row['accommodation_discount_tiers'] ?? []);

        if (!$useTiered || empty($tiers)) {
            return [
                'use_tiered_accommodation_discount' => false,
                'accommodation_discount_tiers'      => null,
                'accommodation_discount'            => (int) ($row['accommodation_discount'] ?? 0),
            ];
        }

        return [
            'use_tiered_accommodation_discount' => true,
            'accommodation_discount_tiers'      => $tiers,
            'accommodation_discount'            => self::maxPercentage($tiers),
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
            $key = self::tierType($item)
                . '|' . ($item['pay_amount'] ?? '')
                . '|' . ($item['discount_percentage'] ?? '');

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'type'                => self::tierType($item),
                    'units'               => 0,
                    'discount_amount'     => 0,
                    'pay_amount'          => ($item['pay_amount'] ?? null),
                    'discount_percentage' => ($item['discount_percentage'] ?? null),
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
        $nightText = $units === 1 ? '۱ شب' : "{$units} شب";

        return match (self::tierType($item)) {
            self::TYPE_FREE => "{$nightText} رایگان (تخفیف " . number_format($amount) . ' ریال)',
            self::TYPE_FIXED_PAY => "{$nightText} با مبلغ ثابت "
                . number_format((int) ($item['pay_amount'] ?? 0)) . ' ریال '
                . '(تخفیف ' . number_format($amount) . ' ریال)',
            self::TYPE_PERCENTAGE => "{$nightText} با "
                . (int) ($item['discount_percentage'] ?? 0) . '٪ تخفیف اقامت '
                . '(− ' . number_format($amount) . ' ریال)',
            default => "{$nightText} با تخفیف " . number_format($amount) . ' ریال',
        };
    }

    /**
     * Short label for financial rows (without discount amount).
     *
     * @param  array<string, mixed>  $item
     */
    public static function tierBreakdownHint(array $item): string
    {
        $units = (int) ($item['units'] ?? 0);
        $nightText = $units === 1 ? '۱ شب' : "{$units} شب";

        return match (self::tierType($item)) {
            self::TYPE_FREE => "{$nightText} · اقامت رایگان",
            self::TYPE_FIXED_PAY => "{$nightText} · مبلغ ثابت "
                . number_format((int) ($item['pay_amount'] ?? 0)) . ' ریال',
            self::TYPE_PERCENTAGE => "{$nightText} · "
                . (int) ($item['discount_percentage'] ?? 0) . '٪ تخفیف',
            default => $nightText,
        };
    }

    /**
     * @param  array<string, mixed>  $tier
     */
    public static function tierType(array $tier): string
    {
        return (string) ($tier['type'] ?? self::TYPE_PERCENTAGE);
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyTier(): array
    {
        return [
            'type'                => self::TYPE_PERCENTAGE,
            'discount_percentage' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, array<string, mixed>>  $tiers
     * @return array<string, mixed>
     */
    private static function groupRowUiState(array $row, bool $useTiered, array $tiers): array
    {
        return [
            'accommodation_discount'            => (int) ($row['accommodation_discount'] ?? 0),
            'use_tiered_accommodation_discount' => $useTiered,
            'accommodation_discount_tiers'      => $tiers,
        ];
    }
}
