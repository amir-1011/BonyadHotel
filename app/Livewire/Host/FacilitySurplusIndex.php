<?php

namespace App\Livewire\Host;

use App\Livewire\Concerns\AssertsHostPermissions;
use App\Livewire\Concerns\ManagesFacilityItemDetailModal;
use App\Livewire\Concerns\ManagesFacilityItemFilters;
use App\Livewire\Concerns\QueriesFacilityExchangeItems;
use App\Models\FacilityExchangeItem;
use App\Models\Province;
use App\Services\FacilityItemCategoryCatalogService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.host', ['title' => 'اقلام مازاد', 'pageTitle' => 'اقلام مازاد'])]
class FacilitySurplusIndex extends Component
{
    use WithPagination;
    use AssertsHostPermissions;
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

    public function destroy(int $id): void
    {
        $this->assertHostCan('facility-surplus.edit', 'delete');

        $item = FacilityExchangeItem::query()
            ->where('type', FacilityExchangeItem::TYPE_SURPLUS)
            ->where('user_id', Auth::id())
            ->where('id', $id)
            ->first();

        if (!$item) {
            abort(403, 'به این عملیات دسترسی ندارید.');
        }

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
            'panel' => 'host',
            'type' => 'surplus',
            'introText' => 'اقلام مازاد ثبت‌شده توسط میزبان‌ها — برای هماهنگی با شماره تماس ارتباط بگیرید.',
            'showCreateButton' => true,
            'createRoute' => route('host.facility.surplus.create'),
            'createPermissionPage' => 'facility-surplus.create',
            'createButtonLabel' => 'ثبت مورد جدید',
            'emptyText' => 'هنوز موردی ثبت نشده است.',
            'showMineOnlyFilter' => true,
            'items' => $items,
            'categories' => $categories,
            'provinces' => $provinces,
            'detailItem' => $this->resolveDetailItem(),
        ]);
    }
}
