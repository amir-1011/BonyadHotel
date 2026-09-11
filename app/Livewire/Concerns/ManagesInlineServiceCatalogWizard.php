<?php

namespace App\Livewire\Concerns;

use App\Models\ServiceCatalog;
use App\Models\ServiceCatalogVariant;
use App\Models\VeteranGroup;
use App\Models\VeteranGroupServiceDiscount;
use App\Services\ServiceDiscountTierEngine;
use App\Services\VeteranPolicyService;

trait ManagesInlineServiceCatalogWizard
{
    use ManagesDiscountTierMatrix;

    public bool $showInlineServiceCatalogModal = false;

    /** @var 'list'|'wizard' */
    public string $inlineCatalogScreen = 'list';

    public int $inlineWizardStep = 1;

    public ?int $inlineEditingServiceId = null;

    public string $inlineParentServiceName = '';

    /** @var array<int, array{id:int, name:string, variants_count:int, is_active:bool}> */
    public array $inlineCatalogCards = [];

    /** @var array<int, array<string, mixed>> */
    public array $inlineCatalogGroups = [];

    /** @var array<string, array<string, mixed>> */
    public array $inlineWizardServices = [];

    /** @var array<string, array<int|string, array<string, mixed>>> */
    public array $discountMatrix = [];

    /** @var array<int, array{name: string, price: int|string}> */
    public array $newVariantDrafts = [];

    protected function assertInlineCatalogCanEdit(): void
    {
        if ($this->panel === 'host') {
            $this->assertHostCan('accommodations.manual-service-sale', 'write');
        }
    }

    public function openInlineServiceCatalogModal(?int $serviceId = null): void
    {
        $this->assertInlineCatalogCanEdit();
        $this->loadInlineCatalogCards();
        $this->loadInlineCatalogGroups();

        if ($serviceId) {
            $this->startInlineWizardEdit($serviceId);

            return;
        }

        $this->inlineCatalogScreen = 'list';
        $this->inlineWizardStep = 1;
        $this->inlineEditingServiceId = null;
        $this->inlineParentServiceName = '';
        $this->inlineWizardServices = [];
        $this->discountMatrix = [];
        $this->newVariantDrafts = [];
        $this->showInlineServiceCatalogModal = true;
    }

    public function closeInlineServiceCatalogModal(): void
    {
        $this->showInlineServiceCatalogModal = false;
        $this->inlineCatalogScreen = 'list';
        $this->inlineWizardStep = 1;
        $this->inlineEditingServiceId = null;
        $this->inlineParentServiceName = '';
        $this->inlineWizardServices = [];
        $this->discountMatrix = [];
        $this->newVariantDrafts = [];
    }

    public function startInlineWizardNew(): void
    {
        $this->assertInlineCatalogCanEdit();
        $this->inlineCatalogScreen = 'wizard';
        $this->inlineWizardStep = 1;
        $this->inlineEditingServiceId = null;
        $this->inlineParentServiceName = '';
        $this->inlineWizardServices = [];
        $this->discountMatrix = [];
        $this->newVariantDrafts = [];
    }

    public function startInlineWizardEdit(int $serviceId): void
    {
        $this->assertInlineCatalogCanEdit();
        $this->inlineCatalogScreen = 'wizard';
        $this->inlineEditingServiceId = $serviceId;
        $this->hydrateInlineWizardFromService($serviceId);
        $this->inlineWizardStep = 1;
        $this->showInlineServiceCatalogModal = true;
    }

    public function inlineWizardBack(): void
    {
        if ($this->inlineWizardStep <= 1) {
            $this->inlineCatalogScreen = 'list';
            $this->loadInlineCatalogCards();

            return;
        }

        $this->inlineWizardStep--;
    }

