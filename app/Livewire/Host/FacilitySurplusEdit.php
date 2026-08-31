<?php

namespace App\Livewire\Host;

use App\Livewire\Concerns\AssertsHostPermissions;
use App\Livewire\Concerns\ManagesFacilityItemForm;
use App\Models\FacilityExchangeItem;
use App\Services\FacilityExchangeItemService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.host', ['title' => 'ویرایش اقلام مازاد', 'pageTitle' => 'ویرایش اقلام مازاد'])]
class FacilitySurplusEdit extends Component
{
    use AssertsHostPermissions;
    use ManagesFacilityItemForm;

    public FacilityExchangeItem $item;

    public function mount(FacilityExchangeItem $item): void
    {
        abort_unless($item->type === FacilityExchangeItem::TYPE_SURPLUS, 404);
        abort_unless($item->user_id === Auth::id(), 403);

        $this->item = $item;
        $this->loadFacilityItemFormFrom($item);
    }

    public function update(): void
    {
        $this->assertHostCan('facility-surplus.edit', 'edit');

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
        $this->redirectRoute('host.facility.surplus.index', navigate: true);
    }

    public function render()
    {
        return view('host.facility.surplus.edit', array_merge(
            $this->facilityItemFormViewData(),
            [
                'item' => $this->item,
                'cancelRoute' => $this->facilityItemFormCancelRoute(true),
            ],
        ));
    }
}
