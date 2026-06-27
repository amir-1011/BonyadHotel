<?php

namespace App\Services;

use App\Models\Accommodation;
use App\Models\ServiceCatalog;
use App\Models\ServiceCatalogVariant;
use App\Models\VeteranGroup;
use App\Models\VeteranGroupServiceDiscount;

class VeteranPolicyBroadcastService
{
    public function __construct(
        private readonly VeteranPolicyProvisioner $provisioner,
        private readonly VeteranPolicyService $policyService,
    ) {}

    public function ensureAllAccommodationsHavePolicy(): void
    {
        foreach (Accommodation::query()->pluck('id') as $accommodationId) {
            $this->provisioner->seedForAccommodation($accommodationId);
        }
    }

    public function referenceAccommodationId(): ?int
    {
        return VeteranGroup::query()
            ->orderBy('accommodation_id')
            ->value('accommodation_id');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function syncGroupByKey(string $key, array $attributes): void
    {
        $this->ensureAllAccommodationsHavePolicy();

        VeteranGroup::query()
            ->where('key', $key)
            ->update($attributes);

        $this->clearAllCaches();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function syncServiceByKey(string $key, array $attributes): void
    {
        $this->ensureAllAccommodationsHavePolicy();

        ServiceCatalog::query()
            ->where('key', $key)
            ->update($attributes);

        $this->clearAllCaches();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function syncDiscountByKeys(string $groupKey, string $serviceKey, array $attributes): void
    {
        $this->ensureAllAccommodationsHavePolicy();

        $groups = VeteranGroup::query()->where('key', $groupKey)->get(['id', 'accommodation_id']);
        $services = ServiceCatalog::query()->where('key', $serviceKey)->get(['id', 'accommodation_id']);

        $servicesByAccommodation = $services->keyBy('accommodation_id');

        foreach ($groups as $group) {
            $service = $servicesByAccommodation->get($group->accommodation_id);
            if (!$service) {
                continue;
            }

            VeteranGroupServiceDiscount::query()->updateOrCreate(
                [
                    'veteran_group_id'   => $group->id,
                    'service_catalog_id' => $service->id,
                ],
                $attributes,
            );
        }

        $this->clearAllCaches();
    }

    /**
     * @param  array<string, mixed>  $groupData  Must include unique `key`
     */
    public function addGroupToAllAccommodations(array $groupData): void
    {
        $this->ensureAllAccommodationsHavePolicy();

        foreach (Accommodation::query()->pluck('id') as $accommodationId) {
            if (VeteranGroup::query()->where('accommodation_id', $accommodationId)->where('key', $groupData['key'])->exists()) {
                continue;
            }

            $group = VeteranGroup::create(array_merge($groupData, [
                'accommodation_id' => $accommodationId,
            ]));

            foreach (ServiceCatalog::query()->forAccommodation($accommodationId)->ordered()->get() as $service) {
                VeteranGroupServiceDiscount::create([
                    'veteran_group_id'       => $group->id,
                    'service_catalog_id'     => $service->id,
                    'discount_percentage'    => $service->default_discount,
                    'free_sessions_eligible' => false,
                    'weekly_free_sessions'   => 0,
                ]);
            }
        }

        $this->clearAllCaches();
    }

    /**
     * @param  array<string, mixed>  $serviceData  Must include unique `key`
     */
    public function addServiceToAllAccommodations(array $serviceData): void
    {
        $this->ensureAllAccommodationsHavePolicy();

        foreach (Accommodation::query()->pluck('id') as $accommodationId) {
            if (ServiceCatalog::query()->where('accommodation_id', $accommodationId)->where('key', $serviceData['key'])->exists()) {
                continue;
            }

            $service = ServiceCatalog::create(array_merge($serviceData, [
                'accommodation_id' => $accommodationId,
            ]));

            foreach (VeteranGroup::query()->forAccommodation($accommodationId)->get() as $group) {
                VeteranGroupServiceDiscount::create([
                    'veteran_group_id'       => $group->id,
                    'service_catalog_id'     => $service->id,
                    'discount_percentage'    => 0,
                    'free_sessions_eligible' => false,
                    'weekly_free_sessions'   => 0,
                ]);
            }
        }

        $this->clearAllCaches();
    }

    /**
     * @param  array<int, array<string, mixed>>  $variants
     */
    public function syncVariantsForService(string $serviceKey, array $variants): void
    {
        $this->ensureAllAccommodationsHavePolicy();

        $services = ServiceCatalog::query()->where('key', $serviceKey)->get(['id', 'accommodation_id']);

        foreach ($services as $service) {
            $keptKeys = [];

            foreach ($variants as $index => $variant) {
                $variantKey = $variant['key'] ?? null;
                if (!$variantKey) {
                    continue;
                }

                $payload = [
                    'name'       => $variant['name'],
                    'price'      => (int) $variant['price'],
                    'sort_order' => $index + 1,
                    'is_active'  => (bool) ($variant['is_active'] ?? true),
                ];

                ServiceCatalogVariant::query()->updateOrCreate(
                    [
                        'service_catalog_id' => $service->id,
                        'key'                => $variantKey,
                    ],
                    $payload,
                );

                $keptKeys[] = $variantKey;
            }

            if ($keptKeys !== []) {
                ServiceCatalogVariant::query()
                    ->where('service_catalog_id', $service->id)
                    ->whereNotIn('key', $keptKeys)
                    ->delete();
            }
        }

        $this->clearAllCaches();
    }

    /**
     * @param  array<string, mixed>  $variantData  Must include unique `key`
     */
    public function addVariantToAllAccommodations(string $serviceKey, array $variantData): void
    {
        $this->ensureAllAccommodationsHavePolicy();

        foreach (ServiceCatalog::query()->where('key', $serviceKey)->get() as $service) {
            if (ServiceCatalogVariant::query()
                ->where('service_catalog_id', $service->id)
                ->where('key', $variantData['key'])
                ->exists()) {
                continue;
            }

            ServiceCatalogVariant::create(array_merge($variantData, [
                'service_catalog_id' => $service->id,
                'sort_order'         => (int) ServiceCatalogVariant::query()
                    ->where('service_catalog_id', $service->id)
                    ->max('sort_order') + 1,
            ]));
        }

        $this->clearAllCaches();
    }

    public function removeVariantFromAllAccommodations(string $serviceKey, string $variantKey): void
    {
        $serviceIds = ServiceCatalog::query()->where('key', $serviceKey)->pluck('id');

        ServiceCatalogVariant::query()
            ->whereIn('service_catalog_id', $serviceIds)
            ->where('key', $variantKey)
            ->delete();

        $this->clearAllCaches();
    }

    public function clearAllCaches(): void
    {
        foreach (Accommodation::query()->pluck('id') as $accommodationId) {
            $this->policyService->clearCache($accommodationId);
        }
    }
}
