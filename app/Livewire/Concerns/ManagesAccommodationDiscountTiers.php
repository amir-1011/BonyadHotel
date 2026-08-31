<?php

namespace App\Livewire\Concerns;

use App\Services\AccommodationDiscountTierEngine;

trait ManagesAccommodationDiscountTiers
{
    public function addGroupAccommodationTier(int $groupIndex): void
    {
        $tiers = $this->groups[$groupIndex]['accommodation_discount_tiers'] ?? [];
        $tiers[] = [
            'type'                => AccommodationDiscountTierEngine::TYPE_PERCENTAGE,
            'night_count'         => null,
            'discount_percentage' => 0,
        ];

        $this->groups[$groupIndex]['accommodation_discount_tiers'] = $tiers;
    }

    public function removeGroupAccommodationTier(int $groupIndex, int $tierIndex): void
    {
        $tiers = $this->groups[$groupIndex]['accommodation_discount_tiers'] ?? [];
        unset($tiers[$tierIndex]);
        $this->groups[$groupIndex]['accommodation_discount_tiers'] = array_values($tiers);
    }

    public function seedGroupAccommodationTiersFromLegacy(int $groupIndex): void
    {
        $row = $this->groups[$groupIndex] ?? [];
        if (!empty($row['accommodation_discount_tiers'])) {
            return;
        }

        $this->groups[$groupIndex]['accommodation_discount_tiers'] = [[
            'type'                => AccommodationDiscountTierEngine::TYPE_PERCENTAGE,
            'night_count'         => null,
            'discount_percentage' => (int) ($row['accommodation_discount'] ?? 0),
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    protected function accommodationTierValidationRules(): array
    {
        return [
            'groups.*.use_tiered_accommodation_discount' => ['nullable', 'boolean'],
            'groups.*.accommodation_discount_tiers'      => ['nullable', 'array'],
            'groups.*.accommodation_discount_tiers.*.type' => ['nullable', 'string'],
            'groups.*.accommodation_discount_tiers.*.night_count' => ['nullable', 'integer', 'min:1', 'max:365'],
            'groups.*.accommodation_discount_tiers.*.pay_amount' => ['nullable', 'integer', 'min:0'],
            'groups.*.accommodation_discount_tiers.*.discount_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
