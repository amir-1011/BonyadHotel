<?php

namespace App\Services;

use App\Models\BookingRoom;
use App\Models\RoomType;
use App\Models\RoomTypeBlockedDate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Morilog\Jalali\Jalalian;

class BlockedDatesService
{
    /**
     * @return array{date_from:string, date_to:string, reason:?string, room_ids:array<int>}
     */
    public function validateStoreRequest(Request $request, RoomType $roomType): array
    {
        $validRoomIds = $roomType->rooms()->pluck('id')->map(fn ($id) => (int) $id)->all();

        $data = $request->validate([
            'date_from' => ['required', 'string', 'regex:/^\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}$/'],
            'date_to'   => ['required', 'string', 'regex:/^\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}$/'],
            'reason'    => ['nullable', 'string', 'max:200'],
            'room_ids'  => ['required', 'array', 'min:1'],
            'room_ids.*'=> ['integer', Rule::in($validRoomIds)],
        ], [
            'room_ids.required' => 'حداقل یک اتاق فیزیکی را انتخاب کنید.',
            'room_ids.min'      => 'حداقل یک اتاق فیزیکی را انتخاب کنید.',
            'room_ids.*.in'     => 'اتاق انتخاب‌شده معتبر نیست.',
        ]);

        $data['room_ids'] = array_values(array_unique(array_map('intval', $data['room_ids'])));

        return $data;
    }

    /**
     * @return array{ok:bool, from:?string, to:?string, errors?:array<string,string>}
     */
    public function parseJalaliRange(string $dateFrom, string $dateTo): array
    {
        try {
            $split = fn (string $s) => array_map('intval', preg_split('/[\/\-]/', $s));
            [$fy, $fm, $fd] = $split($dateFrom);
            [$ty, $tm, $td] = $split($dateTo);
            $fromGreg = (new Jalalian($fy, $fm, $fd))->toCarbon()->format('Y-m-d');
            $toGreg   = (new Jalalian($ty, $tm, $td))->toCarbon()->format('Y-m-d');
        } catch (\Exception $e) {
            return ['ok' => false, 'from' => null, 'to' => null, 'errors' => ['date_from' => 'تاریخ خورشیدی وارد شده معتبر نیست.']];
        }

        if ($fromGreg < now()->toDateString()) {
            return ['ok' => false, 'from' => null, 'to' => null, 'errors' => ['date_from' => 'تاریخ شروع نباید در گذشته باشد.']];
        }

        if ($toGreg < $fromGreg) {
            return ['ok' => false, 'from' => null, 'to' => null, 'errors' => ['date_to' => 'تاریخ پایان باید بعد از تاریخ شروع باشد.']];
        }

        return ['ok' => true, 'from' => $fromGreg, 'to' => $toGreg];
    }

    /**
     * Upcoming active bookings grouped by physical room id (for UI hints).
     *
     * @return array<int, array<int, array{check_in:string, check_out:string, tracking_code:?string}>>
     */
    public function upcomingBookingsByRoom(RoomType $roomType): array
    {
        $from = now()->toDateString();

        $lines = BookingRoom::query()
            ->where('room_type_id', $roomType->id)
            ->whereNotNull('room_id')
            ->whereHas('booking', fn ($q) => $q
                ->whereIn('status', ['confirmed', 'pending'])
                ->where('check_out', '>', $from))
            ->with(['booking:id,check_in,check_out,tracking_code', 'room:id,name'])
            ->get();

        $byRoom = [];
        foreach ($lines as $line) {
            $booking = $line->booking;
            if (!$booking) {
                continue;
            }

            $roomId = (int) $line->room_id;
            $byRoom[$roomId][] = [
                'check_in'      => $booking->check_in->format('Y-m-d'),
                'check_out'     => $booking->check_out->format('Y-m-d'),
                'tracking_code' => $booking->tracking_code,
            ];
        }

        return $byRoom;
    }

    /**
     * @param  array<int>  $roomIds
     * @return array<int, array{room_id:int, room_name:string, check_in:string, check_out:string, tracking_code:?string}>
     */
    public function findBookingConflicts(RoomType $roomType, string $fromGreg, string $toGreg, array $roomIds): array
    {
        if ($roomIds === []) {
            return [];
        }

        $roomNames = $roomType->rooms()->whereIn('id', $roomIds)->pluck('name', 'id');
        $conflicts = [];

        foreach ($roomIds as $roomId) {
            $line = $this->firstConflictingBookingLine($roomType, $roomId, $fromGreg, $toGreg);
            if (!$line?->booking) {
                continue;
            }

            $booking = $line->booking;
            $conflicts[] = [
                'room_id'       => $roomId,
                'room_name'     => (string) ($roomNames[$roomId] ?? 'اتاق'),
                'check_in'      => $booking->check_in->format('Y-m-d'),
                'check_out'     => $booking->check_out->format('Y-m-d'),
                'tracking_code' => $booking->tracking_code,
            ];
        }

        return $conflicts;
    }

