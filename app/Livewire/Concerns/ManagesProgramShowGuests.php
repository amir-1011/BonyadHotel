<?php

namespace App\Livewire\Concerns;

use App\Models\BookingGuestDetail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

trait ManagesProgramShowGuests
{
    public bool $guestEditMode = false;

    /** @var array<int, array{id?:int, full_name:string, national_id:string, mobile:string, relation:string, room_line_index:?int, sort_order:int}> */
    public array $guestRows = [];

    public function bootProgramShowGuests(): void
    {
        $this->loadProgramGuestRows();
    }

    public function loadProgramGuestRows(): void
    {
        $booking = $this->program->booking;

        if (!$booking) {
            $this->guestRows = [];

            return;
        }

        $guests = $booking->guestDetails->sortBy('sort_order');

        if ($guests->isEmpty()) {
            $count = max(1, (int) $this->program->guest_count);
            $this->guestRows = [];

            for ($i = 0; $i < $count; $i++) {
                $this->guestRows[] = $this->emptyProgramShowGuestRow($i);
            }

            return;
        }

        $this->guestRows = $guests->map(function (BookingGuestDetail $guest) use ($booking) {
            $roomLineIndex = null;

            if ($guest->booking_room_id) {
                $index = $booking->bookingRooms->search(
                    fn ($line) => (int) $line->id === (int) $guest->booking_room_id
                );
                $roomLineIndex = $index === false ? null : (int) $index;
            }

            $sortOrder = (int) $guest->sort_order;
            $fullName = trim((string) $guest->full_name);

            return [
                'id'              => $guest->id,
                'full_name'       => BookingGuestDetail::isGenericGuestName($fullName, $sortOrder) ? '' : $fullName,
                'national_id'     => (string) ($guest->national_id ?? ''),
                'mobile'          => (string) ($guest->mobile ?? ''),
                'relation'        => (string) ($guest->relation ?? ''),
                'room_line_index' => $roomLineIndex,
                'sort_order'      => $sortOrder,
            ];
        })->values()->all();
    }

    public function toggleGuestEditMode(): void
    {
        if ($this->guestEditMode) {
            $this->guestEditMode = false;
            $this->loadProgramGuestRows();
            $this->resetValidation();

            return;
        }

        $this->assertCanEditProgramGuests();
        $this->guestEditMode = true;
    }

    public function addProgramGuestRow(): void
    {
        $this->assertCanEditProgramGuests();

        $nextSort = count($this->guestRows);
        $this->guestRows[] = $this->emptyProgramShowGuestRow($nextSort);
    }

    public function removeProgramGuestRow(int $index): void
    {
        $this->assertCanEditProgramGuests();

        if (!isset($this->guestRows[$index]) || count($this->guestRows) <= 1) {
            return;
        }

        unset($this->guestRows[$index]);
        $this->guestRows = array_values($this->guestRows);

        foreach ($this->guestRows as $i => &$row) {
            $row['sort_order'] = $i;
        }
        unset($row);
    }

