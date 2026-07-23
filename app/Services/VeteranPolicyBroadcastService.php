<?php

namespace App\Services;

use App\Models\Accommodation;
use App\Models\ServiceCatalog;
use App\Models\ServiceCatalogVariant;
use App\Models\VeteranGroup;
use App\Models\VeteranGroupServiceDiscount;
use Illuminate\Support\Facades\DB;

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

    public function referenceAccommodationId(?array $accommodationIds = null): ?int
    {
        $query = VeteranGroup::query()->orderBy('accommodation_id');

        if ($accommodationIds !== null) {
            $query->whereIn('accommodation_id', $this->resolveAccommodationIds($accommodationIds));
        }

        return $query->value('accommodation_id');
    }

    /**
     * @param  array<int>|null  $scopeAccommodationIds  When null, all accommodations.
     * @return array<string, list<array{id: int, name: string}>>
     */
    public function groupAccommodationsByKey(?array $scopeAccommodationIds = null): array
    {
        return $this->policyAccommodationsByKey(VeteranGroup::class, $scopeAccommodationIds);
    }

    /**
     * @param  array<int>|null  $scopeAccommodationIds  When null, all accommodations.
     * @return array<string, list<array{id: int, name: string}>>
     */
    public function serviceAccommodationsByKey(?array $scopeAccommodationIds = null): array
    {
        return $this->policyAccommodationsByKey(ServiceCatalog::class, $scopeAccommodationIds);
    }

    /**
     * @param  array<int>|null  $scopeAccommodationIds
     * @return array<string, array<string, list<array{id: int, name: string}>>>  serviceKey => variantKey => accommodations
     */
    public function variantAccommodationsByServiceKey(?array $scopeAccommodationIds = null): array
    {
        $ids = $this->resolveAccommodationIds($scopeAccommodationIds);

        if ($ids === []) {
            return [];
        }

        $services = ServiceCatalog::query()
            ->whereIn('accommodation_id', $ids)
            ->with(['accommodation:id,name', 'variants'])
            ->orderBy('accommodation_id')
            ->get();

        $map = [];
        foreach ($services as $service) {
            foreach ($service->variants as $variant) {
                $map[$service->key][$variant->key] ??= [];
                $accId = (int) $service->accommodation_id;

                if (!collect($map[$service->key][$variant->key])->contains('id', $accId)) {
                    $map[$service->key][$variant->key][] = [
                        'id'   => $accId,
                        'name' => (string) ($service->accommodation?->name ?? ''),
                    ];
                }
            }
        }

        return $map;
    }

    /**
     * @param  array<int>|null  $accommodationIds  When null, all accommodations.
     * @return list<int>
     */
    public function resolveAccommodationIds(?array $accommodationIds): array
    {
        if ($accommodationIds === null) {
            return Accommodation::query()->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $allowed = Accommodation::query()->pluck('id')->map(fn ($id) => (int) $id)->all();

        return array_values(array_intersect(array_map('intval', $accommodationIds), $allowed));
    }

    /**
     * @param  class-string<VeteranGroup|ServiceCatalog>  $modelClass
     * @param  array<int>|null  $scopeAccommodationIds
     * @return array<string, list<array{id: int, name: string}>>
     */
    private function policyAccommodationsByKey(string $modelClass, ?array $scopeAccommodationIds): array
    {
        $ids = $this->resolveAccommodationIds($scopeAccommodationIds);

        if ($ids === []) {
            return [];
        }

        $rows = $modelClass::query()
            ->whereIn('accommodation_id', $ids)
            ->with('accommodation:id,name')
            ->orderBy('accommodation_id')
            ->get(['id', 'accommodation_id', 'key']);

        $map = [];
        foreach ($rows as $row) {
            $map[$row->key] ??= [];
            $accId = (int) $row->accommodation_id;
            if (!collect($map[$row->key])->contains('id', $accId)) {
                $map[$row->key][] = [
                    'id'   => $accId,
                    'name' => (string) ($row->accommodation?->name ?? ''),
                ];
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int>|null  $accommodationIds  When null, all accommodations.
     */
    public function syncGroupByKey(string $key, array $attributes, ?array $accommodationIds = null): void
    {
        $this->ensureAllAccommodationsHavePolicy();

        $query = VeteranGroup::query()->where('key', $key);

        if ($accommodationIds !== null) {
            $query->whereIn('accommodation_id', $this->resolveAccommodationIds($accommodationIds));
        }

        $query->update($attributes);

        $this->clearCachesFor($accommodationIds);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int>|null  $accommodationIds  When null, all accommodations.
     */
    public function syncServiceByKey(string $key, array $attributes, ?array $accommodationIds = null): void
    {
        $this->ensureAllAccommodationsHavePolicy();

        $query = ServiceCatalog::query()->where('key', $key);

        if ($accommodationIds !== null) {
            $query->whereIn('accommodation_id', $this->resolveAccommodationIds($accommodationIds));
        }

        $query->update($attributes);

        $this->clearCachesFor($accommodationIds);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int>|null  $accommodationIds  When null, all accommodations.
     */
    public function syncDiscountByKeys(string $groupKey, string $serviceKey, array $attributes, ?array $accommodationIds = null): void
    {
        $this->ensureAllAccommodationsHavePolicy();

        $resolvedIds = $accommodationIds !== null
            ? $this->resolveAccommodationIds($accommodationIds)
            : null;

        $groupsQuery = VeteranGroup::query()->where('key', $groupKey);
        $servicesQuery = ServiceCatalog::query()->where('key', $serviceKey);

        if ($resolvedIds !== null) {
            $groupsQuery->whereIn('accommodation_id', $resolvedIds);
            $servicesQuery->whereIn('accommodation_id', $resolvedIds);
        }

        $groups = $groupsQuery->get(['id', 'accommodation_id']);
        $services = $servicesQuery->get(['id', 'accommodation_id']);

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

        $this->clearCachesFor($accommodationIds);
    }

    /**
     * @param  array<string, mixed>  $groupData  Must include unique `key`
     * @param  array<int>|null  $accommodationIds  When null, all accommodations.
     */
    public function addGroupToAllAccommodations(array $groupData, ?array $accommodationIds = null): void
    {
        $this->ensureAllAccommodationsHavePolicy();

        foreach ($this->resolveAccommodationIds($accommodationIds) as $accommodationId) {
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

        $this->clearCachesFor($accommodationIds);
    }

    /**
     * @param  array<string, mixed>  $serviceData  Must include unique `key`
     * @param  array<int>|null  $accommodationIds  When null, all accommodations.
     */
    public function addServiceToAllAccommodations(array $serviceData, ?array $accommodationIds = null): void
    {
        $this->ensureAllAccommodationsHavePolicy();

        foreach ($this->resolveAccommodationIds($accommodationIds) as $accommodationId) {
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

        $this->clearCachesFor($accommodationIds);
    }

    /**
     * @param  array<int, array<string, mixed>>  $variants
     * @param  array<int>|null  $accommodationIds  When null, all accommodations.
     */
    public function syncVariantsForService(string $serviceKey, array $variants, ?array $accommodationIds = null): void
    {
        $this->ensureAllAccommodationsHavePolicy();

        $servicesQuery = ServiceCatalog::query()->where('key', $serviceKey);

        if ($accommodationIds !== null) {
            $servicesQuery->whereIn('accommodation_id', $this->resolveAccommodationIds($accommodationIds));
        }

        $services = $servicesQuery->get(['id', 'accommodation_id']);

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

        $this->clearCachesFor($accommodationIds);
    }

    /**
     * @param  array<string, mixed>  $variantData  Must include unique `key`
     * @param  array<int>|null  $accommodationIds  When null, all accommodations.
     */
    public function addVariantToAllAccommodations(string $serviceKey, array $variantData, ?array $accommodationIds = null): void
    {
        $this->ensureAllAccommodationsHavePolicy();

        $servicesQuery = ServiceCatalog::query()->where('key', $serviceKey);

        if ($accommodationIds !== null) {
            $servicesQuery->whereIn('accommodation_id', $this->resolveAccommodationIds($accommodationIds));
        }

        foreach ($servicesQuery->get() as $service) {
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

        $this->clearCachesFor($accommodationIds);
    }

    /**
     * @param  array<int>|null  $accommodationIds  When null, all accommodations.
     */
    public function removeVariantFromAllAccommodations(string $serviceKey, string $variantKey, ?array $accommodationIds = null): void
    {
        $servicesQuery = ServiceCatalog::query()->where('key', $serviceKey);

        if ($accommodationIds !== null) {
            $servicesQuery->whereIn('accommodation_id', $this->resolveAccommodationIds($accommodationIds));
        }

        $serviceIds = $servicesQuery->pluck('id');

        ServiceCatalogVariant::query()
            ->whereIn('service_catalog_id', $serviceIds)
            ->where('key', $variantKey)
            ->delete();

        $this->clearCachesFor($accommodationIds);
    }

    /**
     * @param  array<int>|null  $accommodationIds  When null, all accommodations.
     */
    public function removeServiceFromAllAccommodations(string $serviceKey, ?array $accommodationIds = null): void
    {
        $query = ServiceCatalog::query()->where('key', $serviceKey);

        if ($accommodationIds !== null) {
            $query->whereIn('accommodation_id', $this->resolveAccommodationIds($accommodationIds));
        }

        $query->delete();

        $this->clearCachesFor($accommodationIds);
    }

    public function copyGlobalPolicyToAccommodation(Accommodation|int $accommodation): void
    {
        $this->ensureAllAccommodationsHavePolicy();

        $target = $accommodation instanceof Accommodation
            ? $accommodation
            : Accommodation::query()->findOrFail($accommodation);
        $targetId = $target->id;

        $referenceId = $this->referenceAccommodationId();
        if (!$referenceId) {
            $this->provisioner->restoreHardcodedDefaultsForAccommodation($target);

            return;
        }

        $this->replaceAccommodationPolicyFromReference($targetId, $referenceId);

        $target->update(['veteran_policy_auto_seed' => true]);
        $this->policyService->clearCache($targetId);
    }

    private function replaceAccommodationPolicyFromReference(int $targetId, int $referenceId): void
    {
        DB::transaction(function () use ($targetId, $referenceId) {
            VeteranGroup::query()->where('accommodation_id', $targetId)->delete();
            ServiceCatalog::query()->where('accommodation_id', $targetId)->delete();

            $targetGroupIdByKey = [];
            foreach (VeteranGroup::query()->forAccommodation($referenceId)->ordered()->get() as $refGroup) {
                $group = $refGroup->replicate();
                $group->accommodation_id = $targetId;
                $group->save();
                $targetGroupIdByKey[$group->key] = $group->id;
            }

            $targetServiceIdByKey = [];
            foreach (ServiceCatalog::query()->forAccommodation($referenceId)->ordered()->with('variants')->get() as $refService) {
                $service = $refService->replicate();
                $service->accommodation_id = $targetId;
                $service->save();
                $targetServiceIdByKey[$service->key] = $service->id;

                foreach ($refService->variants as $refVariant) {
                    $variant = $refVariant->replicate();
                    $variant->service_catalog_id = $service->id;
                    $variant->save();
                }
            }

            $refGroups = VeteranGroup::query()
                ->forAccommodation($referenceId)
                ->get(['id', 'key'])
                ->keyBy('id');
            $refServices = ServiceCatalog::query()
                ->forAccommodation($referenceId)
                ->get(['id', 'key'])
                ->keyBy('id');

            $discounts = VeteranGroupServiceDiscount::query()
                ->whereIn('veteran_group_id', $refGroups->keys())
                ->get();

            foreach ($discounts as $refDiscount) {
                $groupKey = $refGroups->get($refDiscount->veteran_group_id)?->key;
                $serviceKey = $refServices->get($refDiscount->service_catalog_id)?->key;
                if (!$groupKey || !$serviceKey) {
                    continue;
                }

                $targetGroupId = $targetGroupIdByKey[$groupKey] ?? null;
                $targetServiceId = $targetServiceIdByKey[$serviceKey] ?? null;
                if (!$targetGroupId || !$targetServiceId) {
                    continue;
                }

                $discount = $refDiscount->replicate();
                $discount->veteran_group_id = $targetGroupId;
                $discount->service_catalog_id = $targetServiceId;
                $discount->save();
            }
        });
    }

    public function clearAllCaches(): void
    {
        $this->clearCachesFor(null);
    }

    /**
     * @param  array<int>|null  $accommodationIds  When null, all accommodations.
     */
    public function clearCachesFor(?array $accommodationIds): void
    {
        foreach ($this->resolveAccommodationIds($accommodationIds) as $accommodationId) {
            $this->policyService->clearCache($accommodationId);
        }
    }
}
