<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\ManagesFacilityItemForm;
use App\Models\FacilityExchangeItem;
use App\Services\FacilityExchangeItemService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'ویرایش اقلام مازاد', 'pageTitle' => 'ویرایش اقلام مازاد'])]
class FacilitySurplusEdit extends Component
{
    use ManagesFacilityItemForm;

    public FacilityExchangeItem $item;

    public function mount(FacilityExchangeItem $item): void
    {
        abort_unless($item->type === FacilityExchangeItem::TYPE_SURPLUS, 404);

        $this->item = $item;
        $this->formPanel = 'admin';
        $this->loadFacilityItemFormFrom($item);
    }

    public function update(): void
    {
        $this->validate(
            $this->facilityItemFormRules(true, false),
            [],
            $this->facilityItemFormAttributes(),
        );

        try {
            $payload = $this->validatedFacilityItemPayload();

            app(FacilityExchangeItemService::class)->update(
                $this->item,
                $payload,
                $this->images,
                $this->keptImagePaths,
            );
        } catch (\RuntimeException $e) {
            $this->addError('images', $e->getMessage());

            return;
        }

        session()->flash('status', 'با موفقیت به‌روزرسانی شد.');
        $this->redirectRoute('admin.facility.surplus.index', navigate: true);
    }

    public function render()
    {
        return view('admin.facility.surplus.edit', array_merge(
            $this->facilityItemFormViewData(),
            [
                'item' => $this->item,
                'cancelRoute' => $this->facilityItemFormCancelRoute(true),
            ],
        ));
    }
}
