<?php

namespace App\Livewire\Admin;

use App\Models\Accommodation;
use App\Models\ServiceCatalog;
use App\Models\ServiceCatalogVariant;
use App\Models\VeteranGroup;
use App\Models\VeteranGroupServiceDiscount;
use App\Livewire\Concerns\ManagesDiscountTierMatrix;
use App\Services\ServiceDiscountTierEngine;
use App\Services\VeteranPolicyBroadcastService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'تنظیمات ایثارگری', 'pageTitle' => 'تنظیمات ایثارگری و خدمات'])]
class VeteranPolicySettings extends Component
{
    use ManagesDiscountTierMatrix;

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
        $broadcast->ensureAllAccommodationsHavePolicy();
        $this->loadData($broadcast);
    }

    public function loadData(?VeteranPolicyBroadcastService $broadcast = null): void
    {
        $broadcast ??= app(VeteranPolicyBroadcastService::class);
        $referenceId = $broadcast->referenceAccommodationId();

        if (!$referenceId) {
            $this->groups = [];
            $this->services = [];
            $this->discountMatrix = [];

            return;
        }

        $this->groups = VeteranGroup::query()
            ->forAccommodation($referenceId)
            ->ordered()
            ->get()
            ->map(fn (VeteranGroup $g) => [
                'key'                    => $g->key,
                'label'                  => $g->label,
                'accommodation_discount' => $g->accommodation_discount,
                'nights_per_dependent'   => $g->nights_per_dependent,
                'max_nights_per_period'  => $g->max_nights_per_period,
                'period_months'          => $g->period_months,
                'weekly_free_sessions'   => $g->weekly_free_sessions,
                'usage_notes'            => $g->usage_notes ?? '',
                'is_active'              => $g->is_active,
            ])->values()->all();

        $this->services = ServiceCatalog::query()
            ->forAccommodation($referenceId)
            ->ordered()
            ->with(['variants' => fn ($q) => $q->ordered()])
            ->get()
            ->map(fn (ServiceCatalog $s) => [
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
            ])->values()->all();

        $this->discountMatrix = [];
        foreach (VeteranGroup::query()->forAccommodation($referenceId)->ordered()->get() as $group) {
            foreach (ServiceCatalog::query()->forAccommodation($referenceId)->ordered()->get() as $service) {
                $row = VeteranGroupServiceDiscount::query()
                    ->where('veteran_group_id', $group->id)
                    ->where('service_catalog_id', $service->id)
                    ->first();

                $this->discountMatrix[$group->key][$service->key] = [
                    'discount_percentage'    => $row?->discount_percentage ?? $service->default_discount,
                    'free_sessions_eligible' => (bool) ($row?->free_sessions_eligible ?? false),
                    'weekly_free_sessions'   => (int) ($row?->weekly_free_sessions ?? 0),
                    'use_tiered_discount'    => (bool) ($row?->use_tiered_discount ?? false),
                    'discount_tiers'         => $row?->discount_tiers ?? [],
                ];
            }
        }
    }

    public function saveGroups(VeteranPolicyBroadcastService $broadcast): void
    {
        $this->validate([
            'groups.*.label'                  => ['required', 'string', 'max:200'],
            'groups.*.accommodation_discount' => ['required', 'integer', 'min:0', 'max:100'],
            'groups.*.nights_per_dependent'   => ['required', 'integer', 'min:1', 'max:365'],
            'groups.*.max_nights_per_period'  => ['required', 'integer', 'min:1', 'max:365'],
            'groups.*.period_months'          => ['required', 'integer', 'min:1', 'max:24'],
        ]);

        foreach ($this->groups as $row) {
            $broadcast->syncGroupByKey($row['key'], [
                'label'                  => $row['label'],
                'accommodation_discount' => $row['accommodation_discount'],
                'nights_per_dependent'   => $row['nights_per_dependent'],
                'max_nights_per_period'  => $row['max_nights_per_period'],
                'period_months'          => $row['period_months'],
                'usage_notes'            => $row['usage_notes'] ?: null,
                'is_active'              => (bool) ($row['is_active'] ?? true),
            ]);
        }

        $this->dispatch('toast', type: 'success', message: 'گروه‌های ایثارگری برای همه اقامتگاه‌ها ذخیره شد.');
    }

    public function saveServices(VeteranPolicyBroadcastService $broadcast): void
    {
        $this->validate([
            'services.*.name'             => ['required', 'string', 'max:200'],
            'services.*.variants.*.name'  => ['required', 'string', 'max:200'],
            'services.*.variants.*.price' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($this->services as $row) {
            $broadcast->syncServiceByKey($row['key'], [
                'name'                   => $row['name'],
                'min_discount'           => $row['min_discount'] !== '' && $row['min_discount'] !== null
                    ? (int) $row['min_discount'] : null,
                'max_discount'           => $row['max_discount'] !== '' && $row['max_discount'] !== null
                    ? (int) $row['max_discount'] : null,
                'is_active'              => (bool) ($row['is_active'] ?? true),
            ]);

            $broadcast->syncVariantsForService($row['key'], $row['variants'] ?? []);
        }

        $this->loadData($broadcast);
        $this->dispatch('toast', type: 'success', message: 'فهرست خدمات و انواع آن‌ها برای همه اقامتگاه‌ها ذخیره شد.');
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

        $serviceKey = collect($this->services)->firstWhere('id', $serviceId)['key'] ?? null;
        if (!$serviceKey) {
            return;
        }

        $draft = $this->newVariantDrafts[$serviceId] ?? [];
        $variantKey = 'custom_variant_' . time();
        $broadcast->addVariantToAllAccommodations($serviceKey, [
            'key'       => $variantKey,
            'name'      => $draft['name'],
            'price'     => (int) $draft['price'],
            'is_active' => true,
        ]);

        $this->newVariantDrafts[$serviceId] = ['name' => '', 'price' => 0];
        $this->loadData($broadcast);
        $this->dispatch('toast', type: 'success', message: 'نوع خدمت به همه اقامتگاه‌ها اضافه شد.');
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
        );

        $this->loadData($broadcast);
        $this->dispatch('toast', type: 'success', message: 'نوع خدمت از همه اقامتگاه‌ها حذف شد.');
    }

    public function saveDiscountMatrix(VeteranPolicyBroadcastService $broadcast): void
    {
        $this->validate($this->discountMatrixValidationRules());

        foreach ($this->discountMatrix as $groupKey => $serviceRows) {
            foreach ($serviceRows as $serviceKey => $row) {
                $broadcast->syncDiscountByKeys(
                    $groupKey,
                    $serviceKey,
                    ServiceDiscountTierEngine::matrixRowToPersistence($row),
                );
            }
        }

        $this->loadData($broadcast);
        $this->dispatch('toast', type: 'success', message: 'ماتریس تخفیف خدمات برای همه اقامتگاه‌ها ذخیره شد.');
    }

    public function addCustomGroup(VeteranPolicyBroadcastService $broadcast): void
    {
        $this->validate([
            'newGroupLabel'                 => ['required', 'string', 'max:200'],
            'newGroupAccommodationDiscount' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

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
        ]);

        $this->newGroupLabel = '';
        $this->newGroupAccommodationDiscount = 0;
        $this->loadData($broadcast);
        $this->dispatch('toast', type: 'success', message: 'گروه ایثارگری جدید به همه اقامتگاه‌ها اضافه شد. تخفیف خدمات را در تب «تخفیف خدمات» تنظیم کنید.');
    }

    public function addCustomService(VeteranPolicyBroadcastService $broadcast): void
    {
        $this->validate([
            'newServiceName' => ['required', 'string', 'max:200'],
        ]);

        $broadcast->addServiceToAllAccommodations([
            'key'              => 'custom_' . time(),
            'name'             => $this->newServiceName,
            'default_price'    => 0,
            'default_discount' => 0,
            'sort_order'       => (int) ServiceCatalog::max('sort_order') + 1,
            'is_active'        => true,
        ]);

        $this->newServiceName = '';
        $this->loadData($broadcast);
        $this->dispatch('toast', type: 'success', message: 'خدمت جدید به همه اقامتگاه‌ها اضافه شد. انواع و قیمت را در بخش همان خدمت تعریف کنید.');
    }

    public function render()
    {
        $accommodationCount = Accommodation::count();

        return view('livewire.admin.veteran-policy-settings', compact('accommodationCount'));
    }
}
