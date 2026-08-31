<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\ManagesFacilityItemForm;
use App\Models\FacilityExchangeItem;
use App\Services\FacilityExchangeItemService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'ویرایش اقلام مورد نیاز', 'pageTitle' => 'ویرایش درخواست اقلام مورد نیاز'])]
class FacilityNeededEdit extends Component
{
    use ManagesFacilityItemForm;

    public FacilityExchangeItem $item;

    public function mount(FacilityExchangeItem $item): void
    {
        abort_unless($item->type === FacilityExchangeItem::TYPE_NEEDED, 404);

        $this->item = $item;
        $this->formPanel = 'admin';
        $this->loadFacilityItemFormFrom($item);
    }

    public function update(): void
    {
        $this->validate(
            $this->facilityItemFormRules(false, false),
            [],
            $this->facilityItemFormAttributes(),
        );

        $payload = $this->validatedFacilityItemPayload();

        app(FacilityExchangeItemService::class)->update(
            $this->item,
            $payload,
        );

        session()->flash('status', 'درخواست با موفقیت به‌روزرسانی شد.');
        $this->redirectRoute('admin.facility.needed.index', navigate: true);
    }

    public function render()
    {
        return view('admin.facility.needed.edit', array_merge(
            $this->facilityItemFormViewData(),
            [
                'item' => $this->item,
                'cancelRoute' => $this->facilityItemFormCancelRoute(false),
            ],
        ));
    }
}
