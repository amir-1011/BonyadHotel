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

#[Layout('layouts.admin', ['title' => 'اقلام مورد نیاز', 'pageTitle' => 'اقلام مورد نیاز'])]
class FacilityNeededIndex extends Component
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
        return FacilityExchangeItem::TYPE_NEEDED;
    }

    protected function supportsMineOnlyFilter(): bool
    {
        return false;
    }

    public function destroy(int $id): void
    {
        $item = FacilityExchangeItem::query()
            ->where('type', FacilityExchangeItem::TYPE_NEEDED)
            ->where('id', $id)
            ->firstOrFail();

        $this->closeDetailIfMatches($id);

        app(\App\Services\FacilityExchangeItemService::class)->delete($item);

        session()->flash('status', 'درخواست حذف شد.');
        $this->dispatch('toast', type: 'success', message: 'درخواست حذف شد.');
        $this->resetPage();
    }

    public function render()
    {
        $items = $this->buildFacilityItemsQuery(FacilityExchangeItem::TYPE_NEEDED)->latest()->paginate(12);
        $categories = app(FacilityItemCategoryCatalogService::class)->allOrdered();
        $provinces = Province::orderBy('name')->get();

        return view('components.facility._listing-page', [
            'panel' => 'admin',
            'type' => 'needed',
            'introText' => 'درخواست‌های اقلام مورد نیاز ثبت‌شده توسط میزبان‌ها.',
            'showCreateButton' => true,
            'createRoute' => route('admin.facility.needed.create'),
            'createPermissionPage' => null,
            'createButtonLabel' => 'ثبت درخواست جدید',
            'emptyText' => 'هنوز درخواستی ثبت نشده است.',
            'showMineOnlyFilter' => false,
            'items' => $items,
            'categories' => $categories,
            'provinces' => $provinces,
            'detailItem' => $this->resolveDetailItem(),
        ]);
    }
}
