<?php

namespace App\Services;

use App\Models\Accommodation;
use App\Models\Booking;
use Illuminate\Support\Collection;
use Morilog\Jalali\Jalalian;

class OccupancyCalendarService
{
    /**
     * @param  Collection<int, int>  $accommodationIds
     */
    public function build(Collection $accommodationIds, int $year, int $month): array
    {
        $jalali = Jalalian::fromFormat('Y/m/d', sprintf('%d/%02d/01', $year, $month));
        $now    = Jalalian::now();

        if ($accommodationIds->isEmpty()) {
            return [
                'month_label'      => $jalali->format('Y F'),
                'year'             => $year,
                'month'            => $month,
                'is_current_month' => $year === $now->getYear() && $month === $now->getMonth(),
                'cells'            => [],
                'total_rooms'      => 0,
            ];
        }

        $daysInMonth = $jalali->getMonthDays();

        $firstGreg = Jalalian::fromFormat('Y/m/d', sprintf('%d/%02d/01', $year, $month))->toCarbon()->startOfDay();
        $lastGreg  = Jalalian::fromFormat('Y/m/d', sprintf('%d/%02d/%02d', $year, $month, $daysInMonth))->toCarbon()->endOfDay();
        $rangeFrom = $firstGreg->format('Y-m-d');
        $rangeTo   = $lastGreg->copy()->addDay()->format('Y-m-d');

        $bookings = Booking::whereIn('accommodation_id', $accommodationIds->all())
            ->whereIn('status', ['confirmed', 'pending'])
            ->where('check_in', '<', $rangeTo)
            ->where('check_out', '>', $rangeFrom)
            ->with('accommodation:id,name', 'roomType:id,name', 'user:id,name,mobile')
            ->get(['id', 'accommodation_id', 'room_type_id', 'check_in', 'check_out', 'rooms_consumed', 'guest_contact_name', 'status', 'tracking_code', 'user_id']);

        $totalRooms = (int) Accommodation::whereIn('id', $accommodationIds->all())->sum('rooms');

        $firstDow = (int) $firstGreg->dayOfWeek;
        $offset   = ($firstDow + 1) % 7;

        $cells = [];
        for ($i = 0; $i < $offset; $i++) {
            $cells[] = null;
        }

        $todayStr = today()->format('Y-m-d');

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $greg    = Jalalian::fromFormat('Y/m/d', sprintf('%d/%02d/%02d', $year, $month, $d))->toCarbon();
            $dateStr = $greg->format('Y-m-d');
            $staying = $bookings->filter(function ($b) use ($dateStr) {
                return $b->check_in->format('Y-m-d') <= $dateStr
                    && $b->check_out->format('Y-m-d') > $dateStr;
            });
            $roomsUsed    = (int) $staying->sum(fn ($b) => (int) ($b->rooms_consumed ?? 1));
            $bookingCount = $staying->count();

            $state = 'free';
            if ($totalRooms > 0 && $roomsUsed >= $totalRooms) {
                $state = 'full';
            } elseif ($roomsUsed > 0) {
                $state = 'partial';
            }

            $cells[] = [
                'day'           => $d,
                'greg'          => $dateStr,
                'jalali'        => sprintf('%d/%02d/%02d', $year, $month, $d),
                'is_past'       => $dateStr < $todayStr,
                'is_today'      => $dateStr === $todayStr,
                'booking_count' => $bookingCount,
                'rooms_used'    => $roomsUsed,
                'total_rooms'   => $totalRooms,
                'state'         => $state,
                'bookings'      => $staying->map(fn ($b) => [
                    'id'           => $b->id,
                    'code'         => $b->tracking_code,
                    'guest'        => $b->bookerName(),
                    'acc'          => $b->accommodation->name ?? '—',
                    'room'         => $b->roomType->name ?? '—',
                    'rooms'        => (int) ($b->rooms_consumed ?? 1),
                    'status'       => $b->status,
                    'status_label' => $b->statusLabel(),
                    'check_in'     => Jalalian::fromCarbon($b->check_in)->format('Y/m/d'),
                    'check_out'    => Jalalian::fromCarbon($b->check_out)->format('Y/m/d'),
                ])->values()->all(),
            ];
        }

        return [
            'month_label'      => $jalali->format('Y F'),
            'year'             => $year,
            'month'            => $month,
            'is_current_month' => $year === $now->getYear() && $month === $now->getMonth(),
            'cells'            => $cells,
            'total_rooms'      => $totalRooms,
        ];
    }
}
