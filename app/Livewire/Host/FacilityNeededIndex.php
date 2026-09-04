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

#[Layout('layouts.host', ['title' => 'اقلام مورد نیاز', 'pageTitle' => 'اقلام مورد نیاز'])]
class FacilityNeededIndex extends Component
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
        return FacilityExchangeItem::TYPE_NEEDED;
    }

    public function destroy(int $id): void
    {
        $this->assertHostCan('facility-needed.edit', 'delete');

        $item = FacilityExchangeItem::query()
            ->where('type', FacilityExchangeItem::TYPE_NEEDED)
            ->where('user_id', Auth::id())
            ->where('id', $id)
            ->first();

        if (!$item) {
            abort(403, 'به این عملیات دسترسی ندارید.');
        }

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
            'panel' => 'host',
            'type' => 'needed',
            'introText' => 'درخواست‌های اقلام مورد نیاز ثبت‌شده توسط کاربران.',
            'showCreateButton' => true,
            'createRoute' => route('host.facility.needed.create'),
            'createPermissionPage' => 'facility-needed.create',
            'createButtonLabel' => 'ثبت درخواست جدید',
            'emptyText' => 'هنوز درخواستی ثبت نشده است.',
            'showMineOnlyFilter' => true,
            'items' => $items,
            'categories' => $categories,
            'provinces' => $provinces,
            'detailItem' => $this->resolveDetailItem(),
        ]);
    }
}
