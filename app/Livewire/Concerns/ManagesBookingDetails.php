<?php

namespace App\Livewire\Concerns;

use App\Models\Booking;
use App\Models\BookingGuestDetail;
use App\Models\BookingService;
use App\Services\ImageUploadService;
use App\Services\ManualBookingService;
use App\Services\VeteranPolicyService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;

trait ManagesBookingDetails
{
    use WithFileUploads;
    use AssertsHostPermissions;

    public string $selectedStatus = '';

    /** @var array<int, array{id?:int, name:string, unit_price:int|string, quantity:int|string}> */
    public array $editableServices = [];

    public ?int $guestSortOrder = null;

    public $uploadedForm;
    public string $newServiceCatalogId = '';
    public string $newServiceCatalogVariantId = '';
    public string $newServiceName = '';
    public int|string $newServicePrice = '';
    public int $newServiceQty = 1;
    public bool $newExcludedFromVeteranQuota = false;
    public string $newManualDiscountPercentage = '';
    public string $newManualDiscountReason = '';

    /** @var array<int, array{full_name:string, national_id:string, mobile:string, relation:string}> */
    public array $editableGuests = [];

    public function bootBookingDetails(Booking $booking): void
    {
        $this->selectedStatus = $booking->status;
        $this->loadEditableServices();
        $this->loadEditableGuests();
    }

    public function loadEditableServices(): void
    {
        $services = $this->booking->services;
        if ($this->guestSortOrder !== null) {
            $services = $services->where('guest_sort_order', $this->guestSortOrder);
        }

        $this->editableServices = $services->mapWithKeys(fn ($s) => [
            $s->id => [
                'id'                          => $s->id,
                'name'                        => $s->name,
                'unit_price'                  => $s->unit_price,
                'quantity'                    => $s->quantity,
                'saved_quantity'              => $s->quantity,
                'discount_amount'             => $s->discount_amount,
                'discount_percentage'         => $s->discount_percentage,
                'free_units'                  => $s->free_units,
                'total'                       => $s->total,
                'excluded_from_veteran_quota' => (bool) $s->excluded_from_veteran_quota,
                'manual_discount_percentage'  => $s->manual_discount_percentage !== null
                    ? (string) $s->manual_discount_percentage
                    : '',
                'manual_discount_reason'      => $s->manual_discount_reason ?? '',
            ],
        ])->all();

        if (empty($this->editableServices)) {
            $this->editableServices = [];
        }
    }

    public function loadEditableGuests(): void
    {
        $this->editableGuests = [];

        foreach ($this->booking->allGuestSlotsForDisplay() as $slot) {
            $index = (int) ($slot->sort_order ?? 0);
            if ($index === 0) {
                continue;
            }

            $name = trim((string) ($slot->full_name ?? ''));
            $isPlaceholder = !empty($slot->is_name_placeholder)
                || BookingGuestDetail::isGenericGuestName($name, $index);

            $this->editableGuests[$index] = [
                'full_name'   => $isPlaceholder ? '' : $name,
                'national_id' => trim((string) ($slot->national_id ?? '')),
                'mobile'      => trim((string) ($slot->mobile ?? '')),
                'relation'    => trim((string) ($slot->relation ?? '')),
            ];
        }
    }

