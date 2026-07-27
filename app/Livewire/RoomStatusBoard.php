<?php

namespace App\Livewire;

use App\Livewire\Concerns\AssertsHostPermissions;
use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\BookingGuestDetail;
use App\Models\BookingRoom;
use App\Models\RoomType;
use App\Services\BlockedDatesService;
use App\Services\RoomBoardLayoutService;
use App\Services\RoomStatusBoardService;
use App\Services\RoomSyncService;
use App\Support\HostPermissions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Defer;
use Livewire\Attributes\On;
use Livewire\Component;
use Morilog\Jalali\Jalalian;

/**
 * Deferred: this widget runs a heavy per-room-type availability build, so it is
 * excluded from the initial dashboard response and loaded asynchronously right
 * after first paint (via Livewire's defer/lazy mechanism), keeping the page's
 * first response fast.
 */
#[Defer]
class RoomStatusBoard extends Component
{
    use AssertsHostPermissions;

    public string $panel = 'host';

    public string $viewDateJalali = '';

    public string $viewDate = '';

    public ?int $accommodationId = null;

    public ?int $activeAccommodationId = null;

    public bool $boardVisible = false;

    public ?array $selectedRoom = null;

    /** @var array<int, array{id:int, name:string, price_per_night:int}> */
    public array $selectedRoomRates = [];

    public ?int $bookingRoomRateId = null;

    public string $blockReason = '';

    public string $actionMessage = '';

    public ?int $servicesBookingId = null;

    public bool $layoutEditMode = false;

    /** @var array<int> */
    public array $dashboardAccommodationIds = [];

    public bool $useDashboardFilter = false;

    /**
     * Editable layouts keyed by accommodation_id.
     *
     * @var array<string, array{cols: int, rows: array<int, array<int>>, row_labels: array<int, string>}>
     */
    public array $editLayouts = [];

    public function mount(string $panel = 'host', array $dashboardAccommodationIds = [], bool $useDashboardFilter = false): void
    {
        $this->panel = $panel;
        $this->dashboardAccommodationIds = array_values(array_map('intval', $dashboardAccommodationIds));
        $this->useDashboardFilter = $useDashboardFilter;
        $this->viewDate = now()->toDateString();
        $this->viewDateJalali = Jalalian::fromCarbon(now())->format('Y/m/d');

        if ($this->panel === 'host') {
            $this->boardVisible = true;
        }
    }

    public function applyDate(): void
    {
        if (!$this->resolveViewDate()) {
            return;
        }

        $this->selectedRoom = null;
        $this->invalidateBoardCache();
    }

    public function viewRooms(): void
    {
        if (!$this->accommodationId) {
            $this->addError('accommodationId', 'ابتدا اقامتگاه را انتخاب کنید.');

            return;
        }

        if (!$this->resolveViewDate()) {
            return;
        }

        $this->activeAccommodationId = $this->accommodationId;
        $this->boardVisible = true;
        $this->selectedRoom = null;
        $this->invalidateBoardCache();
    }

    public function showFilteredRooms(): void
    {
        if ($this->panel !== 'admin' || !$this->useDashboardFilter) {
            return;
        }

        if (!$this->resolveViewDate()) {
            return;
        }

        if ($this->resolvedAccommodationIds() === []) {
            $this->addError('accommodationId', 'اقامتگاهی برای نمایش انتخاب نشده است.');

            return;
        }

        $this->boardVisible = true;
        $this->selectedRoom = null;
        $this->invalidateBoardCache();
    }

    public function selectRoom(int $accommodationId, int $roomId): void
    {
        if ($this->layoutEditMode) {
            return;
        }

        foreach ($this->board as $acc) {
            if ($acc['accommodation_id'] !== $accommodationId) {
                continue;
            }
            foreach ($acc['rooms'] as $room) {
                if ($room['id'] === $roomId) {
                    $this->selectedRoom = array_merge($room, [
                        'accommodation_id'   => $accommodationId,
                        'accommodation_name' => $acc['accommodation_name'],
                    ]);
                    $this->actionMessage = '';
                    $this->blockReason = $room['block_reason'] ?? '';
                    $this->loadSelectedRoomRates((int) $room['room_type_id']);
                    $this->resolveServicesBookingForRoom($room);

                    return;
                }
            }
        }
    }

