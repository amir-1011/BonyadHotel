<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\ManagesFacilityItemForm;
use App\Models\FacilityExchangeItem;
use App\Services\FacilityExchangeItemService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'ثبت اقلام مورد نیاز', 'pageTitle' => 'ثبت درخواست اقلام مورد نیاز'])]
class FacilityNeededCreate extends Component
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
            $this->facilityItemFormRules(false, false),
            [],
            $this->facilityItemFormAttributes(),
        );

        $payload = $this->validatedFacilityItemPayload();

        app(FacilityExchangeItemService::class)->create(
            FacilityExchangeItem::TYPE_NEEDED,
            $payload,
            auth()->user(),
        );

        session()->flash('status', 'درخواست اقلام مورد نیاز با موفقیت ثبت شد.');
        $this->redirectRoute('admin.facility.needed.index', navigate: true);
    }

    public function render()
    {
        return view('admin.facility.needed.create', array_merge(
            $this->facilityItemFormViewData(),
            ['cancelRoute' => $this->facilityItemFormCancelRoute(false)],
        ));
    }
}