    public function saveGuestDetails(int $sortOrder): void
    {
        $this->assertBookingEditable();
        $this->assertHostCan('bookings.guests', 'edit');

        if ($sortOrder <= 0) {
            return;
        }

        $this->validate([
            "editableGuests.{$sortOrder}.full_name"   => ['nullable', 'string', 'max:120'],
            "editableGuests.{$sortOrder}.national_id" => ['nullable', 'digits:10'],
            "editableGuests.{$sortOrder}.mobile"      => ['nullable', 'regex:/^09\d{9}$/'],
            "editableGuests.{$sortOrder}.relation"    => ['nullable', 'string', 'max:50'],
        ], [], [
            "editableGuests.{$sortOrder}.full_name"   => 'نام',
            "editableGuests.{$sortOrder}.national_id" => 'کد ملی',
            "editableGuests.{$sortOrder}.mobile"      => 'موبایل',
            "editableGuests.{$sortOrder}.relation"    => 'نسبت',
        ]);

        $row = $this->editableGuests[$sortOrder] ?? [];
        $fullName = trim((string) ($row['full_name'] ?? ''));
        $nationalId = trim((string) ($row['national_id'] ?? '')) ?: null;
        $mobile = trim((string) ($row['mobile'] ?? '')) ?: null;
        $relation = trim((string) ($row['relation'] ?? '')) ?: null;

        $guest = $this->booking->guestDetails()->where('sort_order', $sortOrder)->first();
        $fallbackName = 'مهمان ' . ($sortOrder + 1);
        $resolvedName = $fullName !== '' ? $fullName : $fallbackName;

        $payload = [
            'full_name'   => $resolvedName,
            'national_id' => $nationalId,
            'mobile'      => $mobile,
            'relation'    => $relation,
        ];

        if ($guest) {
            $guest->update($payload);
        } else {
            $slot = collect($this->booking->guestDiscountSlots())->firstWhere('sort_order', $sortOrder);

            BookingGuestDetail::create([
                'booking_id'                     => $this->booking->id,
                'sort_order'                     => $sortOrder,
                ...$payload,
                'excluded_from_veteran_discount' => !empty($slot['excluded_from_veteran_discount']),
                'manual_discount_percentage'     => (int) ($slot['manual_discount_percentage'] ?? 0) ?: null,
                'manual_discount_reason'         => $slot['manual_discount_reason'] ?? null,
            ]);
        }

        $this->syncGuestLabelInSnapshot($sortOrder, $resolvedName);

        $this->booking->refresh()->load([
            'guestDetails.bookingRoom.room', 'guestDetails.bookingRoom.roomType',
        ]);
        $this->loadEditableGuests();
        $this->closeGuestsModalAfterSave();
        $this->dispatchBookingToast('اطلاعات مهمان ذخیره شد.');
    }

