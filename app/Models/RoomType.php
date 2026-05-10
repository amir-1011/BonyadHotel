<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomType extends Model
{
    protected $fillable = [
        'accommodation_id', 'name', 'description', 'bed_type',
        'capacity', 'size_sqm', 'smoking', 'has_private_bathroom',
        'images', 'amenities', 'room_count', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'images'               => 'array',
            'amenities'            => 'array',
            'smoking'              => 'boolean',
            'has_private_bathroom' => 'boolean',
            'is_active'            => 'boolean',
            'size_sqm'             => 'float',
        ];
    }

    public function accommodation()
    {
        return $this->belongsTo(Accommodation::class);
    }

    public function rates()
    {
        return $this->hasMany(RoomRate::class)->orderBy('price_per_night');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function blockedDates()
    {
        return $this->hasMany(RoomTypeBlockedDate::class);
    }

    public function dailyOverrides()
    {
        return $this->hasMany(RoomTypeDailyOverride::class);
    }

    /** First image or null */
    public function coverImage(): ?string
    {
        return collect($this->images ?? [])->filter()->first();
    }

    /**
     * Get availability map for a date range.
     * Returns an array keyed by date string (Y-m-d) with:
     *   available_rooms => int (rooms not yet booked that day)
     *   is_blocked      => bool (manually blocked by host)
     */
    public function availabilityMap(string $from, string $to): array
    {
        $start     = new \DateTime($from);
        $end       = new \DateTime($to);
        $baseTotal = (int) $this->room_count;

        // Active bookings overlapping the range
        $bookings = $this->bookings()
            ->whereIn('status', ['confirmed', 'pending'])
            ->where('check_in', '<', $to)
            ->where('check_out', '>', $from)
            ->get(['check_in', 'check_out']);

        $endExcl = (clone $end)->modify('-1 day')->format('Y-m-d');

        // Manually blocked dates
        $blocked = $this->blockedDates()
            ->whereBetween('date', [$from, $endExcl])
            ->pluck('date')
            ->map(fn($d) => $d->format('Y-m-d'))
            ->flip()
            ->all();

        // Daily capacity overrides: date → available_count
        $overrides = $this->dailyOverrides()
            ->whereBetween('date', [$from, $endExcl])
            ->get(['date', 'available_count'])
            ->keyBy(fn($o) => $o->date->format('Y-m-d'))
            ->map(fn($o) => (int) $o->available_count)
            ->all();

        $map    = [];
        $cursor = clone $start;
        while ($cursor < $end) {
            $dateStr = $cursor->format('Y-m-d');
            $booked  = 0;
            foreach ($bookings as $b) {
                $ci = $b->check_in->format('Y-m-d');
                $co = $b->check_out->format('Y-m-d');
                if ($ci <= $dateStr && $co > $dateStr) {
                    $booked++;
                }
            }
            // Daily override caps the effective total for that day
            $effectiveTotal = array_key_exists($dateStr, $overrides)
                ? min($overrides[$dateStr], $baseTotal)
                : $baseTotal;

            $map[$dateStr] = [
                'total'           => $effectiveTotal,
                'booked'          => $booked,
                'available_rooms' => max(0, $effectiveTotal - $booked),
                'is_blocked'      => isset($blocked[$dateStr]),
                'has_override'    => array_key_exists($dateStr, $overrides),
                'override_count'  => $overrides[$dateStr] ?? null,
            ];
            $cursor->modify('+1 day');
        }

        return $map;
    }

    /**
     * Check if at least `$rooms` rooms are free for the entire date range
     * (check_in inclusive, check_out exclusive – same as hotel convention).
     */
    public function isAvailable(string $checkIn, string $checkOut, int $rooms = 1): bool
    {
        $map = $this->availabilityMap($checkIn, $checkOut);
        foreach ($map as $day) {
            if ($day['is_blocked'] || $day['available_rooms'] < $rooms) {
                return false;
            }
        }
        return true;
    }
}
