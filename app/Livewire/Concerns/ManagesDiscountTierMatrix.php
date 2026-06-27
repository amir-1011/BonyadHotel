<?php

namespace App\Livewire\Concerns;

use App\Services\ServiceDiscountTierEngine;

trait ManagesDiscountTierMatrix
{
    public function addMatrixTier(string $groupKey, int|string $serviceRef): void
    {
        $tiers = $this->discountMatrix[$groupKey][$serviceRef]['discount_tiers'] ?? [];
        $tiers[] = [
            'type'                => ServiceDiscountTierEngine::TYPE_PERCENTAGE,
            'session_count'       => null,
            'discount_percentage' => 0,
        ];

        $this->discountMatrix[$groupKey][$serviceRef]['discount_tiers'] = $tiers;
    }

    public function removeMatrixTier(string $groupKey, int|string $serviceRef, int $tierIndex): void
    {
        $tiers = $this->discountMatrix[$groupKey][$serviceRef]['discount_tiers'] ?? [];
        unset($tiers[$tierIndex]);
        $this->discountMatrix[$groupKey][$serviceRef]['discount_tiers'] = array_values($tiers);
    }

    public function seedMatrixTiersFromLegacy(string $groupKey, int|string $serviceRef): void
    {
        $row = $this->discountMatrix[$groupKey][$serviceRef] ?? [];
        if (!empty($row['discount_tiers'])) {
            return;
        }

        $this->discountMatrix[$groupKey][$serviceRef]['discount_tiers'] = ServiceDiscountTierEngine::tiersFromLegacyRule([
            'discount_percentage'    => (int) ($row['discount_percentage'] ?? 0),
            'free_sessions_eligible' => (bool) ($row['free_sessions_eligible'] ?? false),
            'weekly_free_sessions'   => (int) ($row['weekly_free_sessions'] ?? 0),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function discountMatrixValidationRules(): array
    {
        return [
            'discountMatrix.*.*.discount_percentage'  => ['nullable', 'integer', 'min:0', 'max:100'],
            'discountMatrix.*.*.weekly_free_sessions' => ['nullable', 'integer', 'min:0', 'max:21'],
            'discountMatrix.*.*.use_tiered_discount'  => ['nullable', 'boolean'],
            'discountMatrix.*.*.discount_tiers'       => ['nullable', 'array'],
            'discountMatrix.*.*.discount_tiers.*.type'  => ['nullable', 'string'],
            'discountMatrix.*.*.discount_tiers.*.session_count' => ['nullable', 'integer', 'min:1', 'max:21'],
            'discountMatrix.*.*.discount_tiers.*.pay_amount' => ['nullable', 'integer', 'min:0'],
            'discountMatrix.*.*.discount_tiers.*.discount_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }

    public function updated($property): void
    {
        if (!is_string($property)
            || !str_starts_with($property, 'discountMatrix.')
            || !str_ends_with($property, 'use_tiered_discount')) {
            return;
        }

        $parts = explode('.', $property);
        if (count($parts) < 4) {
            return;
        }

        if (!data_get($this, $property)) {
            return;
        }

        $this->seedMatrixTiersFromLegacy($parts[1], $parts[2]);
    }
}