    /** Close guests modal and clean up Bootstrap backdrop after Livewire refresh. */
    private function closeGuestsModalAfterSave(): void
    {
        $modalId = 'bd-modal-guests-' . $this->booking->id;

        $this->js(<<<JS
            (() => {
                const modalEl = document.getElementById('{$modalId}');
                if (modalEl && window.bootstrap?.Modal) {
                    const instance = bootstrap.Modal.getInstance(modalEl)
                        ?? bootstrap.Modal.getOrCreateInstance(modalEl);
                    instance.hide();
                }
                const cleanup = () => {
                    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                    document.body.classList.remove('modal-open');
                    document.body.style.removeProperty('overflow');
                    document.body.style.removeProperty('padding-right');
                    if (modalEl) {
                        modalEl.classList.remove('show');
                        modalEl.setAttribute('aria-hidden', 'true');
                        modalEl.style.removeProperty('display');
                    }
                };
                cleanup();
                setTimeout(cleanup, 200);
            })();
        JS);
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

    public function updatedEditableServices($value, $key): void
    {
        if (!preg_match('/^(\d+)\.(excluded_from_veteran_quota|manual_discount_percentage|manual_discount_reason)$/', $key, $matches)) {
            return;
        }

        $this->applyServiceQuotaSettings((int) $matches[1], $matches[2]);
    }

    public function updatedNewExcludedFromVeteranQuota(): void
    {
        if (!$this->newExcludedFromVeteranQuota) {
            $this->newManualDiscountPercentage = '';
            $this->newManualDiscountReason = '';
        }
    }

    public function addServiceLine(): void
    {
        $this->assertBookingEditable();
        $this->assertHostCan('bookings.services', 'write');

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

        if (!$this->validateNewServiceManualDiscount()) {
            return;
        }

        $variantId = ($this->newServiceCatalogVariantId && $catalogId)
            ? (int) $this->newServiceCatalogVariantId
            : null;

        [$manualPct, $manualReason] = $this->normalizedNewServiceManualDiscount();

        BookingService::create([
            'booking_id'                 => $this->booking->id,
            'guest_sort_order'           => $this->guestSortOrder,
            'service_catalog_id'         => $catalogId,
            'service_catalog_variant_id' => $variantId,
            'name'                       => $this->newServiceName,
            'unit_price'                 => (int) $this->newServicePrice,
            'quantity'                   => $this->newServiceQty,
            'total'                      => (int) $this->newServicePrice * $this->newServiceQty,
            'sort_order'                 => $this->booking->services()->count(),
            'excluded_from_veteran_quota' => $this->newExcludedFromVeteranQuota,
            'manual_discount_percentage' => $manualPct,
            'manual_discount_reason'     => $manualReason,
        ]);

        $this->booking->refresh();
        app(ManualBookingService::class)->recalculateTotals($this->booking);
        $this->booking->refresh();
        $this->loadEditableServices();
        $addedServiceName = $this->newServiceName;
        $this->resetNewServiceForm();
        $this->dispatchBookingToast('خدمت «' . $addedServiceName . '» اضافه شد.');
        $this->dispatch('booking-services-updated');
    }

    private function resetNewServiceForm(): void
    {
        $this->newServiceCatalogId = '';
        $this->newServiceCatalogVariantId = '';
        $this->newServiceName = '';
        $this->newServicePrice = '';
        $this->newServiceQty = 1;
        $this->newExcludedFromVeteranQuota = false;
        $this->newManualDiscountPercentage = '';
        $this->newManualDiscountReason = '';
    }

    public function adjustServiceQuantity(int $serviceId, int $delta): void
    {
        $this->assertBookingEditable();
        $this->assertHostCan('bookings.services', 'edit');

        $row = $this->editableServices[$serviceId] ?? null;
        if (!$row || empty($row['id'])) {
            return;
        }

        $service = $this->booking->services()->find($serviceId);
        if (!$service || !$this->serviceBelongsToScope($service)) {
            return;
        }

        $current = (int) ($row['quantity'] ?? $service->quantity);
        $newQty = max(1, min(99, $current + $delta));
        if ($newQty === $current) {
            return;
        }

        $this->editableServices[$serviceId]['quantity'] = $newQty;
    }

    public function applyServiceQuantity(int $serviceId): void
    {
        $this->assertBookingEditable();
        $this->assertHostCan('bookings.services', 'edit');

        $row = $this->editableServices[$serviceId] ?? null;
        if (!$row || empty($row['id'])) {
            return;
        }

        $service = $this->booking->services()->find($serviceId);
        if (!$service || !$this->serviceBelongsToScope($service)) {
            return;
        }

        $this->validate([
            "editableServices.{$serviceId}.quantity" => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $newQty = (int) $row['quantity'];
        if ($newQty === (int) $service->quantity) {
            $this->editableServices[$serviceId]['saved_quantity'] = $newQty;
            return;
        }

        $service->update(['quantity' => $newQty]);

        $this->booking->refresh();
        app(ManualBookingService::class)->recalculateTotals($this->booking);
        $this->booking->refresh();
        $this->loadEditableServices();
        $this->dispatchBookingToast('تعداد «' . $service->name . '» به ' . $newQty . ' تغییر کرد.');
        $this->dispatch('booking-services-updated');
    }

    public function removeServiceLine(int $serviceId): void
    {
        $this->assertBookingEditable();
        $this->assertHostCan('bookings.services', 'delete');

        $service = $this->booking->services()->findOrFail($serviceId);
        if (!$this->serviceBelongsToScope($service)) {
            return;
        }
        $service->delete();
        $this->booking->refresh();
        app(ManualBookingService::class)->recalculateTotals($this->booking);
        $this->booking->refresh();
        $this->loadEditableServices();
        $this->dispatchBookingToast('خدمت «' . $service->name . '» حذف شد.');
        $this->dispatch('booking-services-updated');
    }

    public function applyServiceLineEdits(int $serviceId): void
    {
        $this->assertBookingEditable();
        $this->assertHostCan('bookings.services', 'edit');

        $row = $this->editableServices[$serviceId] ?? null;
        if (!$row || empty($row['id'])) {
            return;
        }

        $service = $this->booking->services()->find($serviceId);
        if (!$service || !$this->serviceBelongsToScope($service)) {
            return;
        }

        $this->validate([
            "editableServices.{$serviceId}.name"       => ['required', 'string', 'max:200'],
            "editableServices.{$serviceId}.unit_price" => ['required', 'integer', 'min:0'],
            "editableServices.{$serviceId}.quantity"   => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        if (!$this->validateServiceManualDiscountRow($serviceId, $row)) {
            return;
        }

        $excluded = !empty($row['excluded_from_veteran_quota']);
        [$manualPct, $manualReason] = $this->normalizedServiceManualDiscount($row, $excluded);

        $service->update([
            'name'                        => $row['name'],
            'unit_price'                  => (int) $row['unit_price'],
            'quantity'                    => (int) $row['quantity'],
            'excluded_from_veteran_quota' => $excluded,
            'manual_discount_percentage'  => $manualPct,
            'manual_discount_reason'      => $manualReason,
        ]);

        app(ManualBookingService::class)->recalculateTotals($this->booking);
        $this->booking->refresh();
        $this->loadEditableServices();
        $this->dispatchBookingToast('تغییرات «' . $service->name . '» اعمال شد.');
        $this->dispatch('booking-services-updated');
    }

    public function saveServiceEdits(ManualBookingService $manualBooking): void
    {
        $this->assertBookingEditable();
        $this->assertHostCan('bookings.services', 'edit');

        $this->validate([
            'editableServices.*.name'       => ['required', 'string', 'max:200'],
            'editableServices.*.unit_price' => ['required', 'integer', 'min:0'],
            'editableServices.*.quantity'   => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        if (!$this->validateEditableServiceManualDiscounts()) {
            return;
        }

        $sortOrder = 0;
        foreach ($this->editableServices as $serviceId => $row) {
            if (empty($row['id'])) {
                continue;
            }
            $service = $this->booking->services()->find($row['id']);
            if (!$service || !$this->serviceBelongsToScope($service)) {
                continue;
            }
            $qty = (int) $row['quantity'];
            $unit = (int) $row['unit_price'];
            $excluded = !empty($row['excluded_from_veteran_quota']);
            [$manualPct, $manualReason] = $this->normalizedServiceManualDiscount($row, $excluded);

            $service->update([
                'name'                       => $row['name'],
                'unit_price'                 => $unit,
                'quantity'                   => $qty,
                'sort_order'                 => $sortOrder++,
                'excluded_from_veteran_quota' => $excluded,
                'manual_discount_percentage' => $manualPct,
                'manual_discount_reason'     => $manualReason,
            ]);
        }

        $manualBooking->recalculateTotals($this->booking);
        $this->booking->refresh();
        $this->loadEditableServices();
        $this->dispatchBookingToast('همه خدمات رزرو به‌روز شد.');
        $this->dispatch('booking-services-updated');
    }

    public function uploadBookingForm(): void
    {
        $this->assertBookingEditable();
        $this->assertHostCan('bookings.forms', 'write');

        $this->validate([
            'uploadedForm' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:' . ImageUploadService::MAX_UPLOAD_KILOBYTES],
        ]);

        if ($this->booking->form_file_path) {
            Storage::disk('public')->delete($this->booking->form_file_path);
        }

        $path = app(ImageUploadService::class)->storeUploadedFile(
            $this->uploadedForm,
            'booking-forms/' . $this->booking->id
        );
        $this->booking->update(['form_file_path' => $path]);
        $this->uploadedForm = null;
        $this->booking->refresh();
        $this->dispatchBookingToast('فرم رزرو آپلود شد.');
    }

    public function deleteBookingForm(): void
    {
        $this->assertBookingEditable();
        $this->assertHostCan('bookings.forms', 'delete');

        if ($this->booking->form_file_path) {
            Storage::disk('public')->delete($this->booking->form_file_path);
            $this->booking->update(['form_file_path' => null]);
            $this->booking->refresh();
        }
        $this->dispatchBookingToast('فایل فرم رزرو حذف شد.');
    }

    protected function serviceCatalogOptions()
    {
        return app(VeteranPolicyService::class)
            ->forAccommodation($this->booking->accommodation_id)
            ->activeServices();
    }

    protected function serviceBelongsToScope(BookingService $service): bool
    {
        if ($this->guestSortOrder === null) {
            return true;
        }

        return (int) $service->guest_sort_order === $this->guestSortOrder;
    }

    protected function bookingUsesVeteranServicePolicy(): bool
    {
        return !empty($this->booking->veteran_type_applied);
    }

    protected function assertBookingEditable(): void
    {
        abort_unless(
            $this->booking->canEditBookingDetails(Auth::user()),
            403,
            'پس از پایان دوره رزرو امکان ویرایش وجود ندارد.'
        );
    }

    protected function dispatchBookingToast(string $message, string $type = 'success'): void
    {
        $this->dispatch('toast', type: $type, message: $message);
    }

    private function syncGuestLabelInSnapshot(int $sortOrder, string $label): void
    {
        $snapshot = $this->booking->guest_discount_snapshot;
        if (!is_array($snapshot) || $snapshot === []) {
            return;
        }

        $updated = false;
        foreach ($snapshot as &$slot) {
            if ((int) ($slot['sort_order'] ?? -1) === $sortOrder) {
                $slot['label'] = $label;
                $updated = true;
            }
        }
        unset($slot);

        if ($updated) {
            $this->booking->update(['guest_discount_snapshot' => $snapshot]);
            $this->booking->refresh();
        }
    }

    private function applyServiceQuotaSettings(int $serviceId, ?string $changedField = null): void
    {
        $this->assertBookingEditable();
        $this->assertHostCan('bookings.services', 'edit');

        $row = $this->editableServices[$serviceId] ?? null;
        if (!$row) {
            return;
        }

        $service = $this->booking->services()->find($serviceId);
        if (!$service || !$this->serviceBelongsToScope($service)) {
            return;
        }

        $excluded = !empty($row['excluded_from_veteran_quota']);
        if (!$excluded) {
            $this->editableServices[$serviceId]['manual_discount_percentage'] = '';
            $this->editableServices[$serviceId]['manual_discount_reason'] = '';
        }

        if (!$this->validateServiceManualDiscountRow($serviceId, $this->editableServices[$serviceId])) {
            return;
        }

        [$manualPct, $manualReason] = $this->normalizedServiceManualDiscount($this->editableServices[$serviceId], $excluded);

        $service->update([
            'excluded_from_veteran_quota' => $excluded,
            'manual_discount_percentage'  => $manualPct,
            'manual_discount_reason'      => $manualReason,
        ]);

        $this->booking->refresh();
        app(ManualBookingService::class)->recalculateTotals($this->booking);
        $this->booking->refresh();
        $this->loadEditableServices();
        $this->dispatchBookingToast($this->quotaSettingsToastMessage($service->name, $changedField, $excluded));
        $this->dispatch('booking-services-updated');
    }

    private function quotaSettingsToastMessage(string $serviceName, ?string $changedField, bool $excluded): string
    {
        return match ($changedField) {
            'excluded_from_veteran_quota' => $excluded
                ? '«' . $serviceName . '» از سهمیه ایثارگری خارج شد.'
                : '«' . $serviceName . '» به سهمیه ایثارگری اضافه شد.',
            'manual_discount_percentage', 'manual_discount_reason' => 'تخفیف دستی «' . $serviceName . '» به‌روز شد.',
            default => 'تنظیمات «' . $serviceName . '» به‌روز شد.',
        };
    }

    private function validateNewServiceManualDiscount(): bool
    {
        if (!$this->newExcludedFromVeteranQuota) {
            return true;
        }

        $pct = trim($this->newManualDiscountPercentage);
        $reason = trim($this->newManualDiscountReason);

        if ($pct === '' || (int) $pct === 0) {
            return true;
        }

        $pctInt = (int) $pct;
        if ($pctInt < 1 || $pctInt > 100) {
            $this->addError('newManualDiscountPercentage', 'درصد تخفیف باید بین ۱ تا ۱۰۰ باشد.');

            return false;
        }

        if ($reason === '') {
            $this->addError('newManualDiscountReason', 'ذکر دلیل تخفیف برای این خدمت الزامی است.');

            return false;
        }

        return true;
    }

    private function validateEditableServiceManualDiscounts(): bool
    {
        $valid = true;

        foreach ($this->editableServices as $serviceId => $row) {
            if (!$this->validateServiceManualDiscountRow((int) $serviceId, $row)) {
                $valid = false;
            }
        }

        return $valid;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function validateServiceManualDiscountRow(int $serviceId, array $row): bool
    {
        if (empty($row['excluded_from_veteran_quota'])) {
            return true;
        }

        $pct = trim((string) ($row['manual_discount_percentage'] ?? ''));
        $reason = trim((string) ($row['manual_discount_reason'] ?? ''));

        if ($pct === '' || (int) $pct === 0) {
            return true;
        }

        $pctInt = (int) $pct;
        if ($pctInt < 1 || $pctInt > 100) {
            $this->addError("editableServices.{$serviceId}.manual_discount_percentage", 'درصد تخفیف باید بین ۱ تا ۱۰۰ باشد.');

            return false;
        }

        if ($reason === '') {
            $this->addError("editableServices.{$serviceId}.manual_discount_reason", 'ذکر دلیل تخفیف برای این خدمت الزامی است.');

            return false;
        }

        return true;
    }

    /**
     * @return array{0:?int,1:?string}
     */
    private function normalizedNewServiceManualDiscount(): array
    {
        if (!$this->newExcludedFromVeteranQuota) {
            return [null, null];
        }

        return $this->normalizedServiceManualDiscount([
            'manual_discount_percentage' => $this->newManualDiscountPercentage,
            'manual_discount_reason'     => $this->newManualDiscountReason,
        ], true);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{0:?int,1:?string}
     */
    private function normalizedServiceManualDiscount(array $row, bool $excluded): array
    {
        if (!$excluded) {
            return [null, null];
        }

        $raw = trim((string) ($row['manual_discount_percentage'] ?? ''));
        if ($raw === '' || (int) $raw === 0) {
            return [null, null];
        }

        $pct = max(1, min(100, (int) $raw));
        $reason = trim((string) ($row['manual_discount_reason'] ?? ''));

        return [$pct, $reason !== '' ? $reason : null];
    }
}
