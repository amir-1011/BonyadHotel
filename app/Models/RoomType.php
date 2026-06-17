<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomType extends Model
{
    protected $fillable = [
        'accommodation_id', 'name', 'description', 'bed_type',
        'capacity', 'extra_capacity', 'extra_capacity_price',
        'size_sqm', 'smoking', 'has_private_bathroom',
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
            ->get(['check_in', 'check_out', 'rooms_consumed']);

        $endExcl = (clone $end)->modify('-1 day')->format('Y-m-d');

        // Manually blocked dates
        $blocked = $this->blockedDates()
            ->whereBetween('date', [$from, $endExcl])
            ->pluck('date')
            ->map(fn($d) => $d->format('Y-m-d'))
            ->flip()
            ->all();

        // Daily capacity overrides: date → override object
        $overrides = $this->dailyOverrides()
            ->whereBetween('date', [$from, $endExcl])
            ->get(['date', 'available_count', 'custom_price', 'discount_percentage', 'price_label'])
            ->keyBy(fn($o) => $o->date->format('Y-m-d'))
            ->all();

        // Default (cheapest) rate price for reference
        $defaultRate = $this->rates()->orderBy('price_per_night')->first();
        $defaultPrice = $defaultRate ? (int) $defaultRate->price_per_night : 0;

        $map    = [];
        $cursor = clone $start;
        while ($cursor < $end) {
            $dateStr = $cursor->format('Y-m-d');
            $booked  = 0;
            foreach ($bookings as $b) {
                $ci = $b->check_in->format('Y-m-d');
                $co = $b->check_out->format('Y-m-d');
                if ($ci <= $dateStr && $co > $dateStr) {
                    // Each booking may consume multiple rooms (multi-room policy)
                    $booked += (int) ($b->rooms_consumed ?? 1);
                }
            }
            $ovr = $overrides[$dateStr] ?? null;
            // Daily override caps the effective total for that day
            $effectiveTotal = $ovr !== null
                ? min((int) $ovr->available_count, $baseTotal)
                : $baseTotal;

            // Compute price for this day
            $dayCustomPrice  = $ovr ? $ovr->custom_price : null;
            $dayDiscount     = $ovr ? $ovr->discount_percentage : null;
            $dayLabel        = $ovr ? $ovr->price_label : null;
            $dayEffectivePrice = null;
            if ($defaultPrice > 0) {
                $base = $dayCustomPrice ?? $defaultPrice;
                $dayEffectivePrice = ($dayDiscount > 0)
                    ? (int) round($base * (1 - $dayDiscount / 100))
                    : $base;
            }

            $map[$dateStr] = [
                'total'           => $effectiveTotal,
                'booked'          => $booked,
                'available_rooms' => max(0, $effectiveTotal - $booked),
                'is_blocked'      => isset($blocked[$dateStr]),
                'has_override'    => $ovr !== null,
                'override_count'  => $ovr ? (int) $ovr->available_count : null,
                // Price fields
                'default_price'        => $defaultPrice,
                'custom_price'         => $dayCustomPrice,
                'discount_percentage'  => $dayDiscount,
                'price_label'          => $dayLabel,
                'effective_price'      => $dayEffectivePrice,
                'has_price_override'   => $ovr && ($dayCustomPrice !== null || $dayDiscount > 0),
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
