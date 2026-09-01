<?php

namespace App\Services;

use App\Models\Accommodation;
use App\Models\ServiceCatalog;
use App\Models\VeteranGroup;
use App\Models\VeteranGroupServiceDiscount;

class VeteranPolicyProvisioner
{
    public function seedForAccommodation(Accommodation|int $accommodation, bool $force = false): void
    {
        $model = $accommodation instanceof Accommodation
            ? $accommodation
            : Accommodation::query()->findOrFail($accommodation);
        $accommodationId = $model->id;

        $hasGroups = VeteranGroup::query()->where('accommodation_id', $accommodationId)->exists();
        $hasServices = ServiceCatalog::query()->where('accommodation_id', $accommodationId)->exists();

        if ($hasGroups && $hasServices) {
            return;
        }

        if (!$force && !($model->veteran_policy_auto_seed ?? true)) {
            return;
        }

        $groupIdByKey = [];
        foreach ($this->groupDefinitions() as $data) {
            $group = VeteranGroup::query()->firstOrCreate(
                [
                    'accommodation_id' => $accommodationId,
                    'key'            => $data['key'],
                ],
                array_merge($data, ['accommodation_id' => $accommodationId]),
            );
            $groupIdByKey[$group->key] = $group->id;
        }

        $serviceIdByKey = [];
        foreach ($this->serviceDefinitions() as $data) {
            $service = ServiceCatalog::query()->firstOrCreate(
                [
                    'accommodation_id' => $accommodationId,
                    'key'            => $data['key'],
                ],
                array_merge($data, ['accommodation_id' => $accommodationId]),
            );
            $serviceIdByKey[$service->key] = $service->id;
        }

        $this->seedDiscountMatrix($groupIdByKey, $serviceIdByKey);
        app(VeteranPolicyService::class)->clearCache($accommodationId);
    }

    public function clearGroupsForAccommodation(Accommodation|int $accommodation): void
    {
        $accommodationId = $accommodation instanceof Accommodation ? $accommodation->id : $accommodation;

        VeteranGroup::query()
            ->where('accommodation_id', $accommodationId)
            ->delete();

        Accommodation::query()
            ->where('id', $accommodationId)
            ->update(['veteran_policy_auto_seed' => false]);

        app(VeteranPolicyService::class)->clearCache($accommodationId);
    }

    public function clearServicesForAccommodation(Accommodation|int $accommodation): void
    {
        $accommodationId = $accommodation instanceof Accommodation ? $accommodation->id : $accommodation;

        ServiceCatalog::query()
            ->where('accommodation_id', $accommodationId)
            ->delete();

        Accommodation::query()
            ->where('id', $accommodationId)
            ->update(['veteran_policy_auto_seed' => false]);

        app(VeteranPolicyService::class)->clearCache($accommodationId);
    }

    public function restoreDefaultsForAccommodation(Accommodation|int $accommodation): void
    {
        app(VeteranPolicyBroadcastService::class)->copyGlobalPolicyToAccommodation($accommodation);
    }

    public function restoreHardcodedDefaultsForAccommodation(Accommodation|int $accommodation): void
    {
        $model = $accommodation instanceof Accommodation
            ? $accommodation
            : Accommodation::query()->findOrFail($accommodation);
        $accommodationId = $model->id;

        VeteranGroup::query()->where('accommodation_id', $accommodationId)->delete();
        ServiceCatalog::query()->where('accommodation_id', $accommodationId)->delete();

        $model->update(['veteran_policy_auto_seed' => true]);
        $model->refresh();

        $this->seedForAccommodation($model, force: true);
    }

    public function markAutoSeedDisabledIfPolicyEmpty(int $accommodationId): void
    {
        $this->disableAutoSeedIfPolicyEmpty($accommodationId);
    }

    private function disableAutoSeedIfPolicyEmpty(int $accommodationId): void
    {
        $hasGroups = VeteranGroup::query()->where('accommodation_id', $accommodationId)->exists();
        $hasServices = ServiceCatalog::query()->where('accommodation_id', $accommodationId)->exists();

        if ($hasGroups || $hasServices) {
            return;
        }

        Accommodation::query()
            ->where('id', $accommodationId)
            ->update(['veteran_policy_auto_seed' => false]);
    }

