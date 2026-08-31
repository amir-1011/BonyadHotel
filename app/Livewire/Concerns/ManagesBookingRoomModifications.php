<?php

namespace App\Livewire\Concerns;

use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Services\BookingRoomModificationService;
use Illuminate\Support\Facades\Auth;

trait ManagesBookingRoomModifications
{
    public string $addRoomRoomTypeId = '';
    public string $addRoomRoomRateId = '';
    public int $addRoomAdults = 1;
    public int $addRoomChildrenUnder6 = 0;
    public int $addRoomExtraGuests = 0;
    public bool $addRoomBillFullRooms = false;
    public ?int $addRoomPhysicalRoomId = null;
    public string $addRoomPhysicalRoomName = '';

    public function canModifyBookingRooms(): bool
    {
        $booking = $this->booking ?? null;

        if (!$booking?->canEditBookingDetails(Auth::user())) {
            return false;
        }

        if ($booking->hasPendingCancellationRequest()) {
            return false;
        }

        if ($booking->booking_source === 'online') {
            return false;
        }

        $user = Auth::user();

        if ($user?->isAdmin()) {
            return true;
        }

        return (bool) $user?->hostCan('bookings.rooms', 'write');
    }

    public function updatedAddRoomRoomTypeId(): void
    {
        $this->addRoomRoomRateId = '';
        $this->clearAddRoomPhysicalSelection();

        if ($this->addRoomRoomTypeId === '') {
            return;
        }

        $roomType = $this->resolveAddRoomType();
        if (!$roomType) {
            return;
        }

        $defaultRate = $roomType->rates()->where('is_active', true)->orderBy('price_per_night')->first();
        if ($defaultRate) {
            $this->addRoomRoomRateId = (string) $defaultRate->id;
        }
    }

    public function openAddRoomPicker(): void
    {
        if (!$this->canModifyBookingRooms()) {
            return;
        }

        if ($this->addRoomRoomTypeId === '') {
            $this->addError('addRoomRoomTypeId', 'ابتدا نوع اتاق را انتخاب کنید.');
            return;
        }

        $roomType = $this->resolveAddRoomType();
        if (!$roomType) {
            $this->addError('addRoomRoomTypeId', 'نوع اتاق معتبر نیست.');
            return;
        }

        $assignedRoomIds = $this->booking->bookingRooms
            ->pluck('room_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $detail = [
            'roomTypeId'        => (int) $roomType->id,
            'roomTypeName'      => $roomType->name,
            'checkIn'           => $this->booking->check_in->format('Y-m-d'),
            'checkOut'          => $this->booking->check_out->format('Y-m-d'),
            'excludeRoomIds'    => $assignedRoomIds,
            'excludeBookingId'  => $this->booking->id,
            'roomsToSelect'     => 1,
            'explicitConfirm'   => true,
        ];

        $encoded = json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $this->js("window.dispatchEvent(new CustomEvent('manual-booking-open-room-picker', { detail: {$encoded} }))");
    }

    /**
     * @param  array<int, array<string, mixed>>  $rooms
     */
    public function onAddRoomPhysicalSelected(array $rooms): void
    {
        if (!$this->canModifyBookingRooms() || $rooms === []) {
            return;
        }

        $picked = $rooms[0] ?? null;
        $roomId = (int) ($picked['roomId'] ?? $picked['id'] ?? 0);

        if ($roomId <= 0) {
            return;
        }

        $room = Room::with('roomType')->find($roomId);
        if (!$room || $room->roomType?->accommodation_id !== $this->booking->accommodation_id) {
            $this->addError('addRoomRoomTypeId', 'اتاق انتخاب‌شده معتبر نیست.');
            return;
        }

        $resolvedTypeId = (int) $room->room_type_id;
        $payloadTypeId = (int) ($picked['roomTypeId'] ?? $picked['room_type_id'] ?? 0);

        if ($this->addRoomRoomTypeId !== '' && (int) $this->addRoomRoomTypeId !== $resolvedTypeId) {
            $this->addError('addRoomRoomTypeId', 'اتاق انتخاب‌شده با نوع اتاق فرم هم‌خوانی ندارد.');
            return;
        }

        if ($payloadTypeId > 0 && $payloadTypeId !== $resolvedTypeId) {
            $this->addError('addRoomRoomTypeId', 'اتاق انتخاب‌شده با نوع اتاق فرم هم‌خوانی ندارد.');
            return;
        }

        if ($this->addRoomRoomTypeId === '') {
            $this->addRoomRoomTypeId = (string) $resolvedTypeId;
            $this->updatedAddRoomRoomTypeId();
        }

        $this->addRoomPhysicalRoomId = $roomId;
        $this->addRoomPhysicalRoomName = (string) ($picked['roomName'] ?? $picked['name'] ?? $room->name);
        $this->resetErrorBag('addRoomRoomTypeId');
    }

    public function clearAddRoomPhysicalSelection(): void
    {
        $this->addRoomPhysicalRoomId = null;
        $this->addRoomPhysicalRoomName = '';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function guestAdditionRoomOptions(): array
    {
        return app(BookingRoomModificationService::class)->roomLinesForGuestAddition($this->booking);
    }

    /**
     * @return \Illuminate\Support\Collection<int, RoomRate>
     */
    protected function addRoomRateOptions()
    {
        $roomType = $this->resolveAddRoomType();

        if (!$roomType) {
            return collect();
        }

        return $roomType->rates()->where('is_active', true)->orderBy('price_per_night')->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, RoomType>
     */
    protected function addRoomTypeOptions()
    {
        return $this->booking->accommodation
            ->roomTypes()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    protected function afterBookingRoomModification(Booking $booking): void
    {
        $this->booking = $booking->load([
            'user.country', 'user.residenceCity', 'accommodation.city.province', 'roomType', 'roomRate',
            'services.serviceCatalog', 'services.serviceCatalogVariant',
            'guestDetails.country', 'guestDetails.residenceCity',
            'guestDetails.bookingRoom.room', 'guestDetails.bookingRoom.roomType',
            'createdBy',
            'bookingRooms.roomType', 'bookingRooms.roomRate', 'bookingRooms.room',
            'beneficiaryCosts.beneficiary.user', 'beneficiaryCosts.user',
            'program',
        ]);

        $this->loadEditableGuests();
    }

    private function resetAddRoomForm(): void
    {
        $this->addRoomRoomTypeId = '';
        $this->addRoomRoomRateId = '';
        $this->addRoomAdults = 1;
        $this->addRoomChildrenUnder6 = 0;
        $this->addRoomExtraGuests = 0;
        $this->addRoomBillFullRooms = false;
        $this->clearAddRoomPhysicalSelection();
        $this->resetErrorBag();
    }

    private function resolveAddRoomType(): ?RoomType
    {
        if ($this->addRoomRoomTypeId === '') {
            return null;
        }

        return RoomType::query()
            ->where('accommodation_id', $this->booking->accommodation_id)
            ->where('is_active', true)
            ->find((int) $this->addRoomRoomTypeId);
    }
}
