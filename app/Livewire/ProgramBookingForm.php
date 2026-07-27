<?php

namespace App\Livewire;

use App\Livewire\Concerns\AssertsHostPermissions;
use App\Livewire\Concerns\ManagesProgramBeneficiaries;
use App\Livewire\Concerns\ResolvesAccountingProvince;
use App\Livewire\Concerns\ManagesProgramEmployers;
use App\Livewire\Concerns\ManagesProgramGuests;
use App\Models\Accommodation;
use App\Models\Program;
use App\Models\ProgramBeneficiary;
use App\Models\ProgramEmployer;
use App\Models\RoomType;
use App\Models\ServiceCatalog;
use App\Services\ProgramBookingService;
use App\Services\ProgramDocumentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;
use Morilog\Jalali\Jalalian;

class ProgramBookingForm extends Component
{
    use ManagesProgramBeneficiaries;
    use ManagesProgramEmployers;
    use ManagesProgramGuests;
    use ResolvesAccountingProvince;
    use WithFileUploads;
    use AssertsHostPermissions;

    public string $panel = 'host';

    public int $step = 1;

    public int $accommodationId = 0;

    // Step 1
    public string $programType = Program::TYPE_CAMP;
    public string $title = '';
    public string $startDate = '';
    public string $endDate = '';
    public int $guestCount = 1;
    public int $roomsAllocated = 1;
    public string $contractor = '';
    public string $description = '';

    // Step 2
    /** @var array<int, array{room_type_id:int, room_rate_id:?int, room_id:int, room_name:string, room_type_name:string}> */
    public array $roomLines = [];

    // Step 3 — services
    /** @var array<int, array{service_catalog_id:string, service_catalog_variant_id:string, name:string, unit_price:int|string, quantity:int|string, is_custom:bool}> */
    public array $services = [];

    // Step 4 — financial
    public string $paymentType = Program::PAYMENT_CASH;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $paymentDocuments = [];

    public int|string $basePrice = 0;
    public int|string $discountAmount = 0;
    public int|string $depositAmount = 0;
    public string $notes = '';

    // Result
    public ?Program $createdProgram = null;

    public function mount(string $panel = 'host', ?int $accommodationId = null): void
    {
        $this->panel = $panel;

        if ($accommodationId) {
            $this->accommodationId = $accommodationId;
        } elseif ($panel === 'host') {
            $first = Auth::user()->accommodations()->first();
            if ($first) {
                $this->accommodationId = $first->id;
            }
        }

        $this->services = [$this->emptyServiceRow()];
    }

    public function updatedAccommodationId(): void
    {
        $this->roomLines = [];
        $this->beneficiaryRows = [];
    }

    public function updatedRoomsAllocated(): void
    {
        if (count($this->roomLines) > $this->roomsAllocated) {
            $this->roomLines = array_values(array_slice($this->roomLines, 0, $this->roomsAllocated));
        }
    }

    public function nextStep(): void
    {
        $this->validateStep($this->step);

        if ($this->step === 4) {
            $this->hydrateGuestStep();
        }

        $this->step = min(7, $this->step + 1);
        $this->dispatch('program-step-changed', step: $this->step);
    }

    public function prevStep(): void
    {
        $this->step = max(1, $this->step - 1);
        $this->dispatch('program-step-changed', step: $this->step);
    }

    public function goToStep(int $target): void
    {
        if ($target < 1 || $target > 7 || $target === $this->step) {
            return;
        }

        if ($target < $this->step) {
            $this->step = $target;
            $this->dispatch('program-step-changed', step: $this->step);
            return;
        }

        for ($s = $this->step; $s < $target; $s++) {
            $this->validateStep($s);
        }

        if ($target >= 5) {
            $this->hydrateGuestStep();
        }

        $this->step = $target;
        $this->dispatch('program-step-changed', step: $this->step);
    }

    public function openRoomPicker(): void
    {
        if ($this->roomsRemaining <= 0 || !$this->startDate || !$this->endDate || $this->accommodationId <= 0) {
            return;
        }

        if (!$this->checkInGregorian || !$this->checkOutGregorian) {
            $this->addError('roomLines', 'تاریخ‌های برنامه معتبر نیستند.');
            return;
        }

        $detail = [
            'accommodationId'  => (int) $this->accommodationId,
            'roomTypeName'     => 'انتخاب اتاق‌های اقامتگاه',
            'checkIn'          => $this->checkInGregorian,
            'checkOut'         => $this->checkOutGregorian,
            'excludeRoomIds'   => $this->excludedRoomIds,
            'roomsToSelect'    => $this->roomsRemaining,
            'explicitConfirm'  => true,
        ];

        $encoded = json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $this->js("window.dispatchEvent(new CustomEvent('manual-booking-open-room-picker', { detail: {$encoded} }))");
    }

