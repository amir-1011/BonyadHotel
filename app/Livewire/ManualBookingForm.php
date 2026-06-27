<?php

namespace App\Livewire;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\User;
use App\Services\BookingPricingService;
use App\Services\ManualBookingService;
use App\Services\NationalIdVerificationService;
use App\Services\RoomAvailabilityService;
use App\Services\VeteranPolicyService;
use App\Support\VeteranGroups;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class ManualBookingForm extends Component
{
    public Accommodation $accommodation;
    public string $panel = 'admin';

    public int $step = 1;

    // Step 1 — room & stay (shared dates + committed room lines)
    public string $checkIn = '';
    public string $checkOut = '';

    /** @var array<int, array{room_type_id:int, room_rate_id:?int, room_id:?int, room_name:?string, adults:int, children_under_6:int, extra_guests:int, bill_full_rooms:bool}> */
    public array $roomLines = [];

    // Current draft while configuring a room in the drawer
    public ?int $roomTypeId = null;
    public ?int $roomRateId = null;
    public int $adults = 1;
    public int $childrenUnder6 = 0;
    public int $extraGuests = 0;
    public bool $billFullRooms = false;

    // Step 2 — services
    /** @var array<int, array{service_catalog_id:string, service_catalog_variant_id:string, name:string, unit_price:int|string, quantity:int|string, discount_override:string, is_custom:bool}> */
    public array $services = [];

    // Step 3 — booker identity & veteran discount
    public string $bookerNationalId = '';
    public bool $bookerVerified = false;
    public bool $bookerIsExistingUser = false;
    public string $bookerVerifyMessage = '';
    public string $veteranType = '';
    public string $secondaryVeteranType = '';

    /** @var array<int, string> */
    public array $selectedVeteranTypes = [];

    // Step 4 — payment & contacts
    public string $paymentMethod = 'cash';
    public ?int $userId = null;
    public string $guestContactName = '';
    public string $guestContactMobile = '';
    public string $notes = '';

    /** @var array<int, array{full_name:string, national_id:string, mobile:string, relation:string, excluded_from_veteran_discount:bool, manual_discount_percentage:string, manual_discount_reason:string}> */
    public array $guestDetails = [];

    public ?int $createdBookingId = null;

    public ?Booking $createdBooking = null;

    public ?int $prefillRoomTypeId = null;

    public ?int $prefillRoomRateId = null;

    public ?int $prefillRoomId = null;

    public ?string $prefillRoomName = null;

    public bool $prefillFocusDates = false;

    private function veteranPolicy(): VeteranPolicyService
    {
        return app(VeteranPolicyService::class)->forAccommodation($this->accommodation->id);
    }

    public function mount(Accommodation $accommodation, string $panel = 'admin'): void
    {
        $this->accommodation = $accommodation->load(['roomTypes.rates', 'roomTypes.rooms', 'city']);
        $this->panel = $panel;
        app(\App\Services\VeteranPolicyProvisioner::class)->seedForAccommodation($accommodation);
        $this->services = [$this->emptyServiceRow()];
        $this->syncGuestDetailRows();
        $this->applyPrefillFromRequest();
    }

    private function applyPrefillFromRequest(): void
    {
        $roomTypeId = request()->integer('room_type_id') ?: null;
        $roomRateId = request()->integer('room_rate_id') ?: null;
        $roomId = request()->integer('room_id') ?: null;

        if (!$roomTypeId || !$roomRateId) {
            return;
        }

        $roomType = $this->accommodation->roomTypes->firstWhere('id', $roomTypeId);
        if (!$roomType) {
            return;
        }

        $rate = $roomType->rates->firstWhere('id', $roomRateId);
        if (!$rate) {
            return;
        }

        if ($roomId) {
            $room = $roomType->rooms->firstWhere('id', $roomId);
            if (!$room) {
                $roomId = null;
            } else {
                $this->prefillRoomName = $room->name;
            }
        }

        $this->prefillRoomTypeId = $roomTypeId;
        $this->prefillRoomRateId = $roomRateId;
        $this->prefillRoomId = $roomId;
        $this->prefillFocusDates = request()->query('focus') === 'dates';
    }

    public function updatedBookerNationalId(): void
    {
        $this->resetBookerVerification();
    }

    public function updatedAdults(): void
    {
        $this->adults = max(1, $this->adults);
        $this->syncGuestDetailRows();
    }

    public function updatedChildrenUnder6(): void
    {
        $this->childrenUnder6 = max(0, $this->childrenUnder6);
        $this->syncGuestDetailRows();
    }

    public function updatedRoomTypeId(): void
    {
        $this->roomRateId = null;
    }

    public function updatedVeteranType(): void
    {
        $this->syncSelectedVeteranTypesFromFields();
        $this->refreshServiceDiscountOverrides();
        $this->clearManualDiscountsForVeteranEligibleGuests();
        $this->dispatch('manual-booking-set-discount', pct: $this->discountPct);
    }

    public function updatedSecondaryVeteranType(): void
    {
        $this->syncSelectedVeteranTypesFromFields();
        $this->refreshServiceDiscountOverrides();
        $this->clearManualDiscountsForVeteranEligibleGuests();
        $this->dispatch('manual-booking-set-discount', pct: $this->discountPct);
    }

    public function updatedSelectedVeteranTypes(): void
    {
        $this->normalizeSelectedVeteranTypes();
        $this->refreshServiceDiscountOverrides();
        $this->clearManualDiscountsForVeteranEligibleGuests();
        $this->dispatch('manual-booking-set-discount', pct: $this->discountPct);
    }

    private function syncSelectedVeteranTypesFromFields(): void
    {
        $types = array_values(array_filter([
            $this->veteranType ?: null,
            $this->secondaryVeteranType ?: null,
        ]));

        $this->selectedVeteranTypes = $this->veteranPolicy()->normalizeVeteranTypes($types);
        [$primary, $secondary] = $this->veteranPolicy()->splitVeteranTypes($this->selectedVeteranTypes);
        $this->veteranType = $primary ?? '';
        $this->secondaryVeteranType = $secondary ?? '';
    }

    private function normalizeSelectedVeteranTypes(): void
    {
        $this->selectedVeteranTypes = $this->veteranPolicy()->normalizeVeteranTypes($this->selectedVeteranTypes);

        if (count($this->selectedVeteranTypes) > 2) {
            $this->selectedVeteranTypes = array_slice($this->selectedVeteranTypes, 0, 2);
        }

        [$primary, $secondary] = $this->veteranPolicy()->splitVeteranTypes($this->selectedVeteranTypes);
        $this->veteranType = $primary ?? '';
        $this->secondaryVeteranType = $secondary ?? '';
    }

    /**
     * @return array<int, string>
     */
    private function resolvedVeteranTypes(): array
    {
        $types = !empty($this->selectedVeteranTypes)
            ? $this->selectedVeteranTypes
            : array_values(array_filter([
                $this->veteranType ?: null,
                $this->secondaryVeteranType ?: null,
            ]));

        return $this->veteranPolicy()->normalizeVeteranTypes($types);
    }

    public function updatedGuestDetails($value, $key): void
    {
        if (!str_contains($key, 'excluded_from_veteran_discount')) {
            return;
        }

        [$index] = explode('.', $key);
        $index = (int) $index;

        if ($this->guestReceivesVeteranDiscount($index)) {
            $this->guestDetails[$index]['manual_discount_percentage'] = '';
            $this->guestDetails[$index]['manual_discount_reason'] = '';
        }
    }

    /**
     * Re-compute discount_override for all catalog-linked service rows
     * whenever the veteran type changes, using the same logic as updatedServices().
     */
    private function refreshServiceDiscountOverrides(): void
    {
        $policy = $this->veteranPolicy();

        $types = $this->resolvedVeteranTypes();
        $primaryType = $types[0] ?? null;

        foreach ($this->services as $index => $service) {
            $catalogId = $service['service_catalog_id'] ?? '';
            if ($catalogId === '' || $catalogId === 'custom' || $catalogId === '0') {
                continue;
            }

            $rule = count($types) > 1
                ? $policy->mergedServiceDiscountRule($types, (int) $catalogId)
                : $policy->serviceDiscountRule($primaryType, (int) $catalogId);

            $matrixPct = $rule['discount_percentage'];
            if (
                $rule['min_discount'] !== null
                && $rule['max_discount'] !== null
                && $matrixPct >= $rule['min_discount']
                && $matrixPct <= $rule['max_discount']
            ) {
                $this->services[$index]['discount_override'] = (string) $matrixPct;
            } else {
                $this->services[$index]['discount_override'] = '';
            }
        }
    }

    public function verifyBooker(): void
    {
        $this->resetErrorBag('bookerNationalId');
        $this->validate([
            'bookerNationalId' => ['required', 'digits:10'],
        ]);

        $nationalId = preg_replace('/\D/', '', $this->bookerNationalId);
        $this->bookerNationalId = $nationalId;

        $existing = $this->findGuestUserByNationalId($nationalId);

        if ($existing) {
            $this->applyExistingBooker($existing);
            $this->bookerVerifyMessage = 'کاربر در سیستم یافت شد: ' . ($existing->name ?: $existing->mobile);
        } else {
            $anyUser = User::where('national_id', $nationalId)->first();
            if ($anyUser) {
                if ($anyUser->isAdmin() || $anyUser->isHost()) {
                    $this->addError('bookerNationalId', 'این کد ملی متعلق به حساب کارکنان است و قابل استفاده برای رزرو مهمان نیست.');
                } else {
                    $this->addError('bookerNationalId', 'این کد ملی قبلاً ثبت شده است. لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.');
                }
                $this->bookerVerified = false;
                return;
            }

            $result = app(NationalIdVerificationService::class)->verify($nationalId);
            if (!$result['valid']) {
                $this->addError('bookerNationalId', $result['message']);
                $this->bookerVerified = false;
                return;
            }

            $this->userId = null;
            $this->bookerIsExistingUser = false;
            $this->guestContactName = '';
            $this->guestContactMobile = '';
            $this->veteranType = $result['veteran_type'] ?? '';
            $this->secondaryVeteranType = '';
            $this->syncSelectedVeteranTypesFromFields();

            $this->bookerVerifyMessage = $this->veteranType
                ? 'کاربر جدید — گروه پیشنهادی: ' . VeteranGroups::label($this->veteranType, $this->accommodation->id)
                : 'کاربر جدید — بدون تخفیف ایثارگری (کاربر عادی)';
        }

        $this->bookerVerified = true;
        $this->syncBookerToGuestDetails();
        $this->dispatch('manual-booking-set-discount', pct: $this->discountPct);
    }

    public function updatedGuestContactMobile(): void
    {
        if ($this->bookerVerified && !$this->bookerIsExistingUser) {
            $this->validateNewBookerContacts(mobileOnly: true);
        }
    }

    public function resetBookerVerificationFromUi(): void
    {
        $this->bookerNationalId = '';
        $this->resetBookerVerification();
    }

    public function updatedServices($value, $key): void
    {
        if (str_contains($key, 'service_catalog_variant_id')) {
            [$index] = explode('.', $key);
            $this->applyVariantToServiceRow((int) $index);
            return;
        }

        if (!str_contains($key, 'service_catalog_id')) {
            return;
        }

        [$index] = explode('.', $key);
        $index = (int) $index;
        $catalogId = $this->services[$index]['service_catalog_id'] ?? '';

        $this->resetErrorBag("services.{$index}.service_catalog_id");
        $this->resetErrorBag("services.{$index}.service_catalog_variant_id");

        if ($catalogId === 'custom') {
            $this->services[$index]['is_custom'] = true;
            $this->services[$index]['service_catalog_variant_id'] = '';
            $this->services[$index]['name'] = '';
            $this->services[$index]['unit_price'] = '';
            $this->services[$index]['discount_override'] = '';
            return;
        }

        if ($catalogId === '' || $catalogId === '0') {
            $this->services[$index]['is_custom'] = false;
            $this->services[$index]['service_catalog_variant_id'] = '';
            return;
        }

        $service = $this->veteranPolicy()->serviceById((int) $catalogId);
        if (!$service) {
            return;
        }

        $this->services[$index]['is_custom'] = false;
        $this->services[$index]['service_catalog_variant_id'] = '';
        $this->services[$index]['discount_override'] = '';
        $this->services[$index]['name'] = $service->name;
        $this->services[$index]['unit_price'] = '';

        $activeVariants = $service->variants->where('is_active', true);
        if ($activeVariants->isNotEmpty()) {
            $this->applyServiceDiscountOverride($index, (int) $catalogId);
            return;
        }

        $this->addError(
            "services.{$index}.service_catalog_id",
            'برای این خدمت نوع و قیمت تعریف نشده. از تنظیمات ایثارگری انواع را اضافه کنید.',
        );
    }

    private function applyVariantToServiceRow(int $index): void
    {
        $catalogId = (int) ($this->services[$index]['service_catalog_id'] ?? 0);
        $variantId = (int) ($this->services[$index]['service_catalog_variant_id'] ?? 0);

        if ($catalogId <= 0 || $variantId <= 0) {
            return;
        }

        $service = $this->veteranPolicy()->serviceById($catalogId);
        if (!$service) {
            return;
        }

        $variant = $service->variants->firstWhere('id', $variantId);
        if (!$variant || !$variant->is_active) {
            return;
        }

        $this->services[$index]['name'] = $service->name . ' — ' . $variant->name;
        $this->services[$index]['unit_price'] = $variant->price;
        $this->resetErrorBag("services.{$index}.service_catalog_id");
        $this->resetErrorBag("services.{$index}.service_catalog_variant_id");
        $this->applyServiceDiscountOverride($index, $catalogId);
    }

    private function applyServiceDiscountOverride(int $index, int $catalogId): void
    {
        $types = $this->resolvedVeteranTypes();
        $primaryType = $types[0] ?? null;
        $rule = count($types) > 1
            ? $this->veteranPolicy()->mergedServiceDiscountRule($types, $catalogId)
            : $this->veteranPolicy()->serviceDiscountRule($primaryType, $catalogId);
        $matrixPct = $rule['discount_percentage'];
        if (
            $rule['min_discount'] !== null
            && $rule['max_discount'] !== null
            && $matrixPct >= $rule['min_discount']
            && $matrixPct <= $rule['max_discount']
        ) {
            $this->services[$index]['discount_override'] = (string) $matrixPct;
        } else {
            $this->services[$index]['discount_override'] = '';
        }
    }

    #[On('manual-booking-sync')]
    public function syncFromDrawer(
        $checkIn = '',
        $checkOut = '',
        $guests = 1,
        $roomTypeId = null,
        $roomRateId = null,
        $extraGuests = 0,
        $billFullRooms = false,
        $childrenUnder6 = 0,
        $adults = null,
    ): void {
        $this->checkIn = $checkIn ?: '';
        $this->checkOut = $checkOut ?: '';
        $this->adults = max(1, (int) ($adults ?? $guests));
        $this->roomTypeId = $roomTypeId ? (int) $roomTypeId : null;
        $this->roomRateId = $roomRateId ? (int) $roomRateId : null;
        $this->extraGuests = max(0, (int) $extraGuests);
        $this->billFullRooms = (bool) $billFullRooms;
        $this->childrenUnder6 = max(0, (int) $childrenUnder6);
    }

    #[On('manual-booking-get-excluded-rooms')]
    public function sendExcludedRoomIds(): void
    {
        $ids = collect($this->roomLines)
            ->pluck('room_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $this->dispatch('manual-booking-excluded-rooms', roomIds: $ids);
    }

    #[On('manual-booking-commit-room')]
    public function commitRoomFromDrawer(
        $checkIn = '',
        $checkOut = '',
        $guests = 1,
        $roomTypeId = null,
        $roomRateId = null,
        $extraGuests = 0,
        $billFullRooms = false,
        $childrenUnder6 = 0,
        $adults = null,
        $roomId = null,
        $roomName = null,
    ): void {
        $rooms = [];
        if ($roomId) {
            $rooms = [['room_id' => (int) $roomId, 'room_name' => $roomName ? (string) $roomName : null]];
        }

        $this->commitRoomsFromDrawer(
            $checkIn,
            $checkOut,
            $guests,
            $roomTypeId,
            $roomRateId,
            $extraGuests,
            $billFullRooms,
            $childrenUnder6,
            $adults,
            $rooms,
        );
    }

    #[On('manual-booking-commit-rooms')]
    public function commitRoomsFromDrawer(
        $checkIn = '',
        $checkOut = '',
        $guests = 1,
        $roomTypeId = null,
        $roomRateId = null,
        $extraGuests = 0,
        $billFullRooms = false,
        $childrenUnder6 = 0,
        $adults = null,
        $rooms = [],
    ): void {
        $this->resetErrorBag('roomLines');

        $this->syncFromDrawer(
            $checkIn,
            $checkOut,
            $guests,
            $roomTypeId,
            $roomRateId,
            $extraGuests,
            $billFullRooms,
            $childrenUnder6,
            $adults,
        );

        if (!$this->checkIn || !$this->checkOut) {
            $this->addError('checkIn', 'تاریخ ورود و خروج الزامی است.');
            return;
        }

        if (!$this->roomTypeId) {
            $this->addError('roomLines', 'لطفاً نوع اتاق را انتخاب کنید.');
            return;
        }

        if (!empty($this->roomLines) && ($this->checkIn !== '' || $this->checkOut !== '')) {
            if ($this->checkIn !== ($checkIn ?: '') || $this->checkOut !== ($checkOut ?: '')) {
                $this->addError('checkIn', 'تاریخ ورود و خروج باید برای همه اتاق‌ها یکسان باشد.');
                return;
            }
        }

        if (empty($this->checkIn)) {
            $this->checkIn = $checkIn ?: '';
            $this->checkOut = $checkOut ?: '';
        }

        $roomType = $this->resolvedRoomType();
        $roomsNeeded = app(BookingPricingService::class)->roomsNeeded(
            $this->adults + $this->childrenUnder6,
            $this->extraGuests,
            $roomType,
            $this->childrenUnder6,
            $this->accommodation,
        );

        $normalizedRooms = $this->normalizePhysicalRoomsPayload($rooms);
        if (!empty($normalizedRooms) && count($normalizedRooms) !== $roomsNeeded) {
            $this->addError(
                'roomLines',
                'برای ' . ($this->adults + $this->childrenUnder6) . ' نفر باید دقیقاً ' . $roomsNeeded . ' اتاق فیزیکی انتخاب شود.',
            );
            return;
        }

        $excludeIds = collect($this->roomLines)->pluck('room_id')->filter()->map(fn ($id) => (int) $id)->all();
        $availability = app(RoomAvailabilityService::class);

        foreach ($normalizedRooms as $picked) {
            $room = Room::with('roomType')->find($picked['room_id']);
            if (!$room || $room->room_type_id !== $this->roomTypeId) {
                $this->addError('roomLines', 'اتاق انتخاب‌شده معتبر نیست.');
                return;
            }

            $otherAssigned = array_merge(
                $excludeIds,
                collect($normalizedRooms)->pluck('room_id')->reject(fn ($id) => $id === $picked['room_id'])->all(),
            );

            if (!$availability->isRoomAvailable($room, $this->checkIn, $this->checkOut, $otherAssigned)) {
                $this->addError('roomLines', 'اتاق «' . $room->name . '» در بازه انتخابی در دسترس نیست.');
                return;
            }
        }

        $capacity = max(1, (int) ($roomType?->capacity ?? 1));
        $lineCount = !empty($normalizedRooms) ? count($normalizedRooms) : max(1, $roomsNeeded);
        $guestSplits = $this->splitGuestsAcrossRooms(
            $this->adults,
            $this->childrenUnder6,
            $this->extraGuests,
            $lineCount,
            $capacity,
        );

        for ($i = 0; $i < $lineCount; $i++) {
            $picked = $normalizedRooms[$i] ?? null;
            $roomIdInt = $picked['room_id'] ?? null;
            $split = $guestSplits[$i] ?? ['adults' => $this->adults, 'children_under_6' => $this->childrenUnder6, 'extra_guests' => 0];

            $this->roomLines[] = [
                'room_type_id'     => $this->roomTypeId,
                'room_rate_id'     => $this->roomRateId,
                'room_id'          => $roomIdInt,
                'room_name'        => $picked['room_name'] ?? ($roomIdInt ? Room::find($roomIdInt)?->name : null),
                'adults'           => $split['adults'],
                'children_under_6' => $split['children_under_6'],
                'extra_guests'     => (int) ($split['extra_guests'] ?? 0),
                'bill_full_rooms'  => $this->billFullRooms,
            ];
        }

        $this->roomTypeId = null;
        $this->roomRateId = null;
        $this->adults = 1;
        $this->childrenUnder6 = 0;
        $this->extraGuests = 0;
        $this->billFullRooms = false;

        $this->syncGuestDetailRows();
        $this->dispatch('manual-booking-room-committed', checkIn: $this->checkIn, checkOut: $this->checkOut);
    }

    public function removeRoomLine(int $index): void
    {
        unset($this->roomLines[$index]);
        $this->roomLines = array_values($this->roomLines);

        if (empty($this->roomLines)) {
            $this->checkIn = '';
            $this->checkOut = '';
            $this->dispatch('manual-booking-dates-unlocked');
        }

        $this->syncGuestDetailRows();
    }

    public function addService(): void
    {
        $this->services[] = $this->emptyServiceRow();
    }

    public function removeService(int $index): void
    {
        unset($this->services[$index]);
        $this->services = array_values($this->services);
        if (empty($this->services)) {
            $this->services = [$this->emptyServiceRow()];
        }
    }

    public function syncGuestDetailRows(): void
    {
        $count = max(1, $this->totalGuests);
        while (count($this->guestDetails) < $count) {
            $this->guestDetails[] = [
                'full_name' => '',
                'national_id' => '',
                'mobile' => '',
                'relation' => '',
                'excluded_from_veteran_discount' => false,
                'manual_discount_percentage' => '',
                'manual_discount_reason' => '',
            ];
        }
        $this->guestDetails = array_slice($this->guestDetails, 0, $count);

        if ($this->bookerVerified) {
            $this->syncBookerToGuestDetails();
        }
    }

    public function nextStep(): void
    {
        if ($this->step === 3 && !$this->bookerVerified) {
            $this->addError('bookerNationalId', 'لطفاً ابتدا کد ملی را بررسی کنید.');
            return;
        }

        $this->validate($this->rulesForStep($this->step));

        if ($this->step === 1) {
            if (empty($this->roomLines)) {
                $this->addError('roomLines', 'حداقل یک اتاق باید انتخاب و تأیید شود.');
                return;
            }
            $this->syncGuestDetailRows();
        }

        if ($this->step === 2 && !$this->validateServiceVariants()) {
            return;
        }

        if ($this->step === 3) {
            $this->syncBookerToGuestDetails();
            if (!$this->validateNewBookerContacts()) {
                return;
            }
        }

        $this->step = min(5, $this->step + 1);
    }

    public function prevStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function goToStep(int $step): void
    {
        if ($step < $this->step) {
            $this->step = $step;
            if ($step === 1) {
                $this->dispatch('manual-booking-set-discount', pct: $this->discountPct);
            }
        }
    }

    public function submit(ManualBookingService $manualBooking): void
    {
        if (!$this->bookerVerified) {
            $this->addError('bookerNationalId', 'لطفاً ابتدا کد ملی رزرو‌کننده را بررسی کنید.');
            return;
        }

        $this->validate(array_merge(
            $this->rulesForStep(1),
            $this->rulesForStep(2),
            $this->rulesForStep(3),
            $this->rulesForStep(4),
        ));

        $this->syncBookerToGuestDetails();
        $this->syncGuestDetailRows();

        if (!$this->validateNewBookerContacts()) {
            return;
        }

        if (!$this->validateManualGuestDiscounts()) {
            return;
        }

        try {
            [$primaryType, $secondaryType] = $this->veteranPolicy()->splitVeteranTypes($this->resolvedVeteranTypes());

            $booking = $manualBooking->create($this->accommodation, [
                'room_lines'           => $this->normalizedRoomLinesForSubmit(),
                'check_in'             => $this->checkIn,
                'check_out'            => $this->checkOut,
                'guests'               => $this->totalGuests,
                'children_under_6'     => $this->totalChildrenUnder6,
                'extra_guests'         => $this->totalExtraGuests,
                'veteran_type'         => $primaryType,
                'secondary_veteran_type' => $secondaryType,
                'veteran_types'        => $this->resolvedVeteranTypes(),
                'booker_national_id'   => $this->bookerNationalId,
                'payment_method'       => $this->paymentMethod,
                'user_id'              => $this->userId,
                'guest_contact_name'   => $this->guestContactName,
                'guest_contact_mobile' => $this->guestContactMobile,
                'notes'                => $this->notes,
                'services'             => $this->filledServices(),
                'guest_details'        => $this->guestDetails,
            ], Auth::user());

            $this->createdBookingId = $booking->id;
            $this->createdBooking = $booking;
            $this->step = 5;
            session()->flash('status', 'رزرو دستی با موفقیت ثبت شد.');
            $this->dispatch('toast', type: 'success', message: 'رزرو دستی با موفقیت ثبت شد.');
        } catch (\Throwable $e) {
            $this->addError('submit', $e->getMessage());
        }
    }

    public function getPricingPreviewProperty(BookingPricingService $pricing): array
    {
        if (!$this->checkIn || !$this->checkOut || empty($this->roomLines)) {
            return [];
        }

        $roomLines = $this->resolvedRoomLinesForPricing();
        if (empty($roomLines)) {
            return [];
        }

        [$primaryType] = $this->veteranPolicy()->splitVeteranTypes($this->resolvedVeteranTypes());

        return $pricing->calculate([
            'check_in'        => $this->checkIn,
            'check_out'       => $this->checkOut,
            'guests'          => $this->totalGuests,
            'children_under_6'=> $this->totalChildrenUnder6,
            'extra_guests'    => $this->totalExtraGuests,
            'bill_full_rooms' => false,
            'veteran_type'    => $primaryType,
            'secondary_veteran_type' => $this->secondaryVeteranType ?: null,
            'veteran_types'   => $this->resolvedVeteranTypes(),
            'services'        => $this->filledServices(),
            'accommodation'   => $this->accommodation,
            'room_lines'      => $roomLines,
            'national_id'     => $this->primaryNationalId(),
            'user_id'         => $this->userId,
            'non_veteran_discount_guests' => $this->nonVeteranDiscountGuestCount(),
            'per_guest_slots' => $this->perGuestSlotsForPricing(),
        ]);
    }

    public function guestReceivesVeteranDiscount(int $index): bool
    {
        return !empty($this->resolvedVeteranTypes())
            && $this->discountPct > 0
            && empty($this->guestDetails[$index]['excluded_from_veteran_discount'] ?? false);
    }

    public function guestCanReceiveManualDiscount(int $index): bool
    {
        return !$this->guestReceivesVeteranDiscount($index);
    }

    /**
     * @return array<int, array{is_child:bool, veteran_eligible:bool, manual_discount_pct:int}>
     */
    private function perGuestSlotsForPricing(): array
    {
        $billingGuests = max(1, $this->totalGuests - $this->totalExtraGuests);

        return app(BookingPricingService::class)->buildPerGuestSlotsFromGuestDetails(
            $this->guestDetails,
            $billingGuests,
            $this->totalChildrenUnder6,
            $this->veteranType ?: null,
            $this->discountPct,
        );
    }

    private function clearManualDiscountsForVeteranEligibleGuests(): void
    {
        foreach ($this->guestDetails as $index => $guest) {
            if ($this->guestReceivesVeteranDiscount($index)) {
                $this->guestDetails[$index]['manual_discount_percentage'] = '';
                $this->guestDetails[$index]['manual_discount_reason'] = '';
            }
        }
    }

    private function validateManualGuestDiscounts(): bool
    {
        $valid = true;

        foreach ($this->guestDetails as $index => $guest) {
            if (!$this->guestCanReceiveManualDiscount($index)) {
                continue;
            }

            $pct = trim((string) ($guest['manual_discount_percentage'] ?? ''));
            $reason = trim((string) ($guest['manual_discount_reason'] ?? ''));

            if ($pct === '' || (int) $pct === 0) {
                continue;
            }

            $pctInt = (int) $pct;
            if ($pctInt < 1 || $pctInt > 100) {
                $this->addError(
                    "guestDetails.{$index}.manual_discount_percentage",
                    'درصد تخفیف باید بین ۱ تا ۱۰۰ باشد.'
                );
                $valid = false;
            }

            if ($reason === '') {
                $this->addError(
                    "guestDetails.{$index}.manual_discount_reason",
                    'ذکر دلیل تخفیف برای این مهمان الزامی است.'
                );
                $valid = false;
            }
        }

        return $valid;
    }

    public function getDiscountPctProperty(): int
    {
        return VeteranGroups::accommodationDiscountForTypes(
            $this->resolvedVeteranTypes(),
            $this->accommodation->id,
        );
    }

    public function getAccommodationUsageCheckProperty(): array
    {
        if (empty($this->resolvedVeteranTypes()) || !$this->checkIn || !$this->checkOut) {
            return [];
        }

        return $this->usageCheck();
    }

    public function getUsageSummaryProperty(): array
    {
        $types = $this->resolvedVeteranTypes();
        if (empty($types)) {
            return [];
        }

        [$primary, $secondary] = $this->veteranPolicy()->splitVeteranTypes($types);

        return $this->veteranPolicy()->usageSummary(
            $primary,
            $this->totalGuests,
            $this->primaryNationalId(),
            $this->userId,
            $this->checkIn ?: null,
            $secondary,
        );
    }

    private function applyExistingBooker(User $user): void
    {
        $this->userId = $user->id;
        $this->bookerIsExistingUser = true;
        $this->guestContactName = $user->name ?? '';
        $this->guestContactMobile = $user->mobile ?? '';
        $this->veteranType = $user->normalizedVeteranType() ?? '';
        $this->secondaryVeteranType = '';
        $this->syncSelectedVeteranTypesFromFields();
    }

    private function findGuestUserByNationalId(string $nationalId): ?User
    {
        return User::query()
            ->where('national_id', $nationalId)
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['super_admin', 'host']))
            ->first();
    }

    private function validateNewBookerContacts(bool $mobileOnly = false): bool
    {
        if ($this->bookerIsExistingUser) {
            return true;
        }

        $nationalId = preg_replace('/\D/', '', $this->bookerNationalId);
        $mobile = preg_replace('/\D/', '', $this->guestContactMobile);

        if (!$mobileOnly && strlen($nationalId) === 10) {
            if (User::where('national_id', $nationalId)->exists()) {
                $this->addError(
                    'bookerNationalId',
                    'این کد ملی قبلاً ثبت شده است. لطفاً دوباره «بررسی» کنید تا اطلاعات کاربر موجود بارگذاری شود.'
                );

                return false;
            }
        }

        if ($mobile !== '') {
            if (!preg_match('/^09[0-9]{9}$/', $mobile)) {
                $this->addError('guestContactMobile', 'شماره موبایل باید ۱۱ رقم و با ۰۹ شروع شود.');

                return false;
            }

            $mobileUser = User::where('mobile', $mobile)->first();

            if ($mobileUser) {
                if ($mobileUser->isAdmin() || $mobileUser->isHost()) {
                    $this->addError('guestContactMobile', 'این شماره موبایل متعلق به حساب کارکنان است و قابل استفاده نیست.');

                    return false;
                }

                $this->addError(
                    'guestContactMobile',
                    'این شماره موبایل قبلاً ثبت شده است'
                    . ($mobileUser->national_id ? " (کد ملی: {$mobileUser->national_id})" : '')
                    . '. برای رزرو، همان کد ملی را در مرحله قبل وارد و «بررسی» کنید.'
                );

                return false;
            }
        }

        return true;
    }

    private function resetBookerVerification(): void
    {
        $this->bookerVerified = false;
        $this->bookerIsExistingUser = false;
        $this->bookerVerifyMessage = '';
        $this->userId = null;
        $this->veteranType = '';
        $this->secondaryVeteranType = '';
        $this->selectedVeteranTypes = [];
        $this->guestContactName = '';
        $this->guestContactMobile = '';
    }

    private function syncBookerToGuestDetails(): void
    {
        if (empty($this->guestDetails)) {
            $this->syncGuestDetailRows();
        }

        $this->guestDetails[0]['national_id'] = $this->bookerNationalId;

        if ($this->guestContactName) {
            $this->guestDetails[0]['full_name'] = $this->guestContactName;
        }

        if ($this->guestContactMobile) {
            $this->guestDetails[0]['mobile'] = $this->guestContactMobile;
        }

        $this->guestDetails[0]['relation'] = 'رزرو‌کننده';
    }

    private function usageCheck(): array
    {
        $nights = 0;
        if ($this->checkIn && $this->checkOut) {
            $nights = (int) (new \DateTime($this->checkIn))->diff(new \DateTime($this->checkOut))->days;
        }

        return $this->veteranPolicy()->checkAccommodationUsageForTypes(
            $this->resolvedVeteranTypes(),
            $this->totalGuests,
            $nights,
            $this->primaryNationalId(),
            $this->userId,
        );
    }

    public function getTotalGuestsProperty(): int
    {
        if (!empty($this->roomLines)) {
            return max(1, collect($this->roomLines)->sum(function ($line) {
                return (int) $line['adults']
                    + (int) ($line['children_under_6'] ?? 0)
                    + (int) ($line['extra_guests'] ?? 0);
            }));
        }

        return max(1, $this->adults + $this->childrenUnder6 + $this->extraGuests);
    }

    public function getTotalChildrenUnder6Property(): int
    {
        if (!empty($this->roomLines)) {
            return collect($this->roomLines)->sum(fn ($line) => (int) ($line['children_under_6'] ?? 0));
        }

        return $this->childrenUnder6;
    }

    public function getTotalExtraGuestsProperty(): int
    {
        if (!empty($this->roomLines)) {
            return collect($this->roomLines)->sum(fn ($line) => (int) ($line['extra_guests'] ?? 0));
        }

        return $this->extraGuests;
    }

    public function nonVeteranDiscountGuestCount(): int
    {
        return min(
            $this->totalGuests,
            collect($this->guestDetails)
                ->filter(fn ($g) => !empty($g['excluded_from_veteran_discount']))
                ->count()
        );
    }

    private function primaryNationalId(): ?string
    {
        $id = preg_replace('/\D/', '', $this->bookerNationalId);
        if (strlen($id) === 10) {
            return $id;
        }

        foreach ($this->guestDetails as $guest) {
            $guestId = preg_replace('/\D/', '', $guest['national_id'] ?? '');
            if (strlen($guestId) === 10) {
                return $guestId;
            }
        }

        if ($this->userId) {
            return User::find($this->userId)?->national_id;
        }

        return null;
    }

    private function emptyServiceRow(): array
    {
        return [
            'service_catalog_id'         => '',
            'service_catalog_variant_id' => '',
            'name'                       => '',
            'unit_price'                 => '',
            'quantity'                   => 1,
            'discount_override'          => '',
            'is_custom'                  => false,
        ];
    }

    private function validateServiceVariants(): bool
    {
        $valid = true;

        foreach ($this->services as $i => $svc) {
            $catalogId = $svc['service_catalog_id'] ?? '';
            if ($catalogId === '' || $catalogId === 'custom' || $catalogId === '0') {
                continue;
            }

            $service = $this->veteranPolicy()->serviceById((int) $catalogId);
            if (!$service) {
                continue;
            }

            $activeVariants = $service->variants->where('is_active', true);
            if ($activeVariants->isEmpty()) {
                $this->addError("services.{$i}.service_catalog_id", 'برای این خدمت نوع و قیمت تعریف نشده. از تنظیمات ایثارگری انواع را اضافه کنید.');
                $valid = false;
                continue;
            }

            if (empty($svc['service_catalog_variant_id'])) {
                $this->addError("services.{$i}.service_catalog_variant_id", 'نوع این خدمت را انتخاب کنید.');
                $valid = false;
            }
        }

        return $valid;
    }

    private function filledServices(): array
    {
        return collect($this->services)
            ->filter(fn ($s) => !empty(trim($s['name'] ?? '')))
            ->map(function ($s) {
                $catalogId = $s['service_catalog_id'] ?? '';
                if ($catalogId === 'custom' || $catalogId === '') {
                    $catalogId = null;
                }

                return [
                    'service_catalog_id'         => $catalogId ? (int) $catalogId : null,
                    'service_catalog_variant_id' => !empty($s['service_catalog_variant_id'])
                        ? (int) $s['service_catalog_variant_id']
                        : null,
                    'name'               => trim($s['name']),
                    'unit_price'         => (int) ($s['unit_price'] ?? 0),
                    'quantity'           => (int) ($s['quantity'] ?? 1),
                    'discount_override'  => ($s['discount_override'] ?? '') !== ''
                        ? (int) $s['discount_override']
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    private function resolvedRoomLinesForPricing(): array
    {
        return collect($this->roomLines)->map(function ($line) {
            $roomType = null;
            $roomRate = null;

            if (!empty($line['room_rate_id'])) {
                $roomRate = RoomRate::with('roomType')->find($line['room_rate_id']);
                $roomType = $roomRate?->roomType;
            } elseif (!empty($line['room_type_id'])) {
                $roomType = $this->accommodation->roomTypes->firstWhere('id', (int) $line['room_type_id']);
            }

            $childrenUnder6 = (int) ($line['children_under_6'] ?? 0);
            $adults = (int) ($line['adults'] ?? 1);

            return [
                'room_type'        => $roomType,
                'room_rate'        => $roomRate,
                'guests'           => max(1, $adults + $childrenUnder6),
                'children_under_6' => $childrenUnder6,
                'extra_guests'     => (int) ($line['extra_guests'] ?? 0),
                'bill_full_rooms'  => (bool) ($line['bill_full_rooms'] ?? false),
            ];
        })->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function normalizedRoomLinesForSubmit(): array
    {
        return collect($this->roomLines)->map(function ($line) {
            $childrenUnder6 = (int) ($line['children_under_6'] ?? 0);
            $adults = (int) ($line['adults'] ?? 1);

            return [
                'room_type_id'     => (int) $line['room_type_id'],
                'room_rate_id'     => !empty($line['room_rate_id']) ? (int) $line['room_rate_id'] : null,
                'room_id'          => !empty($line['room_id']) ? (int) $line['room_id'] : null,
                'adults'           => $adults,
                'children_under_6' => $childrenUnder6,
                'guests'           => max(1, $adults + $childrenUnder6),
                'extra_guests'     => (int) ($line['extra_guests'] ?? 0),
                'bill_full_rooms'  => (bool) ($line['bill_full_rooms'] ?? false),
            ];
        })->values()->all();
    }

    private function resolvedRoomType()
    {
        if ($this->roomRateId) {
            return RoomRate::with('roomType')->find($this->roomRateId)?->roomType;
        }

        return $this->accommodation->roomTypes->firstWhere('id', $this->roomTypeId);
    }

    /**
     * @param  mixed  $rooms
     * @return array<int, array{room_id:int, room_name:?string}>
     */
    private function normalizePhysicalRoomsPayload($rooms): array
    {
        if (!is_array($rooms)) {
            return [];
        }

        return collect($rooms)
            ->map(function ($room) {
                if (!is_array($room)) {
                    return null;
                }

                $roomId = (int) ($room['room_id'] ?? $room['roomId'] ?? 0);
                if ($roomId <= 0) {
                    return null;
                }

                $name = $room['room_name'] ?? $room['roomName'] ?? null;

                return [
                    'room_id'   => $roomId,
                    'room_name' => $name !== null && $name !== '' ? (string) $name : null,
                ];
            })
            ->filter()
            ->unique('room_id')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{adults:int, children_under_6:int, extra_guests:int}>
     */
    private function splitGuestsAcrossRooms(
        int $adults,
        int $childrenUnder6,
        int $extraGuests,
        int $roomCount,
        int $capacity,
    ): array {
        $roomCount = max(1, $roomCount);
        $capacity = max(1, $capacity);
        $childAllocatesBed = $this->accommodation->childrenUnder6AllocateBed();

        $bedGuestTotal = max(0, app(BookingPricingService::class)->guestsForBedAllocation(
            $adults + $childrenUnder6,
            $childrenUnder6,
            $this->accommodation,
        ) - max(0, $extraGuests));

        $remainingBedSlots = $bedGuestTotal;
        $remainingAdults = max(0, $adults);
        $remainingChildren = max(0, $childrenUnder6);
        $lines = [];

        for ($i = 0; $i < $roomCount; $i++) {
            $isLast = ($i === $roomCount - 1);
            $roomBedSlots = $isLast
                ? $remainingBedSlots
                : min($capacity, $remainingBedSlots);

            $roomAdults = min($roomBedSlots, $remainingAdults);
            $slotsLeft = $roomBedSlots - $roomAdults;
            $roomChildren = 0;
            if ($childAllocatesBed && $slotsLeft > 0 && $remainingChildren > 0) {
                $roomChildren = min($slotsLeft, $remainingChildren);
            }

            $remainingAdults -= $roomAdults;
            $remainingChildren -= $roomChildren;
            $remainingBedSlots -= $roomBedSlots;

            $lines[] = [
                'adults'           => max(0, $roomAdults),
                'children_under_6' => max(0, $roomChildren),
                'extra_guests'     => ($isLast && $extraGuests > 0) ? $extraGuests : 0,
            ];
        }

        return $lines;
    }

    private function resolvedRoomRate()
    {
        return $this->roomRateId ? RoomRate::find($this->roomRateId) : null;
    }

    private function rulesForStep(int $step): array
    {
        $veteranKeys = array_keys(VeteranGroups::options($this->accommodation->id));

        return match ($step) {
            1 => [
                'checkIn'  => ['required', 'date', 'after_or_equal:today'],
                'checkOut' => ['required', 'date', 'after:checkIn'],
                'roomLines' => ['array'],
            ],
            2 => [
                'services.*.name'       => ['nullable', 'string', 'max:200'],
                'services.*.unit_price' => ['nullable', 'integer', 'min:0'],
                'services.*.quantity'   => ['nullable', 'integer', 'min:1', 'max:99'],
                'services.*.discount_override' => ['nullable', 'integer', 'min:0', 'max:100'],
            ],
            3 => [
                'bookerNationalId' => ['required', 'digits:10'],
                'veteranType'      => ['nullable', 'string', Rule::in($veteranKeys)],
                'secondaryVeteranType' => ['nullable', 'string', Rule::in($veteranKeys)],
                'selectedVeteranTypes' => ['array', 'max:2'],
                'selectedVeteranTypes.*' => ['string', Rule::in($veteranKeys)],
                'guestContactName' => [Rule::requiredIf(!$this->bookerIsExistingUser), 'nullable', 'string', 'max:120'],
                'guestContactMobile' => [Rule::requiredIf(!$this->bookerIsExistingUser), 'nullable', 'regex:/^09[0-9]{9}$/'],
            ],
            4 => [
                'paymentMethod'      => ['required', 'in:cash,card_terminal'],
                'guestContactName'   => ['required', 'string', 'max:120'],
                'guestContactMobile' => ['required', 'string', 'max:15'],
                'guestDetails.*.full_name' => ['nullable', 'string', 'max:120'],
                'guestDetails.*.excluded_from_veteran_discount' => ['nullable', 'boolean'],
                'guestDetails.*.manual_discount_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
                'guestDetails.*.manual_discount_reason' => ['nullable', 'string', 'max:500'],
            ],
            default => [],
        };
    }

    public function render()
    {
        $policy = $this->veteranPolicy();

        return view('livewire.manual-booking-form', [
            'roomTypes'        => $this->accommodation->roomTypes,
            'veteranGroups'    => VeteranGroups::options($this->accommodation->id),
            'serviceCatalog'   => $policy->activeServices(),
            'pricing'          => $this->pricingPreview,
            'usageSummary'     => $this->usageSummary,
            'accommodationUsageCheck' => $this->accommodationUsageCheck,
            'pdfRoute'         => $this->createdBookingId
                ? route($this->panel . '.bookings.pdf', $this->createdBookingId)
                : null,
            'bookingShowRoute' => $this->createdBookingId
                ? route($this->panel . '.bookings.show', $this->createdBookingId)
                : null,
        ]);
    }
}