    public function inlineWizardAdvanceFromParent(): void
    {
        $this->assertInlineCatalogCanEdit();
        $this->validate([
            'inlineParentServiceName' => ['required', 'string', 'max:200'],
        ]);

        $name = trim($this->inlineParentServiceName);
        $accommodationId = $this->accommodation->id;

        if ($this->inlineEditingServiceId) {
            ServiceCatalog::query()
                ->where('id', $this->inlineEditingServiceId)
                ->where('accommodation_id', $accommodationId)
                ->update(['name' => $name]);
        } else {
            $service = ServiceCatalog::create([
                'accommodation_id' => $accommodationId,
                'key'              => 'custom_' . time(),
                'name'             => $name,
                'default_price'    => 0,
                'default_discount' => 0,
                'sort_order'       => (int) ServiceCatalog::query()
                    ->forAccommodation($accommodationId)
                    ->max('sort_order') + 1,
                'is_active'        => true,
            ]);

            foreach (VeteranGroup::query()->forAccommodation($accommodationId)->get() as $group) {
                VeteranGroupServiceDiscount::create([
                    'veteran_group_id'       => $group->id,
                    'service_catalog_id'     => $service->id,
                    'discount_percentage'    => 0,
                    'free_sessions_eligible' => false,
                    'weekly_free_sessions'   => 0,
                ]);
            }

            $this->inlineEditingServiceId = $service->id;
        }

        $this->hydrateInlineWizardFromService((int) $this->inlineEditingServiceId);
        $this->inlineWizardStep = 2;
        $this->dispatch('toast', type: 'success', message: 'نام خدمت ذخیره شد. اکنون انواع و قیمت را تعریف کنید.');
    }