    public function clearRoomLines(): void
    {
        $this->roomLines = [];
    }

    public function onRoomsSelected(array $rooms): void
    {
        if ($rooms === []) {
            return;
        }

        $accommodation = $this->resolveAccommodation();
        $remaining = $this->roomsAllocated - count($this->roomLines);

        foreach ($rooms as $room) {
            if ($remaining <= 0) {
                break;
            }

            $roomId = (int) ($room['roomId'] ?? $room['id'] ?? 0);
            $roomTypeId = (int) ($room['roomTypeId'] ?? $room['room_type_id'] ?? 0);
            if ($roomId <= 0 || $roomTypeId <= 0) {
                continue;
            }

            if (collect($this->roomLines)->contains(fn ($line) => (int) $line['room_id'] === $roomId)) {
                continue;
            }

            $roomType = $accommodation->roomTypes->firstWhere('id', $roomTypeId);
            if (!$roomType) {
                continue;
            }

            $rateId = $this->defaultRateIdForType($roomTypeId);

            $this->roomLines[] = [
                'room_type_id'   => $roomTypeId,
                'room_rate_id'   => $rateId,
                'room_id'        => $roomId,
                'room_name'      => (string) ($room['roomName'] ?? $room['name'] ?? ''),
                'room_type_name' => (string) ($room['roomTypeName'] ?? $room['room_type_name'] ?? $roomType->name),
            ];

            $remaining--;
        }
    }

    public function removeRoomLine(int $index): void
    {
        if (!isset($this->roomLines[$index])) {
            return;
        }

        unset($this->roomLines[$index]);
        $this->roomLines = array_values($this->roomLines);
    }

    public function addService(): void
    {
        $this->services[] = $this->emptyServiceRow();
    }

    public function removeService(int $index): void
    {
        if (!isset($this->services[$index])) {
            return;
        }

        unset($this->services[$index]);
        $this->services = array_values($this->services);

        if ($this->services === []) {
            $this->services = [$this->emptyServiceRow()];
        }
    }

    public function updatedServices($value, string $key): void
    {
        if (!str_contains($key, 'service_catalog_id')) {
            return;
        }

        [$index] = explode('.', $key);
        $index = (int) $index;
        $catalogId = $this->services[$index]['service_catalog_id'] ?? '';

        if ($catalogId === 'custom' || $catalogId === '') {
            $this->services[$index]['is_custom'] = $catalogId === 'custom';
            if ($catalogId === 'custom') {
                $this->services[$index]['service_catalog_variant_id'] = '';
            }
            return;
        }

        $catalog = ServiceCatalog::with('activeVariants')->find((int) $catalogId);
        if (!$catalog) {
            return;
        }

        $this->services[$index]['name'] = $catalog->name;
        $this->services[$index]['is_custom'] = false;

        $variantId = (int) ($this->services[$index]['service_catalog_variant_id'] ?? 0);
        $variant = $variantId > 0 ? $catalog->activeVariants->firstWhere('id', $variantId) : $catalog->activeVariants->first();

        if ($variant) {
            $this->services[$index]['service_catalog_variant_id'] = (string) $variant->id;
            $this->services[$index]['unit_price'] = $variant->price;
        } elseif ($catalog->default_price) {
            $this->services[$index]['unit_price'] = $catalog->default_price;
        }
    }

    public function submit(ProgramBookingService $service): void
    {
        if ($this->panel === 'host') {
            $this->assertHostCan('programs.create', 'write');
        }

        for ($s = 1; $s <= 6; $s++) {
            $this->validateStep($s);
        }

        $this->validate([
            'paymentDocuments.*' => ProgramDocumentService::fileRules(),
            'beneficiaryRows.*.documents.*' => ProgramDocumentService::fileRules(),
            'beneficiaryDocumentUploads.*' => ['nullable', 'array'],
            'beneficiaryDocumentUploads.*.*' => ProgramDocumentService::fileRules(),
            'guestListDocuments.*' => ProgramDocumentService::spreadsheetRules(),
        ]);

        try {
            $accommodation = $this->resolveAccommodation();
            $checkIn = $this->toGregorian($this->startDate);
            $checkOut = $this->toGregorian($this->endDate);

            $this->createdProgram = $service->create($accommodation, [
                'title'              => $this->title,
                'description'        => $this->description,
                'program_type'       => $this->programType,
                'program_employer_id' => $this->resolvedProgramEmployerId(),
                'contractor'         => $this->contractor,
                'guest_count'        => $this->guestCount,
                'rooms_allocated'    => $this->roomsAllocated,
                'check_in'           => $checkIn,
                'check_out'          => $checkOut,
                'room_lines'         => $this->roomLines,
                'services'           => $this->filledServices(),
                'payment_type'       => $this->paymentType,
                'payment_documents'  => $this->paymentDocuments,
                'base_price'         => $this->parsedAmount($this->basePrice),
                'discount_amount'    => $this->parsedAmount($this->discountAmount),
                'deposit_amount'     => $this->parsedAmount($this->depositAmount),
                'beneficiary_costs'  => $this->filledBeneficiaryCosts(),
                'guest_details'      => $this->filledGuestDetails(),
                'guest_list_documents' => $this->guestListDocuments,
                'notes'              => $this->notes,
            ], Auth::user());

            $this->step = 7;
            session()->flash('status', 'برنامه «' . $this->createdProgram->title . '» با موفقیت ثبت شد.');
            $this->dispatch('toast', type: 'success', message: 'برنامه با موفقیت ثبت شد.');
        } catch (\Throwable $e) {
            $this->addError('submit', $e->getMessage());
        }
    }