    private function firstConflictingBookingLine(RoomType $roomType, int $roomId, string $fromGreg, string $toGreg): ?BookingRoom
    {
        $cursor = new \DateTime($fromGreg);
        $end = new \DateTime($toGreg);

        while ($cursor <= $end) {
            $dateStr = $cursor->format('Y-m-d');

            $line = BookingRoom::query()
                ->where('room_type_id', $roomType->id)
                ->where('room_id', $roomId)
                ->whereHas('booking', function ($q) use ($dateStr) {
                    $q->whereIn('status', ['confirmed', 'pending'])
                        ->whereDate('check_in', '<=', $dateStr)
                        ->whereDate('check_out', '>', $dateStr);
                })
                ->with('booking:id,check_in,check_out,tracking_code')
                ->first();

            if ($line) {
                return $line;
            }

            $cursor->modify('+1 day');
        }

        return null;
    }

    /**
     * @param  array<int>  $roomIds
     * @return array<string, string>|null
     */
    public function validateNoBookingConflicts(RoomType $roomType, string $fromGreg, string $toGreg, array $roomIds): ?array
    {
        $conflicts = $this->findBookingConflicts($roomType, $fromGreg, $toGreg, $roomIds);
        if ($conflicts === []) {
            return null;
        }

        $lines = array_map(function (array $conflict) {
            $checkInJ = Jalalian::fromCarbon(Carbon::parse($conflict['check_in']))->format('Y/m/d');
            $checkOutJ = Jalalian::fromCarbon(Carbon::parse($conflict['check_out']))->format('Y/m/d');
            $code = $conflict['tracking_code'] ? ' (کد: ' . $conflict['tracking_code'] . ')' : '';

            return '«' . $conflict['room_name'] . '» در بازه رزرو ' . $checkInJ . ' تا ' . $checkOutJ . $code;
        }, $conflicts);

        return [
            'room_ids' => 'اتاق‌های زیر در این بازه رزرو فعال دارند و قابل مسدودسازی نیستند: ' . implode('؛ ', $lines) . '.',
        ];
    }

    /**
     * @param  array<int>  $roomIds
     * @return array{conflicts: array<int, array{room_id:int, room_name:string, check_in:string, check_out:string, tracking_code:?string}>, unavailable_room_ids: array<int>}
     */
    public function previewConflicts(RoomType $roomType, string $dateFrom, string $dateTo, array $roomIds = []): array
    {
        $range = $this->parseJalaliRange($dateFrom, $dateTo);
        if (!$range['ok']) {
            return [
                'conflicts'             => [],
                'unavailable_room_ids'  => [],
                'errors'                => $range['errors'] ?? [],
            ];
        }

        $validRoomIds = $roomIds !== []
            ? $roomIds
            : $roomType->rooms()->pluck('id')->map(fn ($id) => (int) $id)->all();

        $conflicts = $this->findBookingConflicts($roomType, $range['from'], $range['to'], $validRoomIds);

        return [
            'conflicts'            => $conflicts,
            'unavailable_room_ids' => array_values(array_unique(array_column($conflicts, 'room_id'))),
            'from'                 => $range['from'],
            'to'                   => $range['to'],
        ];
    }

    /**
     * @param  array<int>  $roomIds
     */
    public function store(RoomType $roomType, string $fromGreg, string $toGreg, array $roomIds, ?string $reason): int
    {
        $conflictErrors = $this->validateNoBookingConflicts($roomType, $fromGreg, $toGreg, $roomIds);
        if ($conflictErrors !== null) {
            throw \Illuminate\Validation\ValidationException::withMessages($conflictErrors);
        }

        $created = 0;
        $from    = new \DateTime($fromGreg);
        $to      = new \DateTime($toGreg);
        $cursor  = clone $from;

        while ($cursor <= $to) {
            $dateStr = $cursor->format('Y-m-d');
            foreach ($roomIds as $roomId) {
                RoomTypeBlockedDate::updateOrCreate(
                    [
                        'room_type_id' => $roomType->id,
                        'room_id'      => $roomId,
                        'date'         => $dateStr,
                    ],
                    ['reason' => $reason]
                );
                $created++;
            }
            $cursor->modify('+1 day');
        }

        return $created;
    }

    public function destroyForRoomOnDate(RoomType $roomType, int $roomId, string $dateGreg): bool
    {
        return RoomTypeBlockedDate::query()
            ->where('room_type_id', $roomType->id)
            ->where('room_id', $roomId)
            ->whereDate('date', $dateGreg)
            ->delete() > 0;
    }
}