    public function saveProgramGuests(): void
    {
        $this->assertCanEditProgramGuests();

        $booking = $this->program->booking;
        abort_unless($booking, 404);

        $errors = [];

        foreach ($this->guestRows as $index => $row) {
            if (!$this->programShowGuestRowHasData($row)) {
                continue;
            }

            $nationalId = $this->normalizeProgramGuestDigits((string) ($row['national_id'] ?? ''));
            if ($nationalId !== '' && !preg_match('/^\d{10}$/', $nationalId)) {
                $errors["guestRows.{$index}.national_id"] = 'کد ملی باید ۱۰ رقم باشد.';
            }

            $mobile = $this->normalizeProgramGuestDigits((string) ($row['mobile'] ?? ''));
            if ($mobile !== '' && !preg_match('/^09\d{9}$/', $mobile)) {
                $errors["guestRows.{$index}.mobile"] = 'شماره موبایل معتبر نیست (مثال: 09123456789).';
            }
        }

        $nationalIds = collect($this->guestRows)
            ->map(fn ($row) => $this->normalizeProgramGuestDigits((string) ($row['national_id'] ?? '')))
            ->filter()
            ->values();

        if ($nationalIds->duplicates()->isNotEmpty()) {
            $errors['guestRows'] = 'کد ملی تکراری در لیست مهمانان وجود دارد.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $roomIdsByLine = $booking->bookingRooms
            ->values()
            ->mapWithKeys(fn ($line, $index) => [$index => $line->id])
            ->all();

        $keptIds = [];
        $savedCount = 0;

        foreach ($this->guestRows as $index => $row) {
            if (!$this->programShowGuestRowHasData($row)) {
                continue;
            }

            $sortOrder = (int) ($row['sort_order'] ?? $index);
            $fullName = trim((string) ($row['full_name'] ?? ''));
            $relation = trim((string) ($row['relation'] ?? ''));

            if ($sortOrder === 0 && $relation === '') {
                $relation = BookingGuestDetail::RELATION_MAIN_GUEST;
            }

            $roomLineIndex = $row['room_line_index'] ?? null;
            $bookingRoomId = ($roomLineIndex !== null && $roomLineIndex !== '' && isset($roomIdsByLine[(int) $roomLineIndex]))
                ? $roomIdsByLine[(int) $roomLineIndex]
                : null;

            $payload = [
                'booking_room_id' => $bookingRoomId,
                'sort_order'      => $sortOrder,
                'full_name'       => $fullName !== '' ? $fullName : 'مهمان ' . ($sortOrder + 1),
                'national_id'     => $this->normalizeProgramGuestDigits((string) ($row['national_id'] ?? '')) ?: null,
                'mobile'          => $this->normalizeProgramGuestDigits((string) ($row['mobile'] ?? '')) ?: null,
                'relation'        => $relation ?: null,
            ];

            if (!empty($row['id'])) {
                $guest = $booking->guestDetails()->find((int) $row['id']);
                if ($guest) {
                    $guest->update($payload);
                    $keptIds[] = $guest->id;
                    $savedCount++;

                    continue;
                }
            }

            $created = $booking->guestDetails()->create($payload);
            $keptIds[] = $created->id;
            $savedCount++;
        }

        $booking->guestDetails()->whereNotIn('id', $keptIds)->delete();

        $this->program->refresh()->load([
            'booking.guestDetails.bookingRoom.room',
            'booking.bookingRooms.room',
            'booking.bookingRooms.roomType',
        ]);

        $this->guestEditMode = false;
        $this->loadProgramGuestRows();
        $this->dispatch('toast', type: 'success', message: "لیست مهمانان ذخیره شد ({$savedCount} نفر).");
    }

    public function filledProgramGuestCount(): int
    {
        return collect($this->guestRows)
            ->filter(fn ($row) => $this->programShowGuestRowHasData($row))
            ->count();
    }

    /** @return array<int, array{booking_room_id:int, room_name:string}> */
    public function programRoomLines(): array
    {
        $booking = $this->program->booking;

        if (!$booking) {
            return [];
        }

        return $booking->bookingRooms
            ->values()
            ->map(fn ($line, $index) => [
                'booking_room_id' => (int) $line->id,
                'room_name'       => (string) ($line->room?->name ?? ('اتاق ' . ($index + 1))),
            ])
            ->all();
    }

    public function canEditProgramGuests(): bool
    {
        $booking = $this->program->booking;

        if (!$booking?->canEditBookingDetails(Auth::user())) {
            return false;
        }

        $user = Auth::user();

        if ($user?->isAdmin()) {
            return true;
        }

        return (bool) $user?->hostCan('bookings.guests', 'edit');
    }

    protected function assertCanEditProgramGuests(): void
    {
        abort_unless($this->canEditProgramGuests(), 403, 'امکان ویرایش مهمانان این برنامه وجود ندارد.');
    }

    /** @return array<string, mixed> */
    private function emptyProgramShowGuestRow(int $sortOrder): array
    {
        return [
            'full_name'       => '',
            'national_id'     => '',
            'mobile'          => '',
            'relation'        => $sortOrder === 0 ? BookingGuestDetail::RELATION_MAIN_GUEST : '',
            'room_line_index' => null,
            'sort_order'      => $sortOrder,
        ];
    }

    /** @param  array<string, mixed>  $row */
    private function programShowGuestRowHasData(array $row): bool
    {
        return trim((string) ($row['full_name'] ?? '')) !== ''
            || trim((string) ($row['national_id'] ?? '')) !== ''
            || trim((string) ($row['mobile'] ?? '')) !== '';
    }

    private function normalizeProgramGuestDigits(string $value): string
    {
        return strtr(trim($value), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }
}
