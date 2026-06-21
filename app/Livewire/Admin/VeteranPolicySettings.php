<?php

namespace App\Livewire\Admin;

use App\Models\ServiceCatalog;
use App\Models\VeteranGroup;
use App\Models\VeteranGroupServiceDiscount;
use App\Services\VeteranPolicyService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'تنظیمات ایثارگری', 'pageTitle' => 'تنظیمات ایثارگری و خدمات'])]
class VeteranPolicySettings extends Component
{
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

    public function mount(): void
    {
        $this->loadData();
    }

    public function loadData(): void
    {
        $this->groups = VeteranGroup::ordered()->get()->map(fn (VeteranGroup $g) => [
            'id'                     => $g->id,
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

        $this->services = ServiceCatalog::ordered()->get()->map(fn (ServiceCatalog $s) => [
            'id'                     => $s->id,
            'key'                    => $s->key,
            'name'                   => $s->name,
            'default_price'          => $s->default_price,
            'supports_free_sessions' => $s->supports_free_sessions,
            'default_discount'       => $s->default_discount,
            'min_discount'           => $s->min_discount,
            'max_discount'           => $s->max_discount,
            'is_active'              => $s->is_active,
        ])->values()->all();

        $this->discountMatrix = [];
        foreach (VeteranGroup::ordered()->get() as $group) {
            foreach (ServiceCatalog::ordered()->get() as $service) {
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

                $this->discountMatrix[$group->key][$service->id] = [
                    'id'                     => $row->id,
                    'discount_percentage'    => $row->discount_percentage,
                    'free_sessions_eligible' => $row->free_sessions_eligible,
                    'weekly_free_sessions'   => $row->weekly_free_sessions,
                ];
            }
        }
    }

    public function saveGroups(): void
    {
        $this->validate([
            'groups.*.label'                  => ['required', 'string', 'max:200'],
            'groups.*.accommodation_discount' => ['required', 'integer', 'min:0', 'max:100'],
            'groups.*.nights_per_dependent'   => ['required', 'integer', 'min:1', 'max:365'],
            'groups.*.max_nights_per_period'  => ['required', 'integer', 'min:1', 'max:365'],
            'groups.*.period_months'          => ['required', 'integer', 'min:1', 'max:24'],
        ]);

        foreach ($this->groups as $row) {
            VeteranGroup::where('id', $row['id'])->update([
                'label'                  => $row['label'],
                'accommodation_discount'   => $row['accommodation_discount'],
                'nights_per_dependent'     => $row['nights_per_dependent'],
                'max_nights_per_period'    => $row['max_nights_per_period'],
                'period_months'            => $row['period_months'],
                'usage_notes'              => $row['usage_notes'] ?: null,
                'is_active'                => (bool) ($row['is_active'] ?? true),
            ]);
        }

        app(VeteranPolicyService::class)->clearCache();
        $this->dispatch('toast', type: 'success', message: 'گروه‌های ایثارگری ذخیره شد.');
    }

    public function saveServices(): void
    {
        $this->validate([
            'services.*.name'             => ['required', 'string', 'max:200'],
            'services.*.default_price'    => ['required', 'integer', 'min:0'],
            'services.*.default_discount' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        foreach ($this->services as $row) {
            ServiceCatalog::where('id', $row['id'])->update([
                'name'                   => $row['name'],
                'default_price'          => $row['default_price'],
                'default_discount'       => $row['default_discount'],
                'min_discount'           => $row['min_discount'] !== '' && $row['min_discount'] !== null
                    ? (int) $row['min_discount'] : null,
                'max_discount'           => $row['max_discount'] !== '' && $row['max_discount'] !== null
                    ? (int) $row['max_discount'] : null,
                'supports_free_sessions' => (bool) ($row['supports_free_sessions'] ?? false),
                'is_active'              => (bool) ($row['is_active'] ?? true),
            ]);
        }

        app(VeteranPolicyService::class)->clearCache();
        $this->dispatch('toast', type: 'success', message: 'فهرست خدمات ذخیره شد.');
    }

    public function saveDiscountMatrix(): void
    {
        $this->validate([
            'discountMatrix.*.*.discount_percentage'  => ['required', 'integer', 'min:0', 'max:100'],
            'discountMatrix.*.*.weekly_free_sessions' => ['nullable', 'integer', 'min:0', 'max:21'],
        ]);

        foreach ($this->discountMatrix as $groupKey => $serviceRows) {
            $group = VeteranGroup::where('key', $groupKey)->first();
            if (!$group) {
                continue;
            }

            foreach ($serviceRows as $serviceId => $row) {
                $eligible = (bool) ($row['free_sessions_eligible'] ?? false);
                VeteranGroupServiceDiscount::where('id', $row['id'])->update([
                    'discount_percentage'    => (int) $row['discount_percentage'],
                    'free_sessions_eligible' => $eligible,
                    'weekly_free_sessions'   => $eligible
                        ? (int) ($row['weekly_free_sessions'] ?? 0)
                        : 0,
                ]);
            }
        }

        app(VeteranPolicyService::class)->clearCache();
        $this->dispatch('toast', type: 'success', message: 'ماتریس تخفیف خدمات ذخیره شد.');
    }

    public function addCustomGroup(): void
    {
        $this->validate([
            'newGroupLabel'                  => ['required', 'string', 'max:200'],
            'newGroupAccommodationDiscount'  => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $group = VeteranGroup::create([
            'key'                    => 'custom_group_' . time(),
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

        foreach (ServiceCatalog::ordered()->get() as $service) {
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
        app(VeteranPolicyService::class)->clearCache();
        $this->loadData();
        $this->dispatch('toast', type: 'success', message: 'گروه ایثارگری جدید اضافه شد. تخفیف خدمات را در تب «تخفیف خدمات» تنظیم کنید.');
    }

    public function addCustomService(): void
    {
        $this->validate([
            'newServiceName'  => ['required', 'string', 'max:200'],
            'newServicePrice' => ['required', 'integer', 'min:0'],
        ]);

        $key = 'custom_' . time();
        $service = ServiceCatalog::create([
            'key'              => $key,
            'name'             => $this->newServiceName,
            'default_price'    => $this->newServicePrice,
            'default_discount' => 0,
            'sort_order'       => ServiceCatalog::max('sort_order') + 1,
            'is_active'        => true,
        ]);

        foreach (VeteranGroup::all() as $group) {
            VeteranGroupServiceDiscount::create([
                'veteran_group_id'       => $group->id,
                'service_catalog_id'     => $service->id,
                'discount_percentage'    => 0,
                'free_sessions_eligible' => false,
                'weekly_free_sessions'   => 0,
            ]);
        }

        $this->newServiceName = '';
        $this->newServicePrice = 0;
        app(VeteranPolicyService::class)->clearCache();
        $this->loadData();
        $this->dispatch('toast', type: 'success', message: 'خدمت جدید اضافه شد.');
    }

    public function render()
    {
        return view('livewire.admin.veteran-policy-settings');
    }
}
