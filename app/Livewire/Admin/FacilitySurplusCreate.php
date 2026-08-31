<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\ManagesFacilityItemForm;
use App\Models\FacilityExchangeItem;
use App\Services\FacilityExchangeItemService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'ثبت اقلام مازاد', 'pageTitle' => 'ثبت اقلام مازاد'])]
class FacilitySurplusCreate extends Component
{
    use ManagesFacilityItemForm;

    public function mount(): void
    {
        $this->formPanel = 'admin';
        $this->mountFacilityItemFormDefaults();
    }

    public function store(): void
    {
        $this->validate(
            $this->facilityItemFormRules(true, false),
            [],
            $this->facilityItemFormAttributes(),
        );

        try {
            $payload = $this->validatedFacilityItemPayload();

            app(FacilityExchangeItemService::class)->create(
                FacilityExchangeItem::TYPE_SURPLUS,
                $payload,
                auth()->user(),
                $this->images,
            );
        } catch (\RuntimeException $e) {
            $this->addError('images', $e->getMessage());

            return;
        }

        session()->flash('status', 'اقلام مازاد با موفقیت ثبت شد.');
        $this->redirectRoute('admin.facility.surplus.index', navigate: true);
    }

    public function render()
    {
        return view('admin.facility.surplus.create', array_merge(
            $this->facilityItemFormViewData(),
            ['cancelRoute' => $this->facilityItemFormCancelRoute(true)],
        ));
    }
}
