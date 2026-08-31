<?php

namespace App\Livewire\Admin;

use App\Models\Accommodation;
use App\Models\ServiceCatalog;
use App\Models\ServiceCatalogVariant;
use App\Models\VeteranGroup;
use App\Models\VeteranGroupServiceDiscount;
use App\Livewire\Concerns\ManagesDashboardAccommodationFilter;
use App\Livewire\Concerns\ManagesDiscountTierMatrix;
use App\Livewire\Concerns\ManagesAccommodationDiscountTiers;
use App\Services\AccommodationDiscountTierEngine;
use App\Services\ServiceDiscountTierEngine;
use App\Services\VeteranPolicyBroadcastService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'تعاریف اولیه', 'pageTitle' => 'تعاریف اولیه'])]
class VeteranPolicySettings extends Component
{
    use ManagesDashboardAccommodationFilter {
        onDashboardAccommodationFilterChanged as protected triggerDashboardAccommodationFilterChanged;
    }
    use ManagesDiscountTierMatrix;
    use ManagesAccommodationDiscountTiers;

    public string $tab = 'groups';

    /** @var array<int, array<string, mixed>> */
    public array $groups = [];

    /** @var array<int, array<string, mixed>> */
    public array $services = [];

    /** @var array<string, array<string, array<string, mixed>>> groupKey => serviceKey => row */
    public array $discountMatrix = [];

    public string $newServiceName = '';
    public int $newServicePrice = 0;

    public string $newGroupLabel = '';
    public int $newGroupAccommodationDiscount = 0;

    /** @var array<int, array{name: string, price: int|string}> */
    public array $newVariantDrafts = [];

    public function mount(VeteranPolicyBroadcastService $broadcast): void
    {
        $this->bootDashboardAccommodationFilter();
        $broadcast->ensureAllAccommodationsHavePolicy();
        $this->loadData($broadcast);
    }

