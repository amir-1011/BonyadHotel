<?php

namespace App\Services;

use App\Models\Hall;
use App\Models\Program;
use Carbon\Carbon;

class HallAvailabilityService
{
    public function isAvailable(
        Hall $hall,
        string $date,
        string $startTime,
        string $endTime,
        ?int $exceptProgramId = null,
    ): bool {
        return $this->overlappingQuery($hall, $date, $startTime, $endTime, $exceptProgramId)->doesntExist();
    }

    public function assertAvailable(
        Hall $hall,
        string $date,
        string $startTime,
        string $endTime,
        ?int $exceptProgramId = null,
    ): void {
        if ($this->isAvailable($hall, $date, $startTime, $endTime, $exceptProgramId)) {
            return;
        }

        throw new \RuntimeException('این سالن در تاریخ و سانس انتخاب‌شده قبلاً رزرو شده است.');
    }

    /**
     * @return array<int, true>
     */
    public function unavailableHallIds(
        int $accommodationId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $exceptProgramId = null,
    ): array {
        $ids = [];

        $halls = Hall::query()
            ->where('accommodation_id', $accommodationId)
            ->active()
            ->get(['id']);

        foreach ($halls as $hall) {
            if (! $this->isAvailable($hall, $date, $startTime, $endTime, $exceptProgramId)) {
                $ids[(int) $hall->id] = true;
            }
        }

        return $ids;
    }

    private function overlappingQuery(
        Hall $hall,
        string $date,
        string $startTime,
        string $endTime,
        ?int $exceptProgramId = null,
    ) {
        $start = $this->normalizeTime($startTime);
        $end = $this->normalizeTime($endTime);
        $day = Carbon::parse($date)->toDateString();

        $query = Program::query()
            ->where('hall_id', $hall->id)
            ->where('status', '!=', Program::STATUS_CANCELLED)
            ->whereHas('booking', function ($booking) use ($day) {
                $booking->whereDate('check_in', $day)
                    ->where('status', '!=', 'cancelled');
            })
            ->whereNotNull('hall_start_time')
            ->whereNotNull('hall_end_time')
            ->where('hall_start_time', '<', $end)
            ->where('hall_end_time', '>', $start);

        if ($exceptProgramId) {
            $query->whereKeyNot($exceptProgramId);
        }

        return $query;
    }

    public function normalizeTime(string $time): string
    {
        $time = trim(str_replace('٫', ':', $time));
        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $time . ':00';
        }

        return Carbon::parse($time)->format('H:i:s');
    }
}
