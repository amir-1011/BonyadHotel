<?php

namespace App\Livewire\Concerns;

use App\Models\Booking;
use App\Models\BookingService;
use App\Services\ManualBookingService;
use App\Services\VeteranPolicyService;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;

trait ManagesBookingDetails
{
    use WithFileUploads;

    public string $selectedStatus = '';

    /** @var array<int, array{id?:int, name:string, unit_price:int|string, quantity:int|string}> */
    public array $editableServices = [];

    public $uploadedForm;
    public string $newServiceCatalogId = '';
    public string $newServiceName = '';
    public int|string $newServicePrice = '';
    public int $newServiceQty = 1;

    public function bootBookingDetails(Booking $booking): void
    {
        $this->selectedStatus = $booking->status;
        $this->loadEditableServices();
    }

    public function loadEditableServices(): void
    {
        $this->editableServices = $this->booking->services->map(fn ($s) => [
            'id'              => $s->id,
            'name'            => $s->name,
            'unit_price'      => $s->unit_price,
            'quantity'        => $s->quantity,
            'discount_amount' => $s->discount_amount,
            'total'           => $s->total,
        ])->values()->all();

        if (empty($this->editableServices)) {
            $this->editableServices = [];
        }
    }

    public function updatedNewServiceCatalogId(): void
    {
        if ($this->newServiceCatalogId === 'custom' || $this->newServiceCatalogId === '') {
            if ($this->newServiceCatalogId === 'custom') {
                $this->newServiceName = '';
                $this->newServicePrice = '';
            }
            return;
        }

        $service = app(VeteranPolicyService::class)->serviceById((int) $this->newServiceCatalogId);
        if ($service) {
            $this->newServiceName = $service->name;
            $this->newServicePrice = $service->default_price;
        }
    }

    public function addServiceLine(): void
    {
        $this->validate([
            'newServiceName'  => ['required', 'string', 'max:200'],
            'newServicePrice' => ['required', 'integer', 'min:0'],
            'newServiceQty'   => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $catalogId = ($this->newServiceCatalogId && $this->newServiceCatalogId !== 'custom')
            ? (int) $this->newServiceCatalogId
            : null;

        BookingService::create([
            'booking_id'         => $this->booking->id,
            'service_catalog_id' => $catalogId,
            'name'               => $this->newServiceName,
            'unit_price'         => (int) $this->newServicePrice,
            'quantity'           => $this->newServiceQty,
            'total'              => (int) $this->newServicePrice * $this->newServiceQty,
            'sort_order'         => $this->booking->services()->count(),
        ]);

        $this->booking->refresh();
        app(ManualBookingService::class)->recalculateTotals($this->booking);
        $this->booking->refresh();
        $this->loadEditableServices();
        $this->newServiceCatalogId = '';
        $this->newServiceName = '';
        $this->newServicePrice = '';
        $this->newServiceQty = 1;
        $this->dispatch('toast', type: 'success', message: 'خدمت اضافه شد.');
    }

    public function removeServiceLine(int $serviceId): void
    {
        $service = $this->booking->services()->findOrFail($serviceId);
        $service->delete();
        $this->booking->refresh();
        app(ManualBookingService::class)->recalculateTotals($this->booking);
        $this->booking->refresh();
        $this->loadEditableServices();
        $this->dispatch('toast', type: 'success', message: 'خدمت حذف شد.');
    }

    public function saveServiceEdits(ManualBookingService $manualBooking): void
    {
        $this->validate([
            'editableServices.*.name'       => ['required', 'string', 'max:200'],
            'editableServices.*.unit_price' => ['required', 'integer', 'min:0'],
            'editableServices.*.quantity'   => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        foreach ($this->editableServices as $i => $row) {
            if (empty($row['id'])) {
                continue;
            }
            $service = $this->booking->services()->find($row['id']);
            if (!$service) {
                continue;
            }
            $qty = (int) $row['quantity'];
            $unit = (int) $row['unit_price'];
            $service->update([
                'name'       => $row['name'],
                'unit_price' => $unit,
                'quantity'   => $qty,
                'total'      => $qty * $unit,
                'sort_order' => $i,
            ]);
        }

        $manualBooking->recalculateTotals($this->booking);
        $this->booking->refresh();
        $this->loadEditableServices();
        $this->dispatch('toast', type: 'success', message: 'خدمات به‌روز شد.');
    }

    public function uploadBookingForm(): void
    {
        $this->validate([
            'uploadedForm' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        if ($this->booking->form_file_path) {
            Storage::disk('public')->delete($this->booking->form_file_path);
        }

        $path = $this->uploadedForm->store('booking-forms/' . $this->booking->id, 'public');
        $this->booking->update(['form_file_path' => $path]);
        $this->uploadedForm = null;
        $this->booking->refresh();
        $this->dispatch('toast', type: 'success', message: 'فرم رزرو آپلود شد.');
    }

    public function deleteBookingForm(): void
    {
        if ($this->booking->form_file_path) {
            Storage::disk('public')->delete($this->booking->form_file_path);
            $this->booking->update(['form_file_path' => null]);
            $this->booking->refresh();
        }
        $this->dispatch('toast', type: 'success', message: 'فایل فرم حذف شد.');
    }

    protected function serviceCatalogOptions()
    {
        return app(VeteranPolicyService::class)->activeServices();
    }
}