    public function closeDetail(): void
    {
        $this->selectedRoom = null;
        $this->selectedRoomRates = [];
        $this->bookingRoomRateId = null;
        $this->blockReason = '';
        $this->actionMessage = '';
        $this->servicesBookingId = null;
    }

    public function selectServicesBooking(int $bookingId): void
    {
        if (!$this->selectedRoom || !$this->bookingBelongsToSelectedRoom($bookingId)) {
            return;
        }

        $this->servicesBookingId = $bookingId;
    }

    #[On('booking-services-updated')]
    public function onBookingServicesUpdated(): void
    {
        if (!$this->selectedRoom) {
            return;
        }

        $accId = (int) $this->selectedRoom['accommodation_id'];
        $roomId = (int) $this->selectedRoom['id'];
        $bookingId = $this->servicesBookingId;
        $this->invalidateBoardCache();
        $this->selectRoom($accId, $roomId);
        if ($bookingId) {
            $this->servicesBookingId = $bookingId;
        }
    }

    public function blockSelectedRoom(): void
    {
        if ($this->panel !== 'host' || !$this->selectedRoom) {
            return;
        }

        $this->validate([
            'blockReason' => ['nullable', 'string', 'max:200'],
        ]);

        if (!$this->resolveViewDate()) {
            return;
        }

        if ($this->viewDate < now()->toDateString()) {
            $this->addError('blockReason', 'مسدودسازی فقط برای امروز و روزهای آینده امکان‌پذیر است.');

            return;
        }

        $accommodation = Accommodation::find($this->selectedRoom['accommodation_id']);
        if (!$accommodation?->isManagedBy(Auth::user())) {
            abort(403);
        }

        $roomType = RoomType::find($this->selectedRoom['room_type_id']);
        if (!$roomType || $roomType->accommodation_id !== $accommodation->id) {
            $this->addError('blockReason', 'نوع اتاق معتبر نیست.');

            return;
        }

        $roomId = (int) $this->selectedRoom['id'];
        $roomSync = app(RoomSyncService::class);
        $roomSync->syncFromRoomType($roomType);

        if (!$roomType->rooms()->whereKey($roomId)->exists()) {
            $this->addError('blockReason', 'اتاق انتخاب‌شده معتبر نیست.');

            return;
        }

        $service = app(BlockedDatesService::class);
        $date = $this->viewDate;

        try {
            $service->store($roomType, $date, $date, [$roomId], $this->blockReason !== '' ? $this->blockReason : null);
        } catch (ValidationException $e) {
            $messages = $e->errors();
            $first = reset($messages);
            $this->addError('blockReason', is_array($first) ? ($first[0] ?? 'خطا در مسدودسازی') : (string) $first);

            return;
        }

        $accId = (int) $this->selectedRoom['accommodation_id'];
        $this->blockReason = '';
        $this->resetErrorBag('blockReason');
        $this->actionMessage = 'اتاق «' . $this->selectedRoom['name'] . '» برای تاریخ ' . $this->viewDateJalali . ' مسدود شد.';
        $this->invalidateBoardCache();
        $this->selectRoom($accId, $roomId);
    }

    public function unblockSelectedRoom(): void
    {
        if ($this->panel !== 'host' || !$this->selectedRoom) {
            return;
        }

        if (!$this->resolveViewDate()) {
            return;
        }

        $accommodation = Accommodation::find($this->selectedRoom['accommodation_id']);
        if (!$accommodation?->isManagedBy(Auth::user())) {
            abort(403);
        }

        $roomType = RoomType::find($this->selectedRoom['room_type_id']);
        if (!$roomType || $roomType->accommodation_id !== $accommodation->id) {
            $this->addError('blockReason', 'نوع اتاق معتبر نیست.');

            return;
        }

        $roomId = (int) $this->selectedRoom['id'];
        $deleted = app(BlockedDatesService::class)->destroyForRoomOnDate($roomType, $roomId, $this->viewDate);

        if (!$deleted) {
            $this->addError('blockReason', 'رکورد مسدودی برای این تاریخ یافت نشد.');

            return;
        }

        $accId = (int) $this->selectedRoom['accommodation_id'];
        $this->blockReason = '';
        $this->resetErrorBag('blockReason');
        $this->actionMessage = 'مسدودیت اتاق «' . $this->selectedRoom['name'] . '» برای تاریخ ' . $this->viewDateJalali . ' برداشته شد.';
        $this->invalidateBoardCache();
        $this->selectRoom($accId, $roomId);
    }

