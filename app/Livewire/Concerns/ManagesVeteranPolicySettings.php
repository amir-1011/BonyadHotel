<?php

namespace App\Livewire\Concerns;

use App\Models\Accommodation;
use App\Models\ServiceCatalog;
use App\Models\ServiceCatalogVariant;
use App\Models\VeteranGroup;
use App\Models\VeteranGroupServiceDiscount;
use App\Services\VeteranPolicyProvisioner;
use App\Services\VeteranPolicyService;
use App\Services\ServiceDiscountTierEngine;
use App\Services\AccommodationDiscountTierEngine;

trait ManagesVeteranPolicySettings
{
    use ManagesDiscountTierMatrix;
    use ManagesAccommodationDiscountTiers;
    use AssertsHostPermissions;
    public Accommodation $accommodation;

    public string $tab = 'groups';

    /** @var array<int, array<string, mixed>> */
    public array $groups = [];

    /** @var array<int, array<string, mixed>> */
    public array $services = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    public array $discountMatrix = [];

    public string $newServiceName = '';
    public int $newServicePrice = 0;

    public string $newGroupLabel = '';
    public int $newGroupAccommodationDiscount = 0;

    /** @var array<int, array{name: string, price: int|string}> */
    public array $newVariantDrafts = [];

    protected function bootVeteranPolicySettings(Accommodation $accommodation): void
    {
        $this->accommodation = $accommodation;
        app(VeteranPolicyProvisioner::class)->seedForAccommodation($accommodation);
        $this->loadVeteranPolicyData();
    }

    public function loadVeteranPolicyData(): void
    {
        $accommodationId = $this->accommodation->id;

        $this->groups = VeteranGroup::query()
            ->forAccommodation($accommodationId)
            ->ordered()
            ->get()
            ->map(fn (VeteranGroup $g) => array_merge([
                'id'                     => $g->id,
                'key'                    => $g->key,
                'label'                  => $g->label,
                'nights_per_dependent'   => $g->nights_per_dependent,
                'max_nights_per_period'  => $g->max_nights_per_period,
                'period_months'          => $g->period_months,
                'weekly_free_sessions'   => $g->weekly_free_sessions,
                'usage_notes'            => $g->usage_notes ?? '',
                'is_active'              => $g->is_active,
            ], AccommodationDiscountTierEngine::groupRowFromPersistence([
                'accommodation_discount'            => $g->accommodation_discount,
                'use_tiered_accommodation_discount' => $g->use_tiered_accommodation_discount,
                'accommodation_discount_tiers'      => $g->accommodation_discount_tiers ?? [],
            ])))->values()->all();

        $this->services = ServiceCatalog::query()
            ->forAccommodation($accommodationId)
            ->ordered()
            ->with(['variants' => fn ($q) => $q->ordered()])
            ->get()
            ->mapWithKeys(fn (ServiceCatalog $s) => [
                $s->key => [
                    'id'                     => $s->id,
                    'key'                    => $s->key,
                    'name'                   => $s->name,
                    'default_price'          => $s->default_price,
                    'supports_free_sessions' => $s->supports_free_sessions,
                    'default_discount'       => $s->default_discount,
                    'min_discount'           => $s->min_discount,
                    'max_discount'           => $s->max_discount,
                    'is_active'              => $s->is_active,
                    'variants'               => $s->variants->map(fn (ServiceCatalogVariant $v) => [
                        'id'        => $v->id,
                        'key'       => $v->key,
                        'name'      => $v->name,
                        'price'     => $v->price,
                        'is_active' => $v->is_active,
                    ])->values()->all(),
                ],
            ])
            ->all();

        $this->pruneNewVariantDrafts();

        $this->discountMatrix = [];
        foreach (VeteranGroup::query()->forAccommodation($accommodationId)->ordered()->get() as $group) {
            foreach (ServiceCatalog::query()->forAccommodation($accommodationId)->ordered()->get() as $service) {
                $row = VeteranGroupServiceDiscount::firstOrCreate(
                    [
                        'veteran_group_id'   => $group->id,
                        'service_catalog_id' => $service->id,
                    ],
                    [
                        'discount_percentage'    => $service->default_discount,
                        'free_sessions_eligible' => false,
                        'weekly_free_sessions'   => 0,
                    ]
                );

                $this->discountMatrix[$group->key][$service->id] = array_merge(
                    ['id' => $row->id],
                    ServiceDiscountTierEngine::matrixRowFromPersistence([
                        'discount_percentage'    => $row->discount_percentage,
                        'free_sessions_eligible' => $row->free_sessions_eligible,
                        'weekly_free_sessions'   => $row->weekly_free_sessions,
                        'use_tiered_discount'    => $row->use_tiered_discount,
                        'discount_tiers'         => $row->discount_tiers ?? [],
                    ]),
                );
            }
        }
    }

