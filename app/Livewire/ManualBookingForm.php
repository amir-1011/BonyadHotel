<?php

namespace App\Livewire;

use App\Livewire\Concerns\AssertsHostPermissions;
use App\Livewire\Concerns\ManagesForeignGuestLocation;
use App\Livewire\Concerns\ManagesProgramBeneficiaries;
use App\Models\Country;
use App\Models\ResidenceCity;
use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\BookingGuestDetail;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\User;
use App\Services\BookingPricingService;
use App\Services\ManualBookingService;
use App\Services\NationalIdVerificationService;
use App\Services\ProgramDocumentService;
use App\Services\RoomAvailabilityService;
use App\Services\VeteranPolicyService;
use App\Support\VeteranGroups;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class ManualBookingForm extends Component
{
    use ManagesProgramBeneficiaries;
    use ManagesForeignGuestLocation;
    use WithFileUploads;
    use AssertsHostPermissions;
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

    // Step 2 — booker identity & veteran discount
    public bool $bookerIsForeignGuest = false;
    public string $bookerNationalId = '';
    public string $bookerPassportNumber = '';
    public int $foreignCountryId = 0;
    public int $foreignResidenceCityId = 0;
    public bool $bookerVerified = false;
    public bool $bookerIsExistingUser = false;
    public string $bookerVerifyMessage = '';
    public string $veteranType = '';
    public string $secondaryVeteranType = '';

    /** @var array<int, string> */
    public array $selectedVeteranTypes = [];

    // Step 4 — payment & contacts (legacy comment removed; payment is step 3)
    public string $paymentMethod = 'cash';
    public ?int $userId = null;
    public string $guestContactName = '';
    public string $guestContactMobile = '';
    public string $notes = '';

    /** @var array<int, array{full_name:string, national_id:string, mobile:string, relation:string, excluded_from_veteran_discount:bool, manual_discount_percentage:string, manual_discount_reason:string, services:array<int, array{service_catalog_id:string, service_catalog_variant_id:string, name:string, unit_price:int|string, quantity:int|string, discount_override:string, is_custom:bool, excluded_from_veteran_quota:bool, manual_discount_percentage:string, manual_discount_reason:string}>}> */
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
        app(\App\Services\CancellationPolicyProvisioner::class)->seedForAccommodation($accommodation);
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

    public function updatedBookerIsForeignGuest(): void
    {
        $this->resetBookerVerification();
        $this->bookerNationalId = '';
        $this->bookerPassportNumber = '';
        $this->foreignCountryId = 0;
        $this->foreignResidenceCityId = 0;
        $this->showAddCountry = false;
        $this->showAddResidenceCity = false;
        $this->newCountryName = '';
        $this->newResidenceCityName = '';
    }

    public function updatedBookerPassportNumber(): void
    {
        if ($this->bookerIsForeignGuest) {
            $this->resetBookerVerification();
        }
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

    /**
     * Veteran types used for pricing UI — only after main guest national ID is verified.
     *
     * @return array<int, string>
     */
    private function veteranTypesForPricing(): array
    {
        if (!$this->bookerVerified || $this->bookerIsForeignGuest) {
            return [];
        }

        return $this->resolvedVeteranTypes();
    }

    public function updatedGuestDetails($value, $key): void
    {
        if (str_contains($key, '.services.')) {
            $this->handleGuestServiceFieldUpdate($key);
            return;
        }

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

        $types = $this->veteranTypesForPricing();
        $primaryType = $types[0] ?? null;

        foreach ($this->guestDetails as $guestIndex => $guest) {
            foreach ($guest['services'] ?? [] as $serviceIndex => $service) {
                $catalogId = $service['service_catalog_id'] ?? '';
                if ($catalogId === '' || $catalogId === 'custom' || $catalogId === '0') {
                    continue;
                }

                if (!empty($service['excluded_from_veteran_quota'])) {
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
                    $this->guestDetails[$guestIndex]['services'][$serviceIndex]['discount_override'] = (string) $matrixPct;
                } else {
                    $this->guestDetails[$guestIndex]['services'][$serviceIndex]['discount_override'] = '';
                }
            }
        }
    }

    public function verifyBooker(): void
    {
        if ($this->bookerIsForeignGuest) {
            $this->verifyForeignBooker();

            return;
        }

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
        $this->refreshServiceDiscountOverrides();
        $this->dispatch('manual-booking-set-discount', pct: $this->discountPct);
    }

    private function verifyForeignBooker(): void
    {
        $this->resetErrorBag(['bookerPassportNumber', 'foreignCountryId', 'foreignResidenceCityId', 'guestContactName', 'guestContactMobile']);

        $this->validate([
            'bookerPassportNumber' => ['required', 'string', 'min:5', 'max:32', 'regex:/^[A-Za-z0-9]+$/'],
            'foreignCountryId' => ['required', 'integer', 'exists:countries,id'],
            'foreignResidenceCityId' => ['required', 'integer', ...$this->residenceCityIdRules()],
            'guestContactName' => ['required', 'string', 'max:120'],
            'guestContactMobile' => ['required', 'regex:/^09[0-9]{9}$/'],
        ], [], [
            'bookerPassportNumber' => 'شماره پاسپورت',
            'foreignCountryId' => 'کشور اقامت',
            'foreignResidenceCityId' => 'شهر اقامت',
            'guestContactName' => 'نام و نام خانوادگی',
            'guestContactMobile' => 'شماره موبایل',
        ]);

        $passport = strtoupper(trim($this->bookerPassportNumber));
        $this->bookerPassportNumber = $passport;

        $existing = $this->findGuestUserByPassport($passport);

        if ($existing) {
            $this->applyExistingForeignBooker($existing);
            $this->bookerVerifyMessage = 'مهمان خارجی در سیستم یافت شد: ' . ($existing->name ?: $existing->mobile);
        } else {
            $anyUser = User::where('passport_number', $passport)->first();
            if ($anyUser) {
                if ($anyUser->isAdmin() || $anyUser->isHost()) {
                    $this->addError('bookerPassportNumber', 'این شماره پاسپورت متعلق به حساب کارکنان است و قابل استفاده برای رزرو مهمان نیست.');
                } else {
                    $this->addError('bookerPassportNumber', 'این شماره پاسپورت قبلاً ثبت شده است. لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.');
                }
                $this->bookerVerified = false;

                return;
            }

            $this->userId = null;
            $this->bookerIsExistingUser = false;
            $this->veteranType = '';
            $this->secondaryVeteranType = '';
            $this->selectedVeteranTypes = [];

            if (!$this->validateNewBookerContacts()) {
                $this->bookerVerified = false;

                return;
            }

            $this->bookerVerifyMessage = 'مهمان خارجی جدید — بدون تخفیف ایثارگری (کاربر عادی)';
        }

        $this->bookerVerified = true;
        $this->syncBookerToGuestDetails();
        $this->refreshServiceDiscountOverrides();
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
        if ($this->bookerIsForeignGuest) {
            $this->bookerPassportNumber = '';
            $this->foreignCountryId = 0;
            $this->foreignResidenceCityId = 0;
        } else {
            $this->bookerNationalId = '';
        }

        $this->resetBookerVerification();
    }

    private function handleGuestServiceFieldUpdate(string $key): void
    {
        if (preg_match('/^(\d+)\.services\.(\d+)\.service_catalog_variant_id$/', $key, $matches)) {
            $this->applyVariantToGuestServiceRow((int) $matches[1], (int) $matches[2]);
            return;
        }

        if (!preg_match('/^(\d+)\.services\.(\d+)\.service_catalog_id$/', $key, $matches)) {
            if (preg_match('/^(\d+)\.services\.(\d+)\.excluded_from_veteran_quota$/', $key, $quotaMatches)) {
                $guestIndex = (int) $quotaMatches[1];
                $serviceIndex = (int) $quotaMatches[2];
                if (empty($this->guestDetails[$guestIndex]['services'][$serviceIndex]['excluded_from_veteran_quota'])) {
                    $catalogId = (int) ($this->guestDetails[$guestIndex]['services'][$serviceIndex]['service_catalog_id'] ?? 0);
                    if ($catalogId > 0) {
                        $this->applyGuestServiceDiscountOverride($guestIndex, $serviceIndex, $catalogId);
                    }
                } else {
                    $this->guestDetails[$guestIndex]['services'][$serviceIndex]['discount_override'] = '';
                }
            }
            return;
        }

        $guestIndex = (int) $matches[1];
        $serviceIndex = (int) $matches[2];
        $catalogId = $this->guestDetails[$guestIndex]['services'][$serviceIndex]['service_catalog_id'] ?? '';

        $this->resetErrorBag("guestDetails.{$guestIndex}.services.{$serviceIndex}.service_catalog_id");
        $this->resetErrorBag("guestDetails.{$guestIndex}.services.{$serviceIndex}.service_catalog_variant_id");

        if ($catalogId === 'custom') {
            $this->guestDetails[$guestIndex]['services'][$serviceIndex]['is_custom'] = true;
            $this->guestDetails[$guestIndex]['services'][$serviceIndex]['service_catalog_variant_id'] = '';
            $this->guestDetails[$guestIndex]['services'][$serviceIndex]['name'] = '';
            $this->guestDetails[$guestIndex]['services'][$serviceIndex]['unit_price'] = '';
            $this->guestDetails[$guestIndex]['services'][$serviceIndex]['discount_override'] = '';
            return;
        }

        if ($catalogId === '' || $catalogId === '0') {
            $this->guestDetails[$guestIndex]['services'][$serviceIndex]['is_custom'] = false;
            $this->guestDetails[$guestIndex]['services'][$serviceIndex]['service_catalog_variant_id'] = '';
            return;
        }

        $service = $this->veteranPolicy()->serviceById((int) $catalogId);
        if (!$service) {
            return;
        }

        $this->guestDetails[$guestIndex]['services'][$serviceIndex]['is_custom'] = false;
        $this->guestDetails[$guestIndex]['services'][$serviceIndex]['service_catalog_variant_id'] = '';
        $this->guestDetails[$guestIndex]['services'][$serviceIndex]['discount_override'] = '';
        $this->guestDetails[$guestIndex]['services'][$serviceIndex]['name'] = $service->name;
        $this->guestDetails[$guestIndex]['services'][$serviceIndex]['unit_price'] = '';

        $activeVariants = $service->variants->where('is_active', true);
        if ($activeVariants->isNotEmpty()) {
            $this->applyGuestServiceDiscountOverride($guestIndex, $serviceIndex, (int) $catalogId);
            return;
        }

        $this->addError(
            "guestDetails.{$guestIndex}.services.{$serviceIndex}.service_catalog_id",
            'برای این خدمت نوع و قیمت تعریف نشده. از تنظیمات ایثارگری انواع را اضافه کنید.',
        );
    }

    private function applyVariantToGuestServiceRow(int $guestIndex, int $serviceIndex): void
    {
        $catalogId = (int) ($this->guestDetails[$guestIndex]['services'][$serviceIndex]['service_catalog_id'] ?? 0);
        $variantId = (int) ($this->guestDetails[$guestIndex]['services'][$serviceIndex]['service_catalog_variant_id'] ?? 0);

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

        $this->guestDetails[$guestIndex]['services'][$serviceIndex]['name'] = $service->name . ' — ' . $variant->name;
        $this->guestDetails[$guestIndex]['services'][$serviceIndex]['unit_price'] = $variant->price;
        $this->resetErrorBag("guestDetails.{$guestIndex}.services.{$serviceIndex}.service_catalog_id");
        $this->resetErrorBag("guestDetails.{$guestIndex}.services.{$serviceIndex}.service_catalog_variant_id");
        $this->applyGuestServiceDiscountOverride($guestIndex, $serviceIndex, $catalogId);
    }

    private function applyGuestServiceDiscountOverride(int $guestIndex, int $serviceIndex, int $catalogId): void
    {
        if (!empty($this->guestDetails[$guestIndex]['services'][$serviceIndex]['excluded_from_veteran_quota'])) {
            return;
        }

        $types = $this->veteranTypesForPricing();
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
            $this->guestDetails[$guestIndex]['services'][$serviceIndex]['discount_override'] = (string) $matrixPct;
        } else {
            $this->guestDetails[$guestIndex]['services'][$serviceIndex]['discount_override'] = '';
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
        $this->scrollToNavigationAfterRoomCommit();
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

    public function addGuestService(int $guestIndex): void
    {
        if (!isset($this->guestDetails[$guestIndex])) {
            return;
        }

        $this->guestDetails[$guestIndex]['services'][] = $this->emptyGuestServiceRow();
    }

    public function removeGuestService(int $guestIndex, int $serviceIndex): void
    {
        if (!isset($this->guestDetails[$guestIndex]['services'][$serviceIndex])) {
            return;
        }

        unset($this->guestDetails[$guestIndex]['services'][$serviceIndex]);
        $this->guestDetails[$guestIndex]['services'] = array_values($this->guestDetails[$guestIndex]['services']);

        if (empty($this->guestDetails[$guestIndex]['services'])) {
            $this->guestDetails[$guestIndex]['services'] = [];
        }
    }

    public function syncGuestDetailRows(): void
    {
        $count = max(1, $this->totalGuests);
        while (count($this->guestDetails) < $count) {
            $this->guestDetails[] = $this->emptyGuestDetailRow();
        }
        $this->guestDetails = array_slice($this->guestDetails, 0, $count);

        foreach ($this->guestDetails as $index => $guest) {
            if (!isset($this->guestDetails[$index]['services']) || !is_array($this->guestDetails[$index]['services'])) {
                $this->guestDetails[$index]['services'] = [];
            }
        }

        if ($this->bookerVerified) {
            $this->syncBookerToGuestDetails();
        }

        $this->assignGuestsToRoomLines();
    }

    /**
     * Map each billing guest slot to the room line they occupy (same order as splitGuestsAcrossRooms).
     */
    private function assignGuestsToRoomLines(): void
    {
        if (empty($this->roomLines)) {
            return;
        }

        $guestIndex = 0;

        foreach ($this->roomLines as $lineIndex => $line) {
            $lineGuests = max(1, (int) ($line['adults'] ?? 1)
                + (int) ($line['children_under_6'] ?? 0)
                + (int) ($line['extra_guests'] ?? 0));

            $roomType = $this->accommodation->roomTypes->firstWhere('id', (int) ($line['room_type_id'] ?? 0));
            $roomName = $line['room_name'] ?? null;

            if (!$roomName && !empty($line['room_id'])) {
                $roomName = $roomType?->rooms?->firstWhere('id', (int) $line['room_id'])?->name;
            }

            if (!$roomName) {
                $typeName = $roomType?->name ?? 'اتاق';
                $roomName = $typeName . ' — ردیف ' . ($lineIndex + 1);
            }

            for ($slot = 0; $slot < $lineGuests; $slot++) {
                if (!isset($this->guestDetails[$guestIndex])) {
                    break 2;
                }

                $this->guestDetails[$guestIndex]['room_line_index'] = $lineIndex;
                $this->guestDetails[$guestIndex]['room_id'] = !empty($line['room_id']) ? (int) $line['room_id'] : null;
                $this->guestDetails[$guestIndex]['room_name'] = $roomName;
                $guestIndex++;
            }
        }
    }

    public function guestRoomLabel(int $index): ?string
    {
        $guest = $this->guestDetails[$index] ?? [];

        return trim((string) ($guest['room_name'] ?? '')) ?: null;
    }

    public function nextStep(): void
    {
        if ($this->step === 2 && !$this->bookerVerified) {
            $this->addError(
                $this->bookerIsForeignGuest ? 'bookerPassportNumber' : 'bookerNationalId',
                $this->bookerIsForeignGuest
                    ? 'لطفاً ابتدا اطلاعات مهمان خارجی را بررسی کنید.'
                    : 'لطفاً ابتدا کد ملی را بررسی کنید.',
            );

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

        if ($this->step === 2) {
            $this->syncBookerToGuestDetails();
            if (!$this->validateNewBookerContacts()) {
                return;
            }
        }

        $this->step = min(5, $this->step + 1);
        $this->scrollToTopAfterStepChange();
    }

    public function prevStep(): void
    {
        $this->step = max(1, $this->step - 1);
        $this->scrollToTopAfterStepChange();
    }

    public function goToStep(int $step): void
    {
        if ($step < $this->step) {
            $this->step = $step;
            if ($step === 1) {
                $this->dispatch('manual-booking-set-discount', pct: $this->discountPct);
            }
            $this->scrollToTopAfterStepChange();
        }
    }

    private function scrollToTopAfterStepChange(): void
    {
        $this->js(<<<'JS'
            (() => {
                const scroll = () => {
                    const el = document.getElementById('manual-booking-form');
                    const top = el
                        ? el.getBoundingClientRect().top + window.scrollY - 24
                        : 0;
                    window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
                };
                scroll();
                setTimeout(scroll, 120);
            })()
        JS);
    }

    private function scrollToNavigationAfterRoomCommit(): void
    {
        $this->js(<<<'JS'
            (() => {
                const scroll = () => {
                    const nav = document.getElementById('manual-booking-nav');
                    if (!nav) return;
                    const rect = nav.getBoundingClientRect();
                    const top = rect.bottom + window.scrollY - window.innerHeight + 48;
                    window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
                };
                scroll();
                setTimeout(scroll, 120);
                setTimeout(scroll, 450);
            })()
        JS);
    }

    public function submit(ManualBookingService $manualBooking): void
    {
        if ($this->panel === 'host') {
            $this->assertHostCan('accommodations.manual-booking', 'write');
        }

        if (!$this->bookerVerified) {
            $this->addError(
                $this->bookerIsForeignGuest ? 'bookerPassportNumber' : 'bookerNationalId',
                $this->bookerIsForeignGuest
                    ? 'لطفاً ابتدا اطلاعات مهمان خارجی را بررسی کنید.'
                    : 'لطفاً ابتدا کد ملی مهمان اصلی را بررسی کنید.',
            );

            return;
        }

        $this->validate(array_merge(
            $this->rulesForStep(1),
            $this->rulesForStep(2),
            $this->rulesForStep(3),
        ));

        $this->validate([
            'beneficiaryRows.*.documents.*' => ProgramDocumentService::fileRules(),
        ]);

        $this->syncBookerToGuestDetails();
        $this->syncGuestDetailRows();

        if (!$this->validateNewBookerContacts()) {
            return;
        }

        if (!$this->validateServiceVariants()) {
            return;
        }

        if (!$this->validateManualGuestDiscounts()) {
            return;
        }

        if (!$this->validateManualServiceDiscounts()) {
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
                'booker_is_foreign_guest' => $this->bookerIsForeignGuest,
                'booker_passport_number' => $this->bookerPassportNumber,
                'foreign_country_id'   => $this->foreignCountryId ?: null,
                'foreign_residence_city_id' => $this->foreignResidenceCityId ?: null,
                'payment_method'       => $this->paymentMethod,
                'user_id'              => $this->userId,
                'guest_contact_name'   => $this->guestContactName,
                'guest_contact_mobile' => $this->guestContactMobile,
                'notes'                => $this->notes,
                'services'             => $this->filledServices(),
                'guest_details'        => $this->guestDetails,
                'beneficiary_costs'    => $this->filledBeneficiaryCosts(),
            ], Auth::user());

            $this->createdBookingId = $booking->id;
            $this->createdBooking = $booking->load([
                'beneficiaryCosts.beneficiary.user',
                'user', 'accommodation.city', 'roomType', 'roomRate',
                'services.serviceCatalog', 'guestDetails.bookingRoom.room',
                'guestDetails.country', 'guestDetails.residenceCity',
                'bookingRooms.roomType', 'bookingRooms.room',
            ]);
            $this->step = 5;
            $this->scrollToTopAfterStepChange();
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

        $pricingVeteranTypes = $this->veteranTypesForPricing();
        [$primaryType] = $this->veteranPolicy()->splitVeteranTypes($pricingVeteranTypes);

        return $pricing->calculate([
            'check_in'        => $this->checkIn,
            'check_out'       => $this->checkOut,
            'guests'          => $this->totalGuests,
            'children_under_6'=> $this->totalChildrenUnder6,
            'extra_guests'    => $this->totalExtraGuests,
            'bill_full_rooms' => false,
            'veteran_type'    => $primaryType,
            'secondary_veteran_type' => $this->bookerVerified ? ($this->secondaryVeteranType ?: null) : null,
            'veteran_types'   => $pricingVeteranTypes,
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
        return $this->bookerVerified
            && !empty($this->veteranTypesForPricing())
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
        $pricing = app(BookingPricingService::class);
        $billingGuests = !empty($this->roomLines)
            ? $pricing->totalBillingGuestsForRoomLines($this->resolvedRoomLinesForPricing(), $this->accommodation)
            : max(1, $this->totalGuests - $this->totalExtraGuests);

        return $pricing->buildPerGuestSlotsFromGuestDetails(
            $this->guestDetails,
            $billingGuests,
            $this->totalChildrenUnder6,
            $this->bookerVerified ? ($this->veteranType ?: null) : null,
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
        if (!$this->bookerVerified) {
            return 0;
        }

        return VeteranGroups::accommodationDiscountForTypes(
            $this->veteranTypesForPricing(),
            $this->accommodation->id,
        );
    }

    public function getAccommodationUsageCheckProperty(): array
    {
        if (!$this->bookerVerified || empty($this->veteranTypesForPricing()) || !$this->checkIn || !$this->checkOut) {
            return [];
        }

        return $this->usageCheck();
    }

    public function getUsageSummaryProperty(): array
    {
        $types = $this->veteranTypesForPricing();
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
        $this->secondaryVeteranType = $user->normalizedSecondaryVeteranType() ?? '';
        $this->syncSelectedVeteranTypesFromFields();
    }

    private function applyExistingForeignBooker(User $user): void
    {
        $this->userId = $user->id;
        $this->bookerIsExistingUser = true;
        $this->guestContactName = $user->name ?? '';
        $this->guestContactMobile = $user->mobile ?? '';
        $this->bookerPassportNumber = $user->passport_number ?? '';
        $this->foreignCountryId = (int) ($user->country_id ?? 0);
        $this->foreignResidenceCityId = (int) ($user->residence_city_id ?? 0);
        $this->veteranType = '';
        $this->secondaryVeteranType = '';
        $this->selectedVeteranTypes = [];
    }

    private function findGuestUserByNationalId(string $nationalId): ?User
    {
        return User::query()
            ->where('national_id', $nationalId)
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['super_admin', 'host']))
            ->first();
    }

    private function findGuestUserByPassport(string $passport): ?User
    {
        return User::query()
            ->where('passport_number', $passport)
            ->where('is_foreign_guest', true)
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

        if ($this->bookerIsForeignGuest) {
            if (!$mobileOnly && $this->bookerPassportNumber !== '') {
                $passport = strtoupper(trim($this->bookerPassportNumber));
                if (User::where('passport_number', $passport)->exists()) {
                    $this->addError(
                        'bookerPassportNumber',
                        'این شماره پاسپورت قبلاً ثبت شده است. لطفاً دوباره «بررسی» کنید تا اطلاعات کاربر موجود بارگذاری شود.'
                    );

                    return false;
                }
            }
        } elseif (!$mobileOnly && strlen($nationalId) === 10) {
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
                    . ($mobileUser->passport_number ? " (پاسپورت: {$mobileUser->passport_number})" : '')
                    . ($this->bookerIsForeignGuest
                        ? '. برای رزرو، همان شماره پاسپورت را در مرحله قبل وارد و «بررسی» کنید.'
                        : '. برای رزرو، همان کد ملی را در مرحله قبل وارد و «بررسی» کنید.')
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
        $this->dispatch('manual-booking-set-discount', pct: 0);
    }

    private function syncBookerToGuestDetails(): void
    {
        if (empty($this->guestDetails)) {
            $this->syncGuestDetailRows();
        }

        $this->guestDetails[0]['national_id'] = $this->bookerIsForeignGuest ? '' : $this->bookerNationalId;
        $this->guestDetails[0]['is_foreign_guest'] = $this->bookerIsForeignGuest;
        $this->guestDetails[0]['passport_number'] = $this->bookerIsForeignGuest ? $this->bookerPassportNumber : '';
        $this->guestDetails[0]['country_id'] = $this->bookerIsForeignGuest ? ($this->foreignCountryId ?: null) : null;
        $this->guestDetails[0]['residence_city_id'] = $this->bookerIsForeignGuest ? ($this->foreignResidenceCityId ?: null) : null;

        if ($this->guestContactName) {
            $this->guestDetails[0]['full_name'] = $this->guestContactName;
        }

        if ($this->guestContactMobile) {
            $this->guestDetails[0]['mobile'] = $this->guestContactMobile;
        }

        $this->guestDetails[0]['relation'] = BookingGuestDetail::RELATION_MAIN_GUEST;
    }

    private function usageCheck(): array
    {
        $nights = 0;
        if ($this->checkIn && $this->checkOut) {
            $nights = (int) (new \DateTime($this->checkIn))->diff(new \DateTime($this->checkOut))->days;
        }

        return $this->veteranPolicy()->checkAccommodationUsageForTypes(
            $this->veteranTypesForPricing(),
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

    private function emptyGuestDetailRow(): array
    {
        return [
            'full_name' => '',
            'national_id' => '',
            'is_foreign_guest' => false,
            'passport_number' => '',
            'country_id' => null,
            'residence_city_id' => null,
            'mobile' => '',
            'relation' => '',
            'room_line_index' => null,
            'room_id' => null,
            'room_name' => null,
            'excluded_from_veteran_discount' => false,
            'manual_discount_percentage' => '',
            'manual_discount_reason' => '',
            'services' => [],
        ];
    }

    private function emptyGuestServiceRow(): array
    {
        return [
            'service_catalog_id'         => '',
            'service_catalog_variant_id' => '',
            'name'                       => '',
            'unit_price'                 => '',
            'quantity'                   => 1,
            'discount_override'          => '',
            'is_custom'                  => false,
            'excluded_from_veteran_quota' => false,
            'manual_discount_percentage' => '',
            'manual_discount_reason'   => '',
        ];
    }

    private function validateServiceVariants(): bool
    {
        $valid = true;

        foreach ($this->guestDetails as $guestIndex => $guest) {
            foreach ($guest['services'] ?? [] as $serviceIndex => $svc) {
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
                    $this->addError("guestDetails.{$guestIndex}.services.{$serviceIndex}.service_catalog_id", 'برای این خدمت نوع و قیمت تعریف نشده. از تنظیمات ایثارگری انواع را اضافه کنید.');
                    $valid = false;
                    continue;
                }

                if (empty($svc['service_catalog_variant_id'])) {
                    $this->addError("guestDetails.{$guestIndex}.services.{$serviceIndex}.service_catalog_variant_id", 'نوع این خدمت را انتخاب کنید.');
                    $valid = false;
                }
            }
        }

        return $valid;
    }

    private function validateManualServiceDiscounts(): bool
    {
        $valid = true;

        foreach ($this->guestDetails as $guestIndex => $guest) {
            foreach ($guest['services'] ?? [] as $serviceIndex => $service) {
                if (empty($service['excluded_from_veteran_quota'])) {
                    continue;
                }

                if (empty(trim($service['name'] ?? ''))) {
                    continue;
                }

                $pct = trim((string) ($service['manual_discount_percentage'] ?? ''));
                $reason = trim((string) ($service['manual_discount_reason'] ?? ''));

                if ($pct === '' || (int) $pct === 0) {
                    continue;
                }

                $pctInt = (int) $pct;
                if ($pctInt < 1 || $pctInt > 100) {
                    $this->addError(
                        "guestDetails.{$guestIndex}.services.{$serviceIndex}.manual_discount_percentage",
                        'درصد تخفیف باید بین ۱ تا ۱۰۰ باشد.'
                    );
                    $valid = false;
                }

                if ($reason === '') {
                    $this->addError(
                        "guestDetails.{$guestIndex}.services.{$serviceIndex}.manual_discount_reason",
                        'ذکر دلیل تخفیف برای این خدمت الزامی است.'
                    );
                    $valid = false;
                }
            }
        }

        return $valid;
    }

    private function filledServices(): array
    {
        $rows = [];

        foreach ($this->guestDetails as $guestIndex => $guest) {
            foreach ($guest['services'] ?? [] as $service) {
                if (empty(trim($service['name'] ?? ''))) {
                    continue;
                }

                $catalogId = $service['service_catalog_id'] ?? '';
                if ($catalogId === 'custom' || $catalogId === '') {
                    $catalogId = null;
                }

                $manualPct = null;
                $manualReason = null;
                if (!empty($service['excluded_from_veteran_quota'])) {
                    $rawPct = trim((string) ($service['manual_discount_percentage'] ?? ''));
                    if ($rawPct !== '' && (int) $rawPct > 0) {
                        $manualPct = (int) $rawPct;
                        $manualReason = trim((string) ($service['manual_discount_reason'] ?? '')) ?: null;
                    }
                }

                $rows[] = [
                    'service_catalog_id'         => $catalogId ? (int) $catalogId : null,
                    'service_catalog_variant_id' => !empty($service['service_catalog_variant_id'])
                        ? (int) $service['service_catalog_variant_id']
                        : null,
                    'guest_sort_order'           => $guestIndex,
                    'name'                       => trim($service['name']),
                    'unit_price'                 => (int) ($service['unit_price'] ?? 0),
                    'quantity'                   => (int) ($service['quantity'] ?? 1),
                    'discount_override'          => !empty($service['excluded_from_veteran_quota'])
                        ? null
                        : (($service['discount_override'] ?? '') !== ''
                            ? (int) $service['discount_override']
                            : null),
                    'excluded_from_veteran_quota' => !empty($service['excluded_from_veteran_quota']),
                    'manual_discount_percentage'  => $manualPct,
                    'manual_discount_reason'      => $manualReason,
                ];
            }
        }

        return $rows;
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
            2 => $this->bookerIsForeignGuest ? [
                'bookerPassportNumber' => ['required', 'string', 'min:5', 'max:32', 'regex:/^[A-Za-z0-9]+$/'],
                'foreignCountryId' => ['required', 'integer', 'exists:countries,id'],
                'foreignResidenceCityId' => ['required', 'integer', ...$this->residenceCityIdRules()],
                'guestContactName' => ['required', 'string', 'max:120'],
                'guestContactMobile' => ['required', 'regex:/^09[0-9]{9}$/'],
            ] : [
                'bookerNationalId' => ['required', 'digits:10'],
                'veteranType'      => ['nullable', 'string', Rule::in($veteranKeys)],
                'secondaryVeteranType' => ['nullable', 'string', Rule::in($veteranKeys)],
                'selectedVeteranTypes' => ['array', 'max:2'],
                'selectedVeteranTypes.*' => ['string', Rule::in($veteranKeys)],
                'guestContactName' => [Rule::requiredIf(!$this->bookerIsExistingUser), 'nullable', 'string', 'max:120'],
                'guestContactMobile' => [Rule::requiredIf(!$this->bookerIsExistingUser), 'nullable', 'regex:/^09[0-9]{9}$/'],
            ],
            3 => [
                'paymentMethod'      => ['required', 'in:cash,card_terminal'],
                'guestContactName'   => ['required', 'string', 'max:120'],
                'guestContactMobile' => ['required', 'string', 'max:15'],
                'guestDetails.*.full_name' => ['nullable', 'string', 'max:120'],
                'guestDetails.*.excluded_from_veteran_discount' => ['nullable', 'boolean'],
                'guestDetails.*.manual_discount_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
                'guestDetails.*.manual_discount_reason' => ['nullable', 'string', 'max:500'],
                'guestDetails.*.services.*.name' => ['nullable', 'string', 'max:200'],
                'guestDetails.*.services.*.unit_price' => ['nullable', 'integer', 'min:0'],
                'guestDetails.*.services.*.quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
                'guestDetails.*.services.*.discount_override' => ['nullable', 'integer', 'min:0', 'max:100'],
                'guestDetails.*.services.*.excluded_from_veteran_quota' => ['nullable', 'boolean'],
                'guestDetails.*.services.*.manual_discount_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
                'guestDetails.*.services.*.manual_discount_reason' => ['nullable', 'string', 'max:500'],
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
            'beneficiaries'    => \App\Models\ProgramBeneficiary::orderBy('name')->get(),
            'countries'        => Country::orderBy('name')->get(),
            'residenceCities'  => $this->foreignCountryId
                ? ResidenceCity::where('country_id', $this->foreignCountryId)->orderBy('name')->get()
                : collect(),
            'pdfRoute'         => $this->createdBookingId
                ? route($this->panel . '.bookings.pdf', $this->createdBookingId)
                : null,
            'bookingShowRoute' => $this->createdBookingId
                ? route($this->panel . '.bookings.show', $this->createdBookingId)
                : null,
        ]);
    }
}
