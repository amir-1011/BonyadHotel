<?php

namespace App\Livewire\Concerns;

use App\Models\BookingGuestDetail;

trait ManagesProgramGuests
{
    /** @var array<int, array{full_name:string, national_id:string, mobile:string, relation:string, room_line_index:?int, room_id:?int, room_name:?string}> */
    public array $guestRows = [];

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $guestListDocuments = [];

    public function updatedGuestCount(): void
    {
        $this->syncGuestRows();
    }

    public function hydrateGuestStep(): void
    {
        $this->syncGuestRows();
    }

    public function syncGuestRows(): void
    {
        $count = max(1, (int) $this->guestCount);

        while (count($this->guestRows) < $count) {
            $this->guestRows[] = $this->emptyProgramGuestRow();
        }

        $this->guestRows = array_slice($this->guestRows, 0, $count);

        if (isset($this->guestRows[0]) && trim((string) ($this->guestRows[0]['relation'] ?? '')) === '') {
            $this->guestRows[0]['relation'] = BookingGuestDetail::RELATION_MAIN_GUEST;
        }

        $this->assignProgramGuestsToRoomLines();
    }

    public function removeGuestRow(int $index): void
    {
        if (!isset($this->guestRows[$index])) {
            return;
        }

        unset($this->guestRows[$index]);
        $this->guestRows = array_values($this->guestRows);
        $this->guestCount = max(1, count($this->guestRows));
        $this->assignProgramGuestsToRoomLines();
    }

    public function updatedGuestRows($value, string $key): void
    {
        if (str_ends_with($key, 'room_line_index')) {
            $this->applyGuestRoomSelection($key);
        }
    }

    public function guestRoomLabel(int $index): ?string
    {
        $guest = $this->guestRows[$index] ?? [];

        return trim((string) ($guest['room_name'] ?? '')) ?: null;
    }

    public function filledGuestCount(): int
    {
        return collect($this->guestRows)
            ->filter(fn ($row) => $this->guestRowHasData($row))
            ->count();
    }

    /** @return array<int, array<string, mixed>> */
    protected function filledGuestDetails(): array
    {
        $rows = [];

        foreach ($this->guestRows as $index => $row) {
            if (!$this->guestRowHasData($row)) {
                continue;
            }

            $rows[] = [
                'full_name'        => trim((string) ($row['full_name'] ?? '')),
                'national_id'      => $this->normalizeDigits((string) ($row['national_id'] ?? '')),
                'mobile'           => $this->normalizeDigits((string) ($row['mobile'] ?? '')),
                'relation'         => trim((string) ($row['relation'] ?? '')),
                'room_line_index'  => isset($row['room_line_index']) ? (int) $row['room_line_index'] : null,
                'sort_order'       => $index,
            ];
        }

        return $rows;
    }

    protected function validateStepGuests(): void
    {
        $this->syncGuestRows();

        $errors = [];

        foreach ($this->guestRows as $index => $row) {
            if (!$this->guestRowHasData($row)) {
                continue;
            }

            $nationalId = $this->normalizeDigits((string) ($row['national_id'] ?? ''));
            if ($nationalId !== '' && !preg_match('/^\d{10}$/', $nationalId)) {
                $errors["guestRows.{$index}.national_id"] = 'کد ملی باید ۱۰ رقم باشد.';
            }

            $mobile = $this->normalizeDigits((string) ($row['mobile'] ?? ''));
            if ($mobile !== '' && !preg_match('/^09\d{9}$/', $mobile)) {
                $errors["guestRows.{$index}.mobile"] = 'شماره موبایل معتبر نیست (مثال: 09123456789).';
            }
        }

        $nationalIds = collect($this->guestRows)
            ->map(fn ($row) => $this->normalizeDigits((string) ($row['national_id'] ?? '')))
            ->filter()
            ->values();

        if ($nationalIds->duplicates()->isNotEmpty()) {
            $errors['guestRows'] = 'کد ملی تکراری در لیست مهمانان وجود دارد.';
        }

        if ($errors !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
    }

    /** @return array<string, mixed> */
    private function emptyProgramGuestRow(): array
    {
        return [
            'full_name'        => '',
            'national_id'      => '',
            'mobile'           => '',
            'relation'         => '',
            'room_line_index'  => null,
            'room_id'          => null,
            'room_name'        => null,
        ];
    }

    private function assignProgramGuestsToRoomLines(): void
    {
        if ($this->roomLines === []) {
            return;
        }

        $unassigned = [];
        foreach ($this->guestRows as $index => $row) {
            if (!$this->guestHasManualRoom($row)) {
                $unassigned[] = $index;
            }
        }

        if ($unassigned === []) {
            return;
        }

        $roomCount = count($this->roomLines);
        $guestCount = count($unassigned);
        $base = intdiv($guestCount, $roomCount);
        $extra = $guestCount % $roomCount;
        $assignIndex = 0;

        foreach ($this->roomLines as $lineIndex => $line) {
            $slots = $base + ($lineIndex < $extra ? 1 : 0);
            $roomName = (string) ($line['room_name'] ?? ('اتاق ' . ($lineIndex + 1)));

            for ($slot = 0; $slot < $slots; $slot++) {
                if (!isset($unassigned[$assignIndex])) {
                    break 2;
                }

                $guestIndex = $unassigned[$assignIndex];
                $this->guestRows[$guestIndex]['room_line_index'] = $lineIndex;
                $this->guestRows[$guestIndex]['room_id'] = !empty($line['room_id']) ? (int) $line['room_id'] : null;
                $this->guestRows[$guestIndex]['room_name'] = $roomName;
                $assignIndex++;
            }
        }
    }

    private function applyGuestRoomSelection(string $key): void
    {
        [$index] = explode('.', $key);
        $index = (int) $index;

        if (!isset($this->guestRows[$index])) {
            return;
        }

        $lineIndex = $this->guestRows[$index]['room_line_index'] ?? null;
        if ($lineIndex === null || $lineIndex === '') {
            $this->guestRows[$index]['room_id'] = null;
            $this->guestRows[$index]['room_name'] = null;

            return;
        }

        $lineIndex = (int) $lineIndex;
        $line = $this->roomLines[$lineIndex] ?? null;

        if (!$line) {
            return;
        }

        $this->guestRows[$index]['room_id'] = !empty($line['room_id']) ? (int) $line['room_id'] : null;
        $this->guestRows[$index]['room_name'] = (string) ($line['room_name'] ?? ('اتاق ' . ($lineIndex + 1)));
    }

    /** @param  array<string, mixed>  $row */
    private function guestHasManualRoom(array $row): bool
    {
        return isset($row['room_line_index'])
            && $row['room_line_index'] !== null
            && $row['room_line_index'] !== '';
    }

    /** @param  array<string, mixed>  $row */
    private function guestRowHasData(array $row): bool
    {
        return trim((string) ($row['full_name'] ?? '')) !== ''
            || trim((string) ($row['national_id'] ?? '')) !== ''
            || trim((string) ($row['mobile'] ?? '')) !== '';
    }

    private function normalizeDigits(string $value): string
    {
        return strtr(trim($value), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }
}