    /** @return array<int, array<string, mixed>> */
    public function groupDefinitions(): array
    {
        return [
            [
                'key'                    => 'veteran_70_spouses',
                'label'                  => 'جانبازان ۷۰ درصد و همسران',
                'accommodation_discount' => 70,
                'nights_per_dependent'   => 6,
                'max_nights_per_period'  => 3,
                'period_months'          => 6,
                'weekly_free_sessions'   => 3,
                'usage_notes'            => '۶ شب به ازای هر نفر تحت تکفل (سقف ۳ شب در هر ۶ ماه)',
                'sort_order'             => 1,
                'is_active'              => true,
            ],
            [
                'key'                    => 'veteran_50_69_dependents',
                'label'                  => 'جانبازان ۵۰ الی ۶۹ درصد به همراه افراد تحت تکفل',
                'accommodation_discount' => 50,
                'nights_per_dependent'   => 6,
                'max_nights_per_period'  => 3,
                'period_months'          => 6,
                'weekly_free_sessions'   => 0,
                'usage_notes'            => null,
                'sort_order'             => 2,
                'is_active'              => true,
            ],
            [
                'key'                    => 'veteran_25_49_dependents',
                'label'                  => 'جانبازان ۵ الی ۴۹ درصد به همراه افراد تحت تکفل',
                'accommodation_discount' => 40,
                'nights_per_dependent'   => 6,
                'max_nights_per_period'  => 3,
                'period_months'          => 6,
                'weekly_free_sessions'   => 0,
                'usage_notes'            => null,
                'sort_order'             => 3,
                'is_active'              => true,
            ],
            [
                'key'                    => 'martyr_children',
                'label'                  => 'فرزندان شهدا و فرزندان جانبازان ۷۰ درصد',
                'accommodation_discount' => 50,
                'nights_per_dependent'   => 6,
                'max_nights_per_period'  => 3,
                'period_months'          => 6,
                'weekly_free_sessions'   => 0,
                'usage_notes'            => null,
                'sort_order'             => 4,
                'is_active'              => true,
            ],
            [
                'key'                    => 'martyr_parents_dependents',
                'label'                  => 'والدین شهدا به همراه افراد تحت تکفل',
                'accommodation_discount' => 70,
                'nights_per_dependent'   => 6,
                'max_nights_per_period'  => 3,
                'period_months'          => 6,
                'weekly_free_sessions'   => 0,
                'usage_notes'            => null,
                'sort_order'             => 5,
                'is_active'              => true,
            ],
            [
                'key'                    => 'martyr_spouse_dependents',
                'label'                  => 'همسر شهید به همراه افراد تحت تکفل',
                'accommodation_discount' => 50,
                'nights_per_dependent'   => 6,
                'max_nights_per_period'  => 3,
                'period_months'          => 6,
                'weekly_free_sessions'   => 0,
                'usage_notes'            => null,
                'sort_order'             => 6,
                'is_active'              => true,
            ],
            [
                'key'                    => 'freed_prisoner_dependents',
                'label'                  => 'آزادگان سرافراز به همراه افراد تحت تکفل',
                'accommodation_discount' => 50,
                'nights_per_dependent'   => 6,
                'max_nights_per_period'  => 3,
                'period_months'          => 6,
                'weekly_free_sessions'   => 0,
                'usage_notes'            => null,
                'sort_order'             => 7,
                'is_active'              => true,
            ],
            [
                'key'                    => 'foundation_staff_retirees',
                'label'                  => 'همکاران و بازنشستگان بنیاد',
                'accommodation_discount' => 30,
                'nights_per_dependent'   => 6,
                'max_nights_per_period'  => 3,
                'period_months'          => 6,
                'weekly_free_sessions'   => 0,
                'usage_notes'            => null,
                'sort_order'             => 8,
                'is_active'              => true,
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function serviceDefinitions(): array
    {
        return [
            [
                'key'                    => 'pool',
                'name'                   => 'استخر',
                'default_price'          => 0,
                'supports_free_sessions' => true,
                'default_discount'       => 65,
                'min_discount'           => 50,
                'max_discount'           => 80,
                'sort_order'             => 1,
                'is_active'              => true,
            ],
            [
                'key'                    => 'gym',
                'name'                   => 'بدنسازی',
                'default_price'          => 0,
                'supports_free_sessions' => true,
                'default_discount'       => 65,
                'min_discount'           => 50,
                'max_discount'           => 80,
                'sort_order'             => 2,
                'is_active'              => true,
            ],
            [
                'key'                    => 'multi_purpose_hall',
                'name'                   => 'سالن چند منظوره',
                'default_price'          => 0,
                'supports_free_sessions' => true,
                'default_discount'       => 65,
                'min_discount'           => 50,
                'max_discount'           => 80,
                'sort_order'             => 3,
                'is_active'              => true,
            ],
            [
                'key'              => 'conference_hall',
                'name'             => 'سالن همایش',
                'default_price'    => 0,
                'default_discount' => 40,
                'sort_order'       => 4,
                'is_active'        => true,
            ],
            [
                'key'              => 'reception_entrance',
                'name'             => 'تالار پذیرایی — ورودی',
                'default_price'    => 0,
                'default_discount' => 50,
                'sort_order'       => 5,
                'is_active'        => true,
            ],
            [
                'key'              => 'reception_food',
                'name'             => 'تالار پذیرایی — غذا',
                'default_price'    => 0,
                'default_discount' => 20,
                'sort_order'       => 6,
                'is_active'        => true,
            ],
        ];
    }

    /**
     * @param  array<string, int>  $groupIdByKey
     * @param  array<string, int>  $serviceIdByKey
     */
    private function seedDiscountMatrix(array $groupIdByKey, array $serviceIdByKey): void
    {
        $sportKeys = ['pool', 'gym', 'multi_purpose_hall'];
        $fixedServices = [
            'conference_hall'    => 40,
            'reception_entrance' => 50,
            'reception_food'     => 20,
        ];

        foreach ($groupIdByKey as $groupKey => $groupId) {
            foreach ($sportKeys as $serviceKey) {
                $serviceId = $serviceIdByKey[$serviceKey] ?? null;
                if (!$serviceId) {
                    continue;
                }

                $is70 = $groupKey === 'veteran_70_spouses';
                VeteranGroupServiceDiscount::query()->firstOrCreate(
                    [
                        'veteran_group_id'   => $groupId,
                        'service_catalog_id' => $serviceId,
                    ],
                    [
                        'discount_percentage'    => $is70 ? 0 : 65,
                        'free_sessions_eligible' => $is70,
                        'weekly_free_sessions'   => $is70 ? 3 : 0,
                    ],
                );
            }

            foreach ($fixedServices as $serviceKey => $discount) {
                $serviceId = $serviceIdByKey[$serviceKey] ?? null;
                if (!$serviceId) {
                    continue;
                }

                VeteranGroupServiceDiscount::query()->firstOrCreate(
                    [
                        'veteran_group_id'   => $groupId,
                        'service_catalog_id' => $serviceId,
                    ],
                    [
                        'discount_percentage'    => $discount,
                        'free_sessions_eligible' => false,
                        'weekly_free_sessions'   => 0,
                    ],
                );
            }
        }
    }
}