    public function getServicesSubtotalProperty(): int
    {
        return $this->sumServices($this->filledServices());
    }

    public function getTotalAmountProperty(): int
    {
        $base = $this->parsedAmount($this->basePrice);
        $discount = $this->parsedAmount($this->discountAmount);

        return max(0, $base + $this->servicesSubtotal - $discount);
    }

    public function getRemainingAmountProperty(): int
    {
        return max(0, $this->totalAmount - $this->parsedAmount($this->depositAmount));
    }

    public function getExcludedRoomIdsProperty(): array
    {
        return collect($this->roomLines)->pluck('room_id')->map(fn ($id) => (int) $id)->all();
    }

    public function getRoomsRemainingProperty(): int
    {
        return max(0, $this->roomsAllocated - count($this->roomLines));
    }

    public function getCheckInGregorianProperty(): string
    {
        return $this->toGregorian($this->startDate) ?? '';
    }

    public function getCheckOutGregorianProperty(): string
    {
        return $this->toGregorian($this->endDate) ?? '';
    }

    public function render()
    {
        $accommodation = null;
        $roomTypes = collect();
        $serviceCatalog = collect();
        $beneficiaries = collect();
        $myAccommodations = collect();

        if ($this->accommodationId > 0) {
            try {
                $accommodation = $this->resolveAccommodation();
                $roomTypes = $accommodation->roomTypes->where('is_active', true)->values();
                $serviceCatalog = ServiceCatalog::forAccommodation($accommodation->id)->active()->ordered()->with('activeVariants')->get();
            } catch (\Throwable) {
                $accommodation = null;
            }
        }

        $beneficiaries = ProgramBeneficiary::orderBy('name')->get();
        $employers = ProgramEmployer::orderBy('name')->get();

        if ($this->panel === 'host') {
            $myAccommodations = Auth::user()->managedAccommodationOptions();
        } elseif ($this->panel === 'admin') {
            $myAccommodations = Accommodation::orderBy('name')->get(['id', 'name']);
        }

        return view('livewire.program-booking-form', compact(
            'accommodation',
            'roomTypes',
            'serviceCatalog',
            'beneficiaries',
            'employers',
            'myAccommodations',
        ));
    }