    public function saveGroups(): void
    {
        $this->assertHostCan('accommodations.veteran-policy', 'edit');
        $this->validate(array_merge([
            'groups.*.label'                  => ['required', 'string', 'max:200'],
            'groups.*.accommodation_discount' => ['required', 'integer', 'min:0', 'max:100'],
            'groups.*.max_nights_per_period'  => ['required', 'integer', 'min:1', 'max:365'],
            'groups.*.period_months'          => ['required', 'integer', 'min:1', 'max:24'],
        ], $this->accommodationTierValidationRules()));

        foreach ($this->groups as $row) {
            $tierPersistence = AccommodationDiscountTierEngine::groupRowToPersistence($row);

            VeteranGroup::query()
                ->where('id', $row['id'])
                ->where('accommodation_id', $this->accommodation->id)
                ->update([
                    'label'                             => $row['label'],
                    'accommodation_discount'            => $tierPersistence['accommodation_discount'],
                    'use_tiered_accommodation_discount' => $tierPersistence['use_tiered_accommodation_discount'],
                    'accommodation_discount_tiers'      => $tierPersistence['accommodation_discount_tiers'],
                    'max_nights_per_period'             => $row['max_nights_per_period'],
                    'period_months'                     => $row['period_months'],
                    'usage_notes'                       => $row['usage_notes'] ?: null,
                    'is_active'                         => (bool) ($row['is_active'] ?? true),
                ]);
        }

        $this->clearVeteranPolicyCache();
        $this->dispatch('toast', type: 'success', message: 'گروه‌های ایثارگری ذخیره شد.');
    }