    public function goToManualBooking(): void
    {
        if ($this->panel !== 'host' || !$this->selectedRoom || !$this->bookingRoomRateId) {
            return;
        }

        $accommodation = Accommodation::find($this->selectedRoom['accommodation_id']);
        if (!$accommodation?->isManagedBy(Auth::user())) {
            abort(403);
        }

        $roomTypeId = (int) $this->selectedRoom['room_type_id'];
        $rateValid = collect($this->selectedRoomRates)->contains('id', $this->bookingRoomRateId);
        if (!$rateValid) {
            $this->addError('bookingRoomRateId', 'لطفاً تعرفه را انتخاب کنید.');

            return;
        }

        $roomType = $accommodation->roomTypes()->whereKey($roomTypeId)->first();
        if (!$roomType) {
            $this->addError('bookingRoomRateId', 'نوع اتاق معتبر نیست.');

            return;
        }

        $this->redirect(
            route('host.accommodations.manual-booking', $accommodation) . '?' . http_build_query([
                'room_type_id' => $roomTypeId,
                'room_rate_id' => $this->bookingRoomRateId,
                'room_id'      => (int) $this->selectedRoom['id'],
                'focus'        => 'dates',
            ]),
            navigate: true,
        );
    }

    private function loadSelectedRoomRates(int $roomTypeId): void
    {
        $roomType = RoomType::with(['rates' => fn ($q) => $q->where('is_active', true)->orderBy('price_per_night')])
            ->find($roomTypeId);

        $this->selectedRoomRates = $roomType?->rates
            ->map(fn ($rate) => [
                'id'              => $rate->id,
                'name'            => $rate->name,
                'price_per_night' => (int) $rate->price_per_night,
            ])
            ->all() ?? [];

        $this->bookingRoomRateId = $this->selectedRoomRates[0]['id'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $room
     */
    private function resolveServicesBookingForRoom(array $room): void
    {
        $this->servicesBookingId = null;

        if (!empty($room['current_booking']['booking_id'])
            && in_array($room['current_booking']['status'] ?? '', ['pending', 'confirmed'], true)
            && $this->bookingBelongsToSelectedRoom((int) $room['current_booking']['booking_id'])) {
            $this->servicesBookingId = (int) $room['current_booking']['booking_id'];

            return;
        }

        $future = $room['future_bookings'] ?? [];
        if (count($future) === 1 && in_array($future[0]['status'] ?? '', ['pending', 'confirmed'], true)) {
            $bookingId = (int) $future[0]['booking_id'];
            if ($this->bookingBelongsToSelectedRoom($bookingId)) {
                $this->servicesBookingId = $bookingId;
            }
        }
    }

    private function bookingBelongsToSelectedRoom(int $bookingId): bool
    {
        if (!$this->selectedRoom || $bookingId <= 0) {
            return false;
        }

        return BookingRoom::query()
            ->where('booking_id', $bookingId)
            ->where('room_id', (int) $this->selectedRoom['id'])
            ->whereHas('booking', fn ($q) => $q->whereIn('status', ['pending', 'confirmed']))
            ->exists();
    }

    public function toggleLayoutEdit(): void
    {
        if ($this->panel !== 'host') {
            return;
        }

        if ($this->layoutEditMode) {
            $this->layoutEditMode = false;
            $this->editLayouts = [];
            $this->invalidateBoardCache();
            $this->dispatch('rsb-layout-edit-closed');

            return;
        }

        $this->assertCanEditBuildingLayout();

        $this->selectedRoom = null;
        $this->invalidateBoardCache();
        $this->initEditLayouts();
        $this->layoutEditMode = true;
        $this->dispatch('rsb-layout-edit-opened');
    }

    public function addLayoutRow(int $accommodationId): void
    {
        $this->assertCanEditBuildingLayout();

        $key = (string) $accommodationId;
        if (!isset($this->editLayouts[$key])) {
            return;
        }

        $this->editLayouts[$key]['rows'][] = [];
        $this->editLayouts[$key]['row_labels'][] = '';
    }

    public function setRowLabel(int $accommodationId, int $rowIndex, string $label): void
    {
        $this->assertCanEditBuildingLayout();

        $key = (string) $accommodationId;
        if (!isset($this->editLayouts[$key]['row_labels'][$rowIndex])) {
            return;
        }

        $this->editLayouts[$key]['row_labels'][$rowIndex] = app(RoomBoardLayoutService::class)
            ->sanitizeRowLabel($label);
    }

    public function setLayoutCols(int $accommodationId, int $cols): void
    {
        $this->assertCanEditBuildingLayout();

        $key = (string) $accommodationId;
        if (!isset($this->editLayouts[$key])) {
            return;
        }

        $this->editLayouts[$key]['cols'] = max(1, min(12, $cols));
    }

    public function sortRoom(int $roomId, int $position, string $groupId): void
    {
        $this->assertCanEditBuildingLayout();

        if (!$this->layoutEditMode || !str_contains($groupId, ':')) {
            return;
        }

        [$accommodationId, $rowIndex] = explode(':', $groupId, 2);
        $key = (string) (int) $accommodationId;

        if (!isset($this->editLayouts[$key])) {
            return;
        }

        $this->editLayouts[$key] = app(RoomBoardLayoutService::class)->applySortMove(
            $this->editLayouts[$key],
            $roomId,
            $position,
            (int) $rowIndex,
        );
    }

    public function sortLayoutRow(int $rowIndex, int $position, string $accommodationId): void
    {
        $this->assertCanEditBuildingLayout();

        if (!$this->layoutEditMode) {
            return;
        }

        $key = (string) (int) $accommodationId;

        if (!isset($this->editLayouts[$key])) {
            return;
        }

        $this->editLayouts[$key] = app(RoomBoardLayoutService::class)->applyRowSortMove(
            $this->editLayouts[$key],
            $rowIndex,
            $position,
        );
    }

    public function saveLayout(): void
    {
        if ($this->panel !== 'host' || !$this->layoutEditMode) {
            return;
        }

        $this->assertCanEditBuildingLayout();

        $user = Auth::user();
        $service = app(RoomBoardLayoutService::class);

        foreach ($this->editLayouts as $accommodationId => $layout) {
            $accommodation = Accommodation::find((int) $accommodationId);
            if (!$accommodation?->isManagedBy($user)) {
                continue;
            }

            $service->saveAccommodationLayout($accommodation, $layout);
        }

        $this->layoutEditMode = false;
        $this->editLayouts = [];
        $this->invalidateBoardCache();
        session()->flash('status', 'چیدمان نقشه ساختمان ذخیره شد.');
    }

    public function resetAccommodationLayout(int $accommodationId): void
    {
        if ($this->panel !== 'host') {
            return;
        }

        $this->assertCanEditBuildingLayout();

        $accommodation = Accommodation::find($accommodationId);
        if (!$accommodation?->isManagedBy(Auth::user())) {
            return;
        }

        app(RoomBoardLayoutService::class)->clearAccommodationLayout($accommodation);

        if ($this->layoutEditMode) {
            $this->initEditLayouts();
        } else {
            $this->invalidateBoardCache();
        }
    }

    private function assertCanEditBuildingLayout(): void
    {
        if ($this->panel !== 'host') {
            return;
        }

        $this->assertHostCan('dashboard.room-status-board', HostPermissions::ACTION_EDIT);
    }

    private function initEditLayouts(): void
    {
        $service = app(RoomBoardLayoutService::class);
        $this->editLayouts = [];

        foreach ($this->board as $acc) {
            $accommodationId = $acc['accommodation_id'];
            $saved = $service->getAccommodationLayout($accommodationId);
            $this->editLayouts[(string) $accommodationId] = $service->buildEditableLayout(
                $acc['rooms'],
                $saved,
            );
        }
    }

    private function resolveViewDate(): bool
    {
        $this->viewDateJalali = $this->normalizeJalaliDigits($this->viewDateJalali);
        $gregorian = $this->parseJalaliToGregorian($this->viewDateJalali);

        if ($gregorian === null) {
            $this->addError('viewDateJalali', 'تاریخ شمسی وارد شده معتبر نیست.');

            return false;
        }

        $this->resetErrorBag('viewDateJalali');
        $this->viewDate = $gregorian;

        return true;
    }

    private function normalizeJalaliDigits(string $jalali): string
    {
        return strtr(trim($jalali), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    private function parseJalaliToGregorian(string $jalali): ?string
    {
        $jalali = $this->normalizeJalaliDigits($jalali);
        if ($jalali === '' || !preg_match('/^\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}$/', $jalali)) {
            return null;
        }

        try {
            return Jalalian::fromFormat('Y/m/d', str_replace('-', '/', $jalali))
                ->toCarbon()
                ->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }

    protected function invalidateBoardCache(): void
    {
        unset($this->board);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function board(): array
    {
        if ($this->panel === 'admin' && !$this->boardVisible) {
            return [];
        }

        $service = app(RoomStatusBoardService::class);
        $date = $this->viewDate ?: now()->toDateString();
        $ids = $this->resolvedAccommodationIds();

        if ($this->panel === 'admin' && !$this->useDashboardFilter) {
            if (!$this->activeAccommodationId) {
                return [];
            }

            return $service->buildForAccommodation($this->activeAccommodationId, $date);
        }

        if ($ids === []) {
            return [];
        }

        return $service->buildForAccommodations($ids, $date);
    }

    /** @return array<int> */
    private function resolvedAccommodationIds(): array
    {
        if ($this->useDashboardFilter) {
            if ($this->panel === 'host') {
                $managed = Auth::user()->managedAccommodationIds()->map(fn ($id) => (int) $id)->all();

                return array_values(array_intersect($this->dashboardAccommodationIds, $managed));
            }

            return $this->dashboardAccommodationIds;
        }

        if ($this->panel === 'host') {
            return Auth::user()->managedAccommodationIds()->map(fn ($id) => (int) $id)->all();
        }

        if ($this->activeAccommodationId) {
            return [(int) $this->activeAccommodationId];
        }

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSelectedRoomGuestsProperty(): array
    {
        if (!$this->servicesBookingId || !$this->selectedRoom) {
            return [];
        }

        $bookingRoom = BookingRoom::query()
            ->where('booking_id', $this->servicesBookingId)
            ->where('room_id', (int) $this->selectedRoom['id'])
            ->first();

        if (!$bookingRoom) {
            return [];
        }

        $guests = BookingGuestDetail::query()
            ->where('booking_id', $this->servicesBookingId)
            ->where('booking_room_id', $bookingRoom->id)
            ->orderBy('sort_order')
            ->get();

        if ($guests->isEmpty()) {
            $assignedPhysicalRooms = BookingRoom::query()
                ->where('booking_id', $this->servicesBookingId)
                ->whereNotNull('room_id')
                ->count();

            if ($assignedPhysicalRooms === 1) {
                $guests = BookingGuestDetail::query()
                    ->with(['country', 'residenceCity'])
                    ->where('booking_id', $this->servicesBookingId)
                    ->orderBy('sort_order')
                    ->get();
            }
        }

        return $guests->map(fn (BookingGuestDetail $guest) => [
            'id'         => $guest->id,
            'sort_order' => $guest->sort_order,
            'full_name'  => $guest->full_name,
            'national_id'=> $guest->national_id,
            'identity_label' => $guest->identityFieldLabel(),
            'identity_number' => $guest->identityNumber(),
            'residence_label' => $guest->residenceLocationLabel(),
            'mobile'     => $guest->mobile,
            'relation'   => $guest->relation,
        ])->all();
    }

    public function getServicesBookingProperty(): ?Booking
    {
        if (!$this->servicesBookingId) {
            return null;
        }

        return Booking::query()
            ->with(['services.serviceCatalog', 'services.serviceCatalogVariant'])
            ->find($this->servicesBookingId);
    }

    public function placeholder(array $params = []): string
    {
        return <<<'HTML'
            <div class="ta-card h-100">
                <div class="ta-card__head">
                    <h2 class="ta-card__title mb-0"><i class="bi bi-grid-3x3-gap me-2"></i>وضعیت اتاق‌ها</h2>
                </div>
                <div class="ta-card__body d-flex align-items-center justify-content-center" style="min-height:220px">
                    <div class="spinner-border text-primary" role="status" style="width:2rem;height:2rem;">
                        <span class="visually-hidden">در حال بارگذاری...</span>
                    </div>
                </div>
            </div>
            HTML;
    }

    public function render()
    {
        $accommodations = $this->panel === 'admin'
            ? Accommodation::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('livewire.room-status-board', [
            'accommodations' => $accommodations,
            'board' => $this->board,
            'canEditBuildingLayout' => $this->hostUserCan('dashboard.room-status-board', HostPermissions::ACTION_EDIT),
        ]);
    }
}
