<?php

namespace App\Support;

class RoomTypePriceResolver
{
    /**
     * Compute nightly price after custom price and percentage adjustment.
     * Positive discount = cheaper; negative = surcharge (e.g. -20 → 20% more expensive).
     */
    public static function effectivePrice(int $basePrice, ?int $customPrice, ?int $discountPercentage): int
    {
        $price = self::resolveBasePrice($basePrice, $customPrice);

        if ($discountPercentage !== null && $discountPercentage !== 0) {
            $price = (int) round($price * (1 - $discountPercentage / 100));
        }

        return max(0, $price);
    }

    /**
     * Empty money-input submits 0 — treat as "use base rate", not a free night.
     */
    public static function resolveBasePrice(int $basePrice, ?int $customPrice): int
    {
        if ($customPrice !== null && $customPrice > 0) {
            return $customPrice;
        }

        return $basePrice;
    }

    public static function normalizeCustomPrice(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $price = (int) $value;

        return $price > 0 ? $price : null;
    }

    public static function hasPriceAdjustment(?int $customPrice, ?int $discountPercentage): bool
    {
        return ($customPrice !== null && $customPrice > 0)
            || ($discountPercentage !== null && $discountPercentage !== 0);
    }

    public static function formatDiscount(?int $discountPercentage): string
    {
        if ($discountPercentage === null || $discountPercentage === 0) {
            return '—';
        }

        return ($discountPercentage > 0 ? '' : '') . $discountPercentage . '%';
    }
}