    public function saveServices(): void
    {
        $this->assertHostCan('accommodations.veteran-policy', 'edit');
        $this->validate([
            'services.*.name'             => ['required', 'string', 'max:200'],
            'services.*.variants.*.name'  => ['required', 'string', 'max:200'],
            'services.*.variants.*.price' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($this->services as $row) {
            ServiceCatalog::query()
                ->where('id', $row['id'])
                ->where('accommodation_id', $this->accommodation->id)
                ->update([
                    'name'                   => $row['name'],
                    'min_discount'           => $row['min_discount'] !== '' && $row['min_discount'] !== null
                        ? (int) $row['min_discount'] : null,
                    'max_discount'           => $row['max_discount'] !== '' && $row['max_discount'] !== null
                        ? (int) $row['max_discount'] : null,
                    'is_active'              => (bool) ($row['is_active'] ?? true),
                ]);

            $this->syncServiceVariants((int) $row['id'], $row['variants'] ?? []);
        }

        $this->clearVeteranPolicyCache();
        $this->loadVeteranPolicyData();
        $this->dispatch('toast', type: 'success', message: 'فهرست خدمات و انواع آن‌ها ذخیره شد.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $variants
     */
    protected function syncServiceVariants(int $serviceId, array $variants): void
    {
        $service = ServiceCatalog::query()
            ->where('id', $serviceId)
            ->where('accommodation_id', $this->accommodation->id)
            ->first();

        if (!$service) {
            return;
        }

        $keptIds = [];

        foreach ($variants as $index => $variant) {
            $payload = [
                'name'       => $variant['name'],
                'price'      => (int) $variant['price'],
                'sort_order' => $index + 1,
                'is_active'  => (bool) ($variant['is_active'] ?? true),
            ];

            if (!empty($variant['id'])) {
                ServiceCatalogVariant::query()
                    ->where('id', $variant['id'])
                    ->where('service_catalog_id', $service->id)
                    ->update($payload);
                $keptIds[] = (int) $variant['id'];
                continue;
            }

            $created = ServiceCatalogVariant::create(array_merge($payload, [
                'service_catalog_id' => $service->id,
                'key'                => !empty($variant['key'])
                    ? $variant['key']
                    : 'custom_variant_' . time() . '_' . $index,
            ]));
            $keptIds[] = $created->id;
        }

        ServiceCatalogVariant::query()
            ->where('service_catalog_id', $service->id)
            ->whereNotIn('id', $keptIds)
            ->delete();
    }

    public function addServiceVariant(?int $serviceId = null): void
    {
        $this->assertHostCan('accommodations.veteran-policy', 'edit');
        if ($serviceId === null) {
            return;
        }

        $this->validate([
            "newVariantDrafts.{$serviceId}.name"  => ['required', 'string', 'max:200'],
            "newVariantDrafts.{$serviceId}.price" => ['required', 'integer', 'min:0'],
        ]);

        $draft = $this->newVariantDrafts[$serviceId] ?? [];

        $service = ServiceCatalog::query()
            ->where('id', $serviceId)
            ->where('accommodation_id', $this->accommodation->id)
            ->firstOrFail();

        ServiceCatalogVariant::create([
            'service_catalog_id' => $service->id,
            'key'                => 'custom_variant_' . time(),
            'name'               => $draft['name'],
            'price'              => (int) $draft['price'],
            'sort_order'         => (int) $service->variants()->max('sort_order') + 1,
            'is_active'          => true,
        ]);

        $this->newVariantDrafts[$serviceId] = ['name' => '', 'price' => 0];
        $this->clearVeteranPolicyCache();
        $this->loadVeteranPolicyData();
        $this->dispatch('toast', type: 'success', message: 'نوع خدمت اضافه شد.');
    }

    public function removeServiceVariant(int $variantId): void
    {
        $this->assertHostCan('accommodations.veteran-policy', 'edit');
        ServiceCatalogVariant::query()
            ->where('id', $variantId)
            ->whereHas('serviceCatalog', fn ($q) => $q->where('accommodation_id', $this->accommodation->id))
            ->delete();

        $this->clearVeteranPolicyCache();
        $this->loadVeteranPolicyData();
        $this->dispatch('toast', type: 'success', message: 'نوع خدمت حذف شد.');
    }

    public function saveDiscountMatrix(): void
    {
        $this->assertHostCan('accommodations.veteran-policy', 'edit');
        $this->validate($this->discountMatrixValidationRules());

        foreach ($this->discountMatrix as $groupKey => $serviceRows) {
            $group = VeteranGroup::query()
                ->where('accommodation_id', $this->accommodation->id)
                ->where('key', $groupKey)
                ->first();
            if (!$group) {
                continue;
            }

            foreach ($serviceRows as $serviceId => $row) {
                $payload = ServiceDiscountTierEngine::matrixRowToPersistence($row);
                VeteranGroupServiceDiscount::where('id', $row['id'])->update($payload);
            }
        }

        $this->clearVeteranPolicyCache();
        $this->dispatch('toast', type: 'success', message: 'ماتریس تخفیف خدمات ذخیره شد.');
    }

    public function addCustomGroup(): void
    {
        $this->assertHostCan('accommodations.veteran-policy', 'edit');
        $this->validate([
            'newGroupLabel'                 => ['required', 'string', 'max:200'],
            'newGroupAccommodationDiscount' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $group = VeteranGroup::create([
            'accommodation_id'       => $this->accommodation->id,
            'key'                    => 'custom_group_' . time(),
            'label'                  => $this->newGroupLabel,
            'accommodation_discount' => $this->newGroupAccommodationDiscount,
            'nights_per_dependent'   => 6,
            'max_nights_per_period'  => 3,
            'period_months'          => 6,
            'weekly_free_sessions'   => 0,
            'usage_notes'            => null,
            'sort_order'             => (int) VeteranGroup::query()
                ->forAccommodation($this->accommodation->id)
                ->max('sort_order') + 1,
            'is_active'              => true,
        ]);

        foreach (ServiceCatalog::query()->forAccommodation($this->accommodation->id)->ordered()->get() as $service) {
            VeteranGroupServiceDiscount::create([
                'veteran_group_id'       => $group->id,
                'service_catalog_id'     => $service->id,
                'discount_percentage'    => $service->default_discount,
                'free_sessions_eligible' => false,
                'weekly_free_sessions'   => 0,
            ]);
        }

        $this->newGroupLabel = '';
        $this->newGroupAccommodationDiscount = 0;
        $this->clearVeteranPolicyCache();
        $this->loadVeteranPolicyData();
        $this->dispatch('toast', type: 'success', message: 'گروه ایثارگری جدید اضافه شد. تخفیف خدمات را در تب «تخفیف خدمات» تنظیم کنید.');
    }

    public function addCustomService(): void
    {
        $this->assertHostCan('accommodations.veteran-policy', 'edit');
        $this->validate([
            'newServiceName' => ['required', 'string', 'max:200'],
        ]);

        $service = ServiceCatalog::create([
            'accommodation_id' => $this->accommodation->id,
            'key'              => 'custom_' . time(),
            'name'             => $this->newServiceName,
            'default_price'    => 0,
            'default_discount' => 0,
            'sort_order'       => (int) ServiceCatalog::query()
                ->forAccommodation($this->accommodation->id)
                ->max('sort_order') + 1,
            'is_active'        => true,
        ]);

        foreach (VeteranGroup::query()->forAccommodation($this->accommodation->id)->get() as $group) {
            VeteranGroupServiceDiscount::create([
                'veteran_group_id'       => $group->id,
                'service_catalog_id'     => $service->id,
                'discount_percentage'    => 0,
                'free_sessions_eligible' => false,
                'weekly_free_sessions'   => 0,
            ]);
        }

        $this->newServiceName = '';
        $this->clearVeteranPolicyCache();
        $this->loadVeteranPolicyData();
        $this->dispatch('toast', type: 'success', message: 'خدمت جدید اضافه شد. انواع و قیمت را در بخش همان خدمت تعریف کنید.');
    }

    public function removeVeteranGroup(int $groupId): void
    {
        $this->assertHostCan('accommodations.veteran-policy', 'edit');
        VeteranGroup::query()
            ->where('id', $groupId)
            ->where('accommodation_id', $this->accommodation->id)
            ->delete();

        app(VeteranPolicyProvisioner::class)->markAutoSeedDisabledIfPolicyEmpty($this->accommodation->id);
        $this->clearVeteranPolicyCache();
        $this->loadVeteranPolicyData();
        $this->dispatch('toast', type: 'success', message: 'گروه ایثارگری حذف شد.');
    }

    public function removeService(int $serviceId): void
    {
        $this->assertHostCan('accommodations.veteran-policy', 'edit');
        ServiceCatalog::query()
            ->where('id', $serviceId)
            ->where('accommodation_id', $this->accommodation->id)
            ->delete();

        app(VeteranPolicyProvisioner::class)->markAutoSeedDisabledIfPolicyEmpty($this->accommodation->id);
        $this->clearVeteranPolicyCache();
        $this->loadVeteranPolicyData();
        $this->dispatch('toast', type: 'success', message: 'خدمت حذف شد.');
    }

    public function clearAllVeteranGroups(): void
    {
        $this->assertHostCan('accommodations.veteran-policy', 'edit');
        app(VeteranPolicyProvisioner::class)->clearGroupsForAccommodation($this->accommodation);
        $this->accommodation->refresh();
        $this->clearVeteranPolicyCache();
        $this->loadVeteranPolicyData();
        $this->dispatch('toast', type: 'success', message: 'همه گروه‌های ایثارگری این اقامتگاه پاک شد.');
    }

    public function clearAllServices(): void
    {
        $this->assertHostCan('accommodations.veteran-policy', 'edit');
        app(VeteranPolicyProvisioner::class)->clearServicesForAccommodation($this->accommodation);
        $this->accommodation->refresh();
        $this->clearVeteranPolicyCache();
        $this->loadVeteranPolicyData();
        $this->dispatch('toast', type: 'success', message: 'همه خدمات این اقامتگاه پاک شد.');
    }

    public function restoreDefaultVeteranPolicy(): void
    {
        app(VeteranPolicyProvisioner::class)->restoreDefaultsForAccommodation($this->accommodation);
        $this->accommodation->refresh();
        $this->clearVeteranPolicyCache();
        $this->loadVeteranPolicyData();
        $this->dispatch('toast', type: 'success', message: 'تنظیمات سراسری ایثارگری روی این اقامتگاه بازگردانی شد.');
    }

    protected function clearVeteranPolicyCache(): void
    {
        app(VeteranPolicyService::class)->clearCache($this->accommodation->id);
    }

    /** @param  array<int, array{name: string, price: int|string}>  $newVariantDrafts */
    protected function pruneNewVariantDrafts(): void
    {
        $validServiceIds = collect($this->services)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->newVariantDrafts = array_intersect_key(
            $this->newVariantDrafts,
            array_flip($validServiceIds),
        );
    }
}
