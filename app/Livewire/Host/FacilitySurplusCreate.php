<?php

namespace App\Livewire\Host;

use App\Livewire\Concerns\AssertsHostPermissions;
use App\Livewire\Concerns\ManagesFacilityItemForm;
use App\Models\FacilityExchangeItem;
use App\Services\FacilityExchangeItemService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.host', ['title' => 'ثبت اقلام مازاد', 'pageTitle' => 'ثبت اقلام مازاد'])]
class FacilitySurplusCreate extends Component
{
    use AssertsHostPermissions;
    use ManagesFacilityItemForm;

    public function mount(): void
    {
        $this->mountFacilityItemFormDefaults();
    }

    public function store(): void
    {
        $this->assertHostCan('facility-surplus.create', 'write');

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
        $this->redirectRoute('host.facility.surplus.index', navigate: true);
    }

    public function render()
    {
        return view('host.facility.surplus.create', array_merge(
            $this->facilityItemFormViewData(),
            ['cancelRoute' => $this->facilityItemFormCancelRoute(true)],
        ));
    }
}