    private function validateStep(int $step): void
    {
        match ($step) {
            1 => $this->validate([
                'accommodationId' => ['required', 'integer', 'min:1'],
                'programType'       => ['required', 'in:camp,event,other'],
                'title'             => ['required', 'string', 'max:200'],
                'startDate'         => ['required', 'string'],
                'endDate'           => ['required', 'string'],
                'guestCount'        => ['required', 'integer', 'min:1'],
                'roomsAllocated'    => ['required', 'integer', 'min:1'],
                'programEmployerId' => ['required', 'integer', 'min:1', 'exists:program_employers,id'],
                'contractor'        => ['nullable', 'string', 'max:200'],
                'description'       => ['nullable', 'string', 'max:5000'],
            ], [], [
                'accommodationId' => 'اقامتگاه',
                'programType'     => 'نوع برنامه',
                'title'           => 'عنوان برنامه',
                'startDate'       => 'تاریخ شروع',
                'endDate'         => 'تاریخ پایان',
                'guestCount'      => 'تعداد نفرات',
                'roomsAllocated'  => 'تعداد اتاق اختصاص داده شده به این رزرو',
                'programEmployerId' => 'کارفرما',
            ]),
            2 => $this->validateStepRooms(),
            3 => null,
            4 => $this->validate([
                'paymentType' => ['required', 'in:payment,credit,supportive'],
                'basePrice'   => ['required'],
                'depositAmount' => ['nullable'],
                'discountAmount' => ['nullable'],
            ], [], [
                'paymentType' => 'نوع پرداخت',
                'basePrice'   => 'قیمت پایه',
            ]),
            5 => $this->validateStepGuests(),
            6 => null,
            default => null,
        };

        if ($step === 1) {
            $start = $this->toGregorian($this->startDate);
            $end = $this->toGregorian($this->endDate);

            if (!$start) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'startDate' => 'تاریخ شروع معتبر نیست.',
                ]);
            }
            if (!$end) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'endDate' => 'تاریخ پایان معتبر نیست.',
                ]);
            }
            if ($end <= $start) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'endDate' => 'تاریخ پایان باید بعد از تاریخ شروع باشد.',
                ]);
            }

            if ($this->panel === 'host') {
                $accIds = Auth::user()->managedAccommodationIds();
                if (!$accIds->contains($this->accommodationId)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'accommodationId' => 'اقامتگاه مجاز نیست.',
                    ]);
                }
            }
        }

        if ($step === 4 && in_array($this->paymentType, [Program::PAYMENT_CREDIT, Program::PAYMENT_SUPPORTIVE], true)) {
            if ($this->paymentDocuments === [] && $this->parsedAmount($this->depositAmount) === 0) {
                // documents optional but encouraged — no hard fail
            }
        }
    }

    private function validateStepRooms(): void
    {
        $checkIn = $this->toGregorian($this->startDate);
        $checkOut = $this->toGregorian($this->endDate);

        if (!$checkIn || !$checkOut) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'roomLines' => 'ابتدا تاریخ‌های برنامه را در مرحله قبل ثبت کنید.',
            ]);
        }

        if (count($this->roomLines) !== $this->roomsAllocated) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'roomLines' => 'باید دقیقاً ' . $this->roomsAllocated . ' اتاق فیزیکی انتخاب شود. (' . count($this->roomLines) . ' انتخاب شده)',
            ]);
        }
    }

    private function resolveAccommodation(): Accommodation
    {
        return Accommodation::with(['roomTypes.rates', 'roomTypes.rooms'])
            ->findOrFail($this->accommodationId);
    }

    private function defaultRateIdForType(?int $roomTypeId): ?int
    {
        if (!$roomTypeId) {
            return null;
        }

        $accommodation = $this->resolveAccommodation();
        $roomType = $accommodation->roomTypes->firstWhere('id', $roomTypeId);

        return $roomType?->rates?->where('is_active', true)->sortBy('id')->first()?->id;
    }

    /** @return array<string, mixed> */
    private function emptyServiceRow(): array
    {
        return [
            'service_catalog_id'         => '',
            'service_catalog_variant_id' => '',
            'name'                       => '',
            'unit_price'                 => 0,
            'quantity'                   => 1,
            'is_custom'                  => false,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function filledServices(): array
    {
        $rows = [];

        foreach ($this->services as $service) {
            if (empty(trim((string) ($service['name'] ?? '')))) {
                continue;
            }

            $catalogId = $service['service_catalog_id'] ?? '';
            if ($catalogId === 'custom' || $catalogId === '') {
                $catalogId = null;
            }

            $variantId = $service['service_catalog_variant_id'] ?? '';
            if ($variantId === '') {
                $variantId = null;
            }

            $rows[] = [
                'service_catalog_id'         => $catalogId ? (int) $catalogId : null,
                'service_catalog_variant_id' => $variantId ? (int) $variantId : null,
                'name'                       => trim((string) $service['name']),
                'unit_price'                 => $this->parsedAmount($service['unit_price'] ?? 0),
                'quantity'                   => max(1, (int) ($service['quantity'] ?? 1)),
            ];
        }

        return $rows;
    }

    /** @param  array<int, array<string, mixed>>  $services */
    private function sumServices(array $services): int
    {
        $total = 0;

        foreach ($services as $service) {
            $qty = max(1, (int) ($service['quantity'] ?? 1));
            $total += $qty * (int) ($service['unit_price'] ?? 0);
        }

        return $total;
    }

    private function parsedAmount(int|string $value): int
    {
        return (int) str_replace([',', ' '], '', (string) $value);
    }

    private function toGregorian(?string $jalali): ?string
    {
        if (!$jalali) {
            return null;
        }

        try {
            $normalized = strtr(trim($jalali), [
                '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
                '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
                '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            ]);

            return Jalalian::fromFormat('Y/m/d', $normalized)->toCarbon()->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function accountingProvince(): ?\App\Models\Province
    {
        if ($this->accommodationId <= 0) {
            return null;
        }

        $accommodation = Accommodation::query()
            ->with(['city.province', 'county.province'])
            ->find($this->accommodationId);

        return $this->resolveAccountingProvinceFromAccommodation($accommodation);
    }
}
