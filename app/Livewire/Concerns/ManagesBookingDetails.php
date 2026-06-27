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
    public string $newServiceCatalogVariantId = '';
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
        $this->editableServices = $this->booking->services->mapWithKeys(fn ($s) => [
            $s->id => [
                'id'              => $s->id,
                'name'            => $s->name,
                'unit_price'      => $s->unit_price,
                'quantity'        => $s->quantity,
                'discount_amount' => $s->discount_amount,
                'total'           => $s->total,
            ],
        ])->all();

        if (empty($this->editableServices)) {
            $this->editableServices = [];
        }
    }

    public function updatedNewServiceCatalogId(): void
    {
        $this->newServiceCatalogVariantId = '';

        if ($this->newServiceCatalogId === 'custom' || $this->newServiceCatalogId === '') {
            if ($this->newServiceCatalogId === 'custom') {
                $this->newServiceName = '';
                $this->newServicePrice = '';
            }
            return;
        }

        $service = app(VeteranPolicyService::class)
            ->forAccommodation($this->booking->accommodation_id)
            ->serviceById((int) $this->newServiceCatalogId);
        if (!$service) {
            return;
        }

        if ($service->variants->where('is_active', true)->isNotEmpty()) {
            $this->newServiceName = $service->name;
            $this->newServicePrice = '';
            return;
        }

        $this->addError('newServiceCatalogId', 'برای این خدمت نوع و قیمت تعریف نشده. از تنظیمات ایثارگری انواع را اضافه کنید.');
        $this->newServiceName = '';
        $this->newServicePrice = '';
    }

    public function updatedNewServiceCatalogVariantId(): void
    {
        if ($this->newServiceCatalogVariantId === '' || $this->newServiceCatalogId === '') {
            return;
        }

        $service = app(VeteranPolicyService::class)
            ->forAccommodation($this->booking->accommodation_id)
            ->serviceById((int) $this->newServiceCatalogId);
        if (!$service) {
            return;
        }

        $variant = $service->variants->firstWhere('id', (int) $this->newServiceCatalogVariantId);
        if (!$variant || !$variant->is_active) {
            return;
        }

        $this->newServiceName = $service->name . ' — ' . $variant->name;
        $this->newServicePrice = $variant->price;
    }

    public function addServiceLine(): void
    {
        $catalogId = ($this->newServiceCatalogId && $this->newServiceCatalogId !== 'custom')
            ? (int) $this->newServiceCatalogId
            : null;

        if ($catalogId) {
            $service = app(VeteranPolicyService::class)
                ->forAccommodation($this->booking->accommodation_id)
                ->serviceById($catalogId);
            if ($service && $service->variants->where('is_active', true)->isEmpty()) {
                $this->addError('newServiceCatalogId', 'برای این خدمت نوع و قیمت تعریف نشده. از تنظیمات ایثارگری انواع را اضافه کنید.');
                return;
            }
            if ($service && $service->variants->where('is_active', true)->isNotEmpty() && $this->newServiceCatalogVariantId === '') {
                $this->addError('newServiceCatalogVariantId', 'نوع این خدمت را انتخاب کنید.');
                return;
            }
        }

        $this->validate([
            'newServiceName'  => ['required', 'string', 'max:200'],
            'newServicePrice' => ['required', 'integer', 'min:0'],
            'newServiceQty'   => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $variantId = ($this->newServiceCatalogVariantId && $catalogId)
            ? (int) $this->newServiceCatalogVariantId
            : null;

        BookingService::create([
            'booking_id'                 => $this->booking->id,
            'service_catalog_id'         => $catalogId,
            'service_catalog_variant_id' => $variantId,
            'name'                       => $this->newServiceName,
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
        $this->newServiceCatalogVariantId = '';
        $this->newServiceName = '';
        $this->newServicePrice = '';
        $this->newServiceQty = 1;
        $this->dispatch('toast', type: 'success', message: 'خدمت اضافه شد.');
        $this->dispatch('booking-services-updated');
    }

    public function adjustServiceQuantity(int $serviceId, int $delta): void
    {
        $service = $this->booking->services()->findOrFail($serviceId);
        $newQty = max(1, min(99, (int) $service->quantity + $delta));
        if ($newQty === (int) $service->quantity) {
            return;
        }

        $service->update([
            'quantity' => $newQty,
            'total'    => $newQty * (int) $service->unit_price,
        ]);

        $this->booking->refresh();
        app(ManualBookingService::class)->recalculateTotals($this->booking);
        $this->booking->refresh();
        $this->loadEditableServices();
        $this->dispatch('booking-services-updated');
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
        $this->dispatch('booking-services-updated');
    }

    public function saveServiceEdits(ManualBookingService $manualBooking): void
    {
        $this->validate([
            'editableServices.*.name'       => ['required', 'string', 'max:200'],
            'editableServices.*.unit_price' => ['required', 'integer', 'min:0'],
            'editableServices.*.quantity'   => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $sortOrder = 0;
        foreach ($this->editableServices as $row) {
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
                'sort_order' => $sortOrder++,
            ]);
        }

        $manualBooking->recalculateTotals($this->booking);
        $this->booking->refresh();
        $this->loadEditableServices();
        $this->dispatch('toast', type: 'success', message: 'خدمات به‌روز شد.');
        $this->dispatch('booking-services-updated');
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
        return app(VeteranPolicyService::class)
            ->forAccommodation($this->booking->accommodation_id)
            ->activeServices();
    }
}
