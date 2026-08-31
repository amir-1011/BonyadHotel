<?php

namespace App\Livewire\Host;

use App\Livewire\Concerns\AssertsHostPermissions;
use App\Livewire\Concerns\ManagesFacilityItemForm;
use App\Models\FacilityExchangeItem;
use App\Services\FacilityExchangeItemService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.host', ['title' => 'ثبت اقلام مورد نیاز', 'pageTitle' => 'ثبت درخواست اقلام مورد نیاز'])]
class FacilityNeededCreate extends Component
{
    use AssertsHostPermissions;
    use ManagesFacilityItemForm;

    public function mount(): void
    {
        $this->mountFacilityItemFormDefaults();
    }

    public function store(): void
    {
        $this->assertHostCan('facility-needed.create', 'write');

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
        $this->redirectRoute('host.facility.needed.index', navigate: true);
    }

    public function render()
    {
        return view('host.facility.needed.create', array_merge(
            $this->facilityItemFormViewData(),
            ['cancelRoute' => $this->facilityItemFormCancelRoute(false)],
        ));
    }
}