    public function inlineWizardAdvanceFromVariants(): void
    {
        $this->assertInlineCatalogCanEdit();
        $serviceId = (int) ($this->inlineEditingServiceId ?? 0);
        if ($serviceId <= 0) {
            return;
        }

        $this->validate([
            'inlineWizardServices.*.name'             => ['required', 'string', 'max:200'],
            'inlineWizardServices.*.variants.*.name'  => ['required', 'string', 'max:200'],
            'inlineWizardServices.*.variants.*.price' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($this->inlineWizardServices as $row) {
            ServiceCatalog::query()
                ->where('id', $serviceId)
                ->where('accommodation_id', $this->accommodation->id)
                ->update(['name' => trim((string) ($row['name'] ?? $this->inlineParentServiceName))]);
            $this->syncInlineServiceVariants($serviceId, $row['variants'] ?? []);
        }

        $variantCount = ServiceCatalogVariant::query()
            ->where('service_catalog_id', $serviceId)
            ->where('is_active', true)
            ->count();

        if ($variantCount < 1) {
            $this->addError('inlineWizardVariants', 'حداقل یک نوع با قیمت فعال تعریف کنید (مثلاً زرشک‌پلو با مبلغ ۵٬۰۰۰٬۰۰۰ ریال).');

            return;
        }

        $this->hydrateInlineWizardFromService($serviceId);
        $this->loadInlineDiscountMatrixForService($serviceId);
        $this->inlineWizardStep = 3;
    }

    public function finishInlineWizardDiscounts(): void
    {
        $this->assertInlineCatalogCanEdit();
        $this->validate($this->discountMatrixValidationRules());

        foreach ($this->discountMatrix as $groupKey => $serviceRows) {
            foreach ($serviceRows as $row) {
                if (empty($row['id'])) {
                    continue;
                }
                $payload = ServiceDiscountTierEngine::matrixRowToPersistence($row);
                VeteranGroupServiceDiscount::where('id', $row['id'])->update($payload);
            }
        }

        app(VeteranPolicyService::class)->clearCache($this->accommodation->id);
        $this->loadInlineCatalogCards();
        $this->refreshServiceDiscountOverrides();
        $this->dispatch('toast', type: 'success', message: 'خدمت، انواع و تخفیف ایثارگری ذخیره شد.');
        $this->closeInlineServiceCatalogModal();
    }

    public function addInlineServiceVariant(?int $serviceId = null): void
    {
        $this->assertInlineCatalogCanEdit();
        $serviceId = $serviceId ?? (int) ($this->inlineEditingServiceId ?? 0);
        if ($serviceId <= 0) {
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
        $service->refresh();
        $service->activateWhenHasActiveVariants();
        app(VeteranPolicyService::class)->clearCache($this->accommodation->id);
        $this->hydrateInlineWizardFromService($serviceId);
        $this->dispatch('toast', type: 'success', message: 'نوع خدمت اضافه شد.');
    }

    public function removeInlineServiceVariant(int $variantId): void
    {
        $this->assertInlineCatalogCanEdit();
        ServiceCatalogVariant::query()
            ->where('id', $variantId)
            ->whereHas('serviceCatalog', fn ($q) => $q->where('accommodation_id', $this->accommodation->id))
            ->delete();

        if ($this->inlineEditingServiceId) {
            $this->hydrateInlineWizardFromService((int) $this->inlineEditingServiceId);
        }

        $this->dispatch('toast', type: 'success', message: 'نوع خدمت حذف شد.');
    }

    protected function syncInlineParentActiveFromVariants(): void
    {
        $updated = ServiceCatalog::activateParentsHavingActiveVariants($this->accommodation->id);
        if ($updated > 0) {
            app(VeteranPolicyService::class)->clearCache($this->accommodation->id);
        }
    }

    protected function loadInlineCatalogCards(): void
    {
        $this->syncInlineParentActiveFromVariants();

        $this->inlineCatalogCards = ServiceCatalog::query()
            ->forAccommodation($this->accommodation->id)
            ->excludingRetiredHalls()
            ->ordered()
            ->withCount(['variants' => fn ($q) => $q->where('is_active', true)])
            ->get()
            ->map(fn (ServiceCatalog $s) => [
                'id'              => $s->id,
                'name'            => $s->name,
                'variants_count'  => (int) $s->variants_count,
                'is_active'       => (bool) $s->is_active,
            ])
            ->values()
            ->all();
    }

    protected function loadInlineCatalogGroups(): void
    {
        $this->inlineCatalogGroups = VeteranGroup::query()
            ->forAccommodation($this->accommodation->id)
            ->ordered()
            ->get()
            ->map(fn (VeteranGroup $g) => [
                'id'    => $g->id,
                'key'   => $g->key,
                'label' => $g->label,
            ])
            ->values()
            ->all();
    }

    protected function hydrateInlineWizardFromService(int $serviceId): void
    {
        $service = ServiceCatalog::query()
            ->where('id', $serviceId)
            ->where('accommodation_id', $this->accommodation->id)
            ->with(['variants' => fn ($q) => $q->ordered()])
            ->firstOrFail();

        $this->inlineParentServiceName = $service->name;
        $this->inlineEditingServiceId = $service->id;

        $this->inlineWizardServices = [
            $service->key => [
                'id'                     => $service->id,
                'key'                    => $service->key,
                'name'                   => $service->name,
                'default_price'          => $service->default_price,
                'supports_free_sessions' => $service->supports_free_sessions,
                'default_discount'       => $service->default_discount,
                'min_discount'           => $service->min_discount,
                'max_discount'           => $service->max_discount,
                'is_active'              => $service->is_active,
                'variants'               => $service->variants->map(fn (ServiceCatalogVariant $v) => [
                    'id'        => $v->id,
                    'key'       => $v->key,
                    'name'      => $v->name,
                    'price'     => $v->price,
                    'is_active' => $v->is_active,
                ])->values()->all(),
            ],
        ];

        $this->newVariantDrafts[$service->id] = $this->newVariantDrafts[$service->id] ?? ['name' => '', 'price' => 0];
    }

    protected function loadInlineDiscountMatrixForService(int $serviceId): void
    {
        $this->discountMatrix = [];
        $accommodationId = $this->accommodation->id;

        foreach (VeteranGroup::query()->forAccommodation($accommodationId)->ordered()->get() as $group) {
            $row = VeteranGroupServiceDiscount::firstOrCreate(
                [
                    'veteran_group_id'   => $group->id,
                    'service_catalog_id' => $serviceId,
                ],
                [
                    'discount_percentage'    => 0,
                    'free_sessions_eligible' => false,
                    'weekly_free_sessions'   => 0,
                ],
            );

            $this->discountMatrix[$group->key][$serviceId] = array_merge(
                ['id' => $row->id],
                ServiceDiscountTierEngine::matrixRowFromPersistence([
                    'discount_percentage'    => $row->discount_percentage,
                    'use_tiered_discount'    => $row->use_tiered_discount,
                    'discount_tiers'         => $row->discount_tiers ?? [],
                    'free_sessions_eligible' => $row->free_sessions_eligible,
                    'weekly_free_sessions'   => $row->weekly_free_sessions,
                ]),
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $variants
     */
    protected function syncInlineServiceVariants(int $serviceId, array $variants): void
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

        $service->refresh();
        $service->activateWhenHasActiveVariants();

        app(VeteranPolicyService::class)->clearCache($this->accommodation->id);
    }

    /** @return array<int, array<string, mixed>> */
    public function inlineWizardServicesList(): array
    {
        return array_values($this->inlineWizardServices);
    }
}
