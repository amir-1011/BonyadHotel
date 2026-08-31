<?php

namespace App\Livewire\Host;

use App\Livewire\Concerns\AssertsHostPermissions;
use App\Livewire\Concerns\ManagesFacilityItemForm;
use App\Models\FacilityExchangeItem;
use App\Services\FacilityExchangeItemService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.host', ['title' => 'ویرایش اقلام مورد نیاز', 'pageTitle' => 'ویرایش درخواست اقلام مورد نیاز'])]
class FacilityNeededEdit extends Component
{
    use AssertsHostPermissions;
    use ManagesFacilityItemForm;

    public FacilityExchangeItem $item;

    public function mount(FacilityExchangeItem $item): void
    {
        abort_unless($item->type === FacilityExchangeItem::TYPE_NEEDED, 404);
        abort_unless($item->user_id === Auth::id(), 403);

        $this->item = $item;
        $this->loadFacilityItemFormFrom($item);
    }

    public function update(): void
    {
        $this->assertHostCan('facility-needed.edit', 'edit');

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
        $this->redirectRoute('host.facility.needed.index', navigate: true);
    }

    public function render()
    {
        return view('host.facility.needed.edit', array_merge(
            $this->facilityItemFormViewData(),
            [
                'item' => $this->item,
                'cancelRoute' => $this->facilityItemFormCancelRoute(false),
            ],
        ));
    }
}