    protected function resolveDashboardAccommodationOptions(): array
    {
        return Accommodation::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Accommodation $acc) => ['id' => (int) $acc->id, 'name' => (string) $acc->name])
            ->values()
            ->all();
    }

    protected function onDashboardAccommodationFilterChanged(): void
    {
        $this->triggerDashboardAccommodationFilterChanged();
        $this->loadData();
    }

    /** @return array<int> */
    protected function scopedAccommodationIds(): array
    {
        return $this->effectiveDashboardAccommodationIds();
    }

    protected function isAllAccommodationsSelected(): bool
    {
        return $this->dashboardAccommodationAllSelected;
    }

    public function loadData(?VeteranPolicyBroadcastService $broadcast = null): void
    {
        $broadcast ??= app(VeteranPolicyBroadcastService::class);
        $scopedIds = $this->scopedAccommodationIds();
        $referenceId = $broadcast->referenceAccommodationId($scopedIds);

        if (!$referenceId || $scopedIds === []) {
            $this->groups = [];
            $this->services = [];
            $this->discountMatrix = [];

            return;
        }

        $groupKeys = VeteranGroup::query()
            ->whereIn('accommodation_id', $scopedIds)
            ->orderBy('sort_order')
            ->pluck('key')
            ->unique()
            ->values()
            ->all();

        $groupsByKey = VeteranGroup::query()
            ->whereIn('accommodation_id', $scopedIds)
            ->ordered()
            ->get()
            ->groupBy('key');

        $this->groups = collect($groupKeys)
            ->map(function (string $key) use ($groupsByKey, $referenceId) {
                $rows = $groupsByKey->get($key, collect());
                $g = $rows->firstWhere('accommodation_id', $referenceId) ?? $rows->first();
                if (!$g) {
                    return null;
                }

                return array_merge([
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
                ]));
            })
            ->filter()
            ->values()
            ->all();

        $serviceKeys = ServiceCatalog::query()
            ->whereIn('accommodation_id', $scopedIds)
            ->orderBy('sort_order')
            ->pluck('key')
            ->unique()
            ->values()
            ->all();

        $servicesByKey = ServiceCatalog::query()
            ->whereIn('accommodation_id', $scopedIds)
            ->ordered()
            ->with(['variants' => fn ($q) => $q->ordered()])
            ->get()
            ->groupBy('key');

        $this->services = collect($serviceKeys)
            ->mapWithKeys(function (string $key) use ($servicesByKey, $referenceId) {
                $rows = $servicesByKey->get($key, collect());
                $s = $rows->firstWhere('accommodation_id', $referenceId) ?? $rows->first();
                if (!$s) {
                    return [];
                }

                return [
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
                ];
            })
            ->all();

        $this->pruneNewVariantDrafts();

        $this->discountMatrix = [];
        foreach ($this->groups as $groupRow) {
            $groupKey = $groupRow['key'];
            foreach ($this->services as $serviceRow) {
                $serviceKey = $serviceRow['key'];

                $group = VeteranGroup::query()
                    ->where('accommodation_id', $referenceId)
                    ->where('key', $groupKey)
                    ->first();
                $service = ServiceCatalog::query()
                    ->where('accommodation_id', $referenceId)
                    ->where('key', $serviceKey)
                    ->first();

                if (!$group || !$service) {
                    continue;
                }

                $row = VeteranGroupServiceDiscount::query()
                    ->where('veteran_group_id', $group->id)
                    ->where('service_catalog_id', $service->id)
                    ->first();

                $this->discountMatrix[$groupKey][$serviceKey] = ServiceDiscountTierEngine::matrixRowFromPersistence([
                    'discount_percentage'    => $row?->discount_percentage ?? $service->default_discount,
                    'free_sessions_eligible' => (bool) ($row?->free_sessions_eligible ?? false),
                    'weekly_free_sessions'   => (int) ($row?->weekly_free_sessions ?? 0),
                    'use_tiered_discount'    => (bool) ($row?->use_tiered_discount ?? false),
                    'discount_tiers'         => $row?->discount_tiers ?? [],
                ]);
            }
        }
    }

    protected function scopedSaveMessage(string $action): string
    {
        if ($this->isAllAccommodationsSelected()) {
            return "{$action} برای همه اقامتگاه‌ها ذخیره شد.";
        }

        $count = count($this->scopedAccommodationIds());

        return "{$action} برای {$count} اقامتگاه انتخاب‌شده ذخیره شد.";
    }

    public function saveGroups(VeteranPolicyBroadcastService $broadcast): void
    {
        $this->validate(array_merge([
            'groups.*.label'                  => ['required', 'string', 'max:200'],
            'groups.*.accommodation_discount' => ['required', 'integer', 'min:0', 'max:100'],
            'groups.*.max_nights_per_period'  => ['required', 'integer', 'min:1', 'max:365'],
            'groups.*.period_months'          => ['required', 'integer', 'min:1', 'max:24'],
        ], $this->accommodationTierValidationRules()));

        $scopedIds = $this->scopedAccommodationIds();

        foreach ($this->groups as $row) {
            $tierPersistence = AccommodationDiscountTierEngine::groupRowToPersistence($row);

            $broadcast->syncGroupByKey($row['key'], [
                'label'                             => $row['label'],
                'accommodation_discount'            => $tierPersistence['accommodation_discount'],
                'use_tiered_accommodation_discount' => $tierPersistence['use_tiered_accommodation_discount'],
                'accommodation_discount_tiers'      => $tierPersistence['accommodation_discount_tiers'],
                'max_nights_per_period'             => $row['max_nights_per_period'],
                'period_months'                     => $row['period_months'],
                'usage_notes'                       => $row['usage_notes'] ?: null,
                'is_active'                         => (bool) ($row['is_active'] ?? true),
            ], $scopedIds);
        }

        $this->dispatch('toast', type: 'success', message: $this->scopedSaveMessage('گروه‌های ایثارگری'));
    }

    public function saveServices(VeteranPolicyBroadcastService $broadcast): void
    {
        $this->validate([
            'services.*.name'             => ['required', 'string', 'max:200'],
            'services.*.variants.*.name'  => ['required', 'string', 'max:200'],
            'services.*.variants.*.price' => ['required', 'integer', 'min:0'],
        ]);

        $scopedIds = $this->scopedAccommodationIds();

        foreach ($this->services as $row) {
            $broadcast->syncServiceByKey($row['key'], [
                'name'                   => $row['name'],
                'min_discount'           => $row['min_discount'] !== '' && $row['min_discount'] !== null
                    ? (int) $row['min_discount'] : null,
                'max_discount'           => $row['max_discount'] !== '' && $row['max_discount'] !== null
                    ? (int) $row['max_discount'] : null,
                'is_active'              => (bool) ($row['is_active'] ?? true),
            ], $scopedIds);

            $broadcast->syncVariantsForService($row['key'], $row['variants'] ?? [], $scopedIds);
        }

        $this->loadData($broadcast);
        $this->dispatch('toast', type: 'success', message: $this->scopedSaveMessage('فهرست خدمات و انواع آن‌ها'));
    }

    public function addServiceVariant(?int $serviceId, VeteranPolicyBroadcastService $broadcast): void
    {
        if ($serviceId === null) {
            return;
        }

        $this->validate([
            "newVariantDrafts.{$serviceId}.name"  => ['required', 'string', 'max:200'],
            "newVariantDrafts.{$serviceId}.price" => ['required', 'integer', 'min:0'],
        ]);

        $serviceKey = collect($this->services)->first(
            fn (array $service) => (int) ($service['id'] ?? 0) === $serviceId,
        )['key'] ?? null;
        if (!$serviceKey) {
            return;
        }

        $draft = $this->newVariantDrafts[$serviceId] ?? [];
        $variantKey = 'custom_variant_' . time();
        $scopedIds = $this->scopedAccommodationIds();

        $broadcast->addVariantToAllAccommodations($serviceKey, [
            'key'       => $variantKey,
            'name'      => $draft['name'],
            'price'     => (int) $draft['price'],
            'is_active' => true,
        ], $scopedIds);

        $this->newVariantDrafts[$serviceId] = ['name' => '', 'price' => 0];
        $this->loadData($broadcast);
        $this->dispatch('toast', type: 'success', message: $this->scopedSaveMessage('نوع خدمت'));
    }

    public function removeServiceVariant(int $variantId, VeteranPolicyBroadcastService $broadcast): void
    {
        $variant = ServiceCatalogVariant::query()->with('serviceCatalog')->find($variantId);
        if (!$variant?->serviceCatalog) {
            return;
        }

        $broadcast->removeVariantFromAllAccommodations(
            $variant->serviceCatalog->key,
            $variant->key,
            $this->scopedAccommodationIds(),
        );

        $this->loadData($broadcast);
        $this->dispatch('toast', type: 'success', message: $this->scopedSaveMessage('حذف نوع خدمت'));
    }

    public function removeService(string $serviceKey, VeteranPolicyBroadcastService $broadcast): void
    {
        if ($serviceKey === '') {
            return;
        }

        $broadcast->removeServiceFromAllAccommodations($serviceKey, $this->scopedAccommodationIds());

        $this->loadData($broadcast);
        $this->dispatch('toast', type: 'success', message: $this->scopedSaveMessage('حذف خدمت'));
    }

    public function saveDiscountMatrix(VeteranPolicyBroadcastService $broadcast): void
    {
        $this->validate($this->discountMatrixValidationRules());

        $scopedIds = $this->scopedAccommodationIds();

        foreach ($this->discountMatrix as $groupKey => $serviceRows) {
            foreach ($serviceRows as $serviceKey => $row) {
                $broadcast->syncDiscountByKeys(
                    $groupKey,
                    $serviceKey,
                    ServiceDiscountTierEngine::matrixRowToPersistence($row),
                    $scopedIds,
                );
            }
        }

        $this->loadData($broadcast);
        $this->dispatch('toast', type: 'success', message: $this->scopedSaveMessage('ماتریس تخفیف خدمات'));
    }

    public function addCustomGroup(VeteranPolicyBroadcastService $broadcast): void
    {
        $this->validate([
            'newGroupLabel'                 => ['required', 'string', 'max:200'],
            'newGroupAccommodationDiscount' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $scopedIds = $this->scopedAccommodationIds();
        if ($scopedIds === []) {
            $this->dispatch('toast', type: 'error', message: 'حداقل یک اقامتگاه را انتخاب کنید.');

            return;
        }

        $key = 'custom_group_' . time();
        $broadcast->addGroupToAllAccommodations([
            'key'                    => $key,
            'label'                  => $this->newGroupLabel,
            'accommodation_discount' => $this->newGroupAccommodationDiscount,
            'nights_per_dependent'   => 6,
            'max_nights_per_period'  => 3,
            'period_months'          => 6,
            'weekly_free_sessions'   => 0,
            'usage_notes'            => null,
            'sort_order'             => (int) VeteranGroup::max('sort_order') + 1,
            'is_active'              => true,
        ], $scopedIds);

        $this->newGroupLabel = '';
        $this->newGroupAccommodationDiscount = 0;
        $this->loadData($broadcast);
        $this->dispatch('toast', type: 'success', message: $this->scopedSaveMessage('گروه ایثارگری جدید') . ' تخفیف خدمات را در تب «تخفیف خدمات» تنظیم کنید.');
    }

    public function addCustomService(VeteranPolicyBroadcastService $broadcast): void
    {
        $this->validate([
            'newServiceName' => ['required', 'string', 'max:200'],
        ]);

        $scopedIds = $this->scopedAccommodationIds();
        if ($scopedIds === []) {
            $this->dispatch('toast', type: 'error', message: 'حداقل یک اقامتگاه را انتخاب کنید.');

            return;
        }

        $broadcast->addServiceToAllAccommodations([
            'key'              => 'custom_' . time(),
            'name'             => $this->newServiceName,
            'default_price'    => 0,
            'default_discount' => 0,
            'sort_order'       => (int) ServiceCatalog::max('sort_order') + 1,
            'is_active'        => true,
        ], $scopedIds);

        $this->newServiceName = '';
        $this->loadData($broadcast);
        $this->dispatch('toast', type: 'success', message: $this->scopedSaveMessage('خدمت جدید') . ' انواع و قیمت را در بخش همان خدمت تعریف کنید.');
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

    public function render(VeteranPolicyBroadcastService $broadcast)
    {
        $scopedIds = $this->scopedAccommodationIds();
        $totalAccommodations = count($this->dashboardAccommodationOptionList());
        $scopedCount = count($scopedIds);

        return view('livewire.admin.veteran-policy-settings', [
            'accommodationCount'          => $totalAccommodations,
            'scopedAccommodationCount'  => $scopedCount,
            'scopedAccommodationIds'    => $scopedIds,
            'isAllAccommodationsSelected' => $this->isAllAccommodationsSelected(),
            'groupAccommodationsByKey'    => $broadcast->groupAccommodationsByKey($scopedIds),
            'serviceAccommodationsByKey'  => $broadcast->serviceAccommodationsByKey($scopedIds),
            'variantAccommodationsByServiceKey' => $broadcast->variantAccommodationsByServiceKey($scopedIds),
            'filterKey'                   => $this->dashboardAccommodationFilterKey(),
            'dashboardAccommodationOptions' => $this->dashboardAccommodationOptionList(),
        ]);
    }
}
