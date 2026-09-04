<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\ManagesFacilityItemDetailModal;
use App\Livewire\Concerns\ManagesFacilityItemFilters;
use App\Livewire\Concerns\QueriesFacilityExchangeItems;
use App\Models\FacilityExchangeItem;
use App\Models\Province;
use App\Services\FacilityItemCategoryCatalogService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin', ['title' => 'اقلام مازاد', 'pageTitle' => 'اقلام مازاد'])]
class FacilitySurplusIndex extends Component
{
    use WithPagination;
    use ManagesFacilityItemDetailModal;
    use ManagesFacilityItemFilters;
    use QueriesFacilityExchangeItems;

    public function mount(): void
    {
        $this->mountFacilityFilters();
    }

    protected function facilityDetailType(): string
    {
        return FacilityExchangeItem::TYPE_SURPLUS;
    }

    protected function supportsMineOnlyFilter(): bool
    {
        return false;
    }

    public function destroy(int $id): void
    {
        $item = FacilityExchangeItem::query()
            ->where('type', FacilityExchangeItem::TYPE_SURPLUS)
            ->where('id', $id)
            ->firstOrFail();

        $this->closeDetailIfMatches($id);

        app(\App\Services\FacilityExchangeItemService::class)->delete($item);

        session()->flash('status', 'مورد حذف شد.');
        $this->dispatch('toast', type: 'success', message: 'مورد حذف شد.');
        $this->resetPage();
    }

    public function render()
    {
        $items = $this->buildFacilityItemsQuery(FacilityExchangeItem::TYPE_SURPLUS)->latest()->paginate(12);
        $categories = app(FacilityItemCategoryCatalogService::class)->allOrdered();
        $provinces = Province::orderBy('name')->get();

        return view('components.facility._listing-page', [
            'panel' => 'admin',
            'type' => 'surplus',
            'introText' => 'اقلام مازاد ثبت‌شده توسط کاربران.',
            'showCreateButton' => true,
            'createRoute' => route('admin.facility.surplus.create'),
            'createPermissionPage' => null,
            'createButtonLabel' => 'ثبت مورد جدید',
            'emptyText' => 'هنوز موردی ثبت نشده است.',
            'showMineOnlyFilter' => false,
            'items' => $items,
            'categories' => $categories,
            'provinces' => $provinces,
            'detailItem' => $this->resolveDetailItem(),
        ]);
    }
}
