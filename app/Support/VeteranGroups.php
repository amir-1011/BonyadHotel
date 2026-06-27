<?php

namespace App\Support;

use App\Services\VeteranPolicyBroadcastService;
use App\Services\VeteranPolicyProvisioner;
use App\Services\VeteranPolicyService;

class VeteranGroups
{
    public static function options(?int $accommodationId = null): array
    {
        return self::policy($accommodationId)->optionsForUi();
    }

    public static function label(?string $type, ?int $accommodationId = null): string
    {
        if (!$type) {
            return 'کاربر عادی';
        }

        $normalized = self::policy($accommodationId)->normalizeKey($type);
        $group = self::policy($accommodationId)->groupByKey($normalized);

        if ($group) {
            return $group->label;
        }

        $fallback = self::fallbackOptions();

        return $fallback[$normalized]['label']
            ?? $fallback[$type]['label']
            ?? 'کاربر عادی';
    }

    public static function discount(?string $type, ?int $accommodationId = null): int
    {
        if (!$type) {
            return 0;
        }

        return self::policy($accommodationId)->accommodationDiscount($type);
    }

    public static function accommodationDiscount(?string $type, ?int $accommodationId = null): int
    {
        return self::discount($type, $accommodationId);
    }

    /**
     * @param  array<int, string|null>  $types
     */
    public static function accommodationDiscountForTypes(array $types, ?int $accommodationId = null): int
    {
        $types = array_values(array_filter($types));

        if (empty($types)) {
            return 0;
        }

        if (count($types) === 1) {
            return self::accommodationDiscount($types[0], $accommodationId);
        }

        return self::policy($accommodationId)->accommodationDiscountForTypes($types);
    }

    /**
     * @param  array<int, string|null>  $types
     */
    public static function labelsForTypes(array $types, ?int $accommodationId = null): string
    {
        $labels = collect($types)
            ->filter()
            ->map(fn ($type) => self::label($type, $accommodationId))
            ->filter(fn ($label) => $label !== 'کاربر عادی')
            ->values();

        return $labels->isEmpty() ? 'کاربر عادی' : $labels->join(' + ');
    }

    private static function policy(?int $accommodationId): VeteranPolicyService
    {
        if ($accommodationId === null) {
            $referenceId = app(VeteranPolicyBroadcastService::class)->referenceAccommodationId();
            if ($referenceId !== null) {
                $accommodationId = $referenceId;
            }
        }

        return app(VeteranPolicyService::class)->forAccommodation($accommodationId);
    }

    /** @return array<string, array{label:string, discount:int}> */
    private static function fallbackOptions(): array
    {
        static $options = null;

        if ($options !== null) {
            return $options;
        }

        $options = [];
        foreach (app(VeteranPolicyProvisioner::class)->groupDefinitions() as $def) {
            $options[$def['key']] = [
                'label'    => $def['label'],
                'discount' => $def['accommodation_discount'],
            ];
        }

        foreach (VeteranPolicyService::LEGACY_KEY_MAP as $legacy => $modern) {
            if (isset($options[$modern]) && !isset($options[$legacy])) {
                $options[$legacy] = $options[$modern];
            }
        }

        return $options;
    }
}
