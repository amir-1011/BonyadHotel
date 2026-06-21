<?php

namespace App\Support;

use App\Services\VeteranPolicyService;

class VeteranGroups
{
    public static function options(): array
    {
        return app(VeteranPolicyService::class)->optionsForUi();
    }

    public static function label(?string $type): string
    {
        if (!$type) {
            return 'کاربر عادی';
        }

        $normalized = app(VeteranPolicyService::class)->normalizeKey($type);
        $group = app(VeteranPolicyService::class)->groupByKey($normalized);

        if ($group) {
            return $group->label;
        }

        return self::fallbackOptions()[$type]['label'] ?? 'کاربر عادی';
    }

    public static function discount(?string $type): int
    {
        if (!$type) {
            return 0;
        }

        return app(VeteranPolicyService::class)->accommodationDiscount($type);
    }

    public static function accommodationDiscount(?string $type): int
    {
        return self::discount($type);
    }

    /** @return array<string, array{label:string, discount:int}> */
    private static function fallbackOptions(): array
    {
        return [
            'martyr_family'         => ['label' => 'خانواده شهید', 'discount' => 50],
            'veteran_25_49'         => ['label' => 'جانباز ۲۵ تا ۴۹ درصد', 'discount' => 30],
            'veteran_50_69'         => ['label' => 'جانباز ۵۰ تا ۶۹ درصد', 'discount' => 40],
            'veteran_70_plus'       => ['label' => 'جانباز ۷۰ درصد و بالاتر', 'discount' => 50],
            'freed_prisoner_family' => ['label' => 'خانواده آزاده', 'discount' => 40],
        ];
    }
}
