<?php

namespace App\Models;

use App\Support\RoomTypePriceResolver;
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

    public function weeklyPriceRules()
    {
        return $this->hasMany(RoomTypeWeeklyPriceRule::class)->orderBy('weekday');
    }

    public function rooms()
    {
        return $this->hasMany(Room::class)->orderBy('sort_order')->orderBy('id');
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

        $endExcl = (clone $end)->modify('-1 day')->format('Y-m-d');

        // Room consumption from multi-room lines and legacy single-room bookings.
        $bookingRoomLines = BookingRoom::query()
            ->where('room_type_id', $this->id)
            ->whereHas('booking', function ($q) use ($from, $to) {
                $q->whereIn('status', ['confirmed', 'pending'])
                    ->where('check_in', '<', $to)
                    ->where('check_out', '>', $from);
            })
            ->with('booking:id,check_in,check_out')
            ->get(['booking_id', 'rooms_consumed']);

        $legacyBookings = $this->bookings()
            ->whereIn('status', ['confirmed', 'pending'])
            ->where('check_in', '<', $to)
            ->where('check_out', '>', $from)
            ->whereDoesntHave('bookingRooms')
            ->get(['id', 'check_in', 'check_out', 'rooms_consumed']);

        // Per-day blocked rooms (legacy rows with room_id=null block all rooms)
        $blockedIndex = $this->blockedDatesIndex($from, $to);

        // Daily capacity overrides: date → override object
        $overrides = $this->dailyOverrides()
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $endExcl)
            ->get()
            ->keyBy(fn (RoomTypeDailyOverride $o) => $o->date->toDateString())
            ->all();

        // Permanent weekly price rules: ISO weekday → rule
        $weeklyRules = $this->weeklyPriceRules()
            ->get(['weekday', 'custom_price', 'discount_percentage', 'price_label'])
            ->keyBy('weekday')
            ->all();

        // Default (cheapest) rate price for reference
        $defaultRate = $this->rates()->orderBy('price_per_night')->first();
        $defaultPrice = $defaultRate ? (int) $defaultRate->price_per_night : 0;

        $map    = [];
        $cursor = clone $start;
        while ($cursor < $end) {
            $dateStr = $cursor->format('Y-m-d');
            $booked = 0;
            foreach ($bookingRoomLines as $line) {
                $b = $line->booking;
                if (!$b) {
                    continue;
                }
                $ci = $b->check_in->format('Y-m-d');
                $co = $b->check_out->format('Y-m-d');
                if ($ci <= $dateStr && $co > $dateStr) {
                    $booked += (int) ($line->rooms_consumed ?? 1);
                }
            }
            foreach ($legacyBookings as $b) {
                $ci = $b->check_in->format('Y-m-d');
                $co = $b->check_out->format('Y-m-d');
                if ($ci <= $dateStr && $co > $dateStr) {
                    $booked += (int) ($b->rooms_consumed ?? 1);
                }
            }
            $ovr = $overrides[$dateStr] ?? null;
            // Daily override caps the effective total for that day
            $effectiveTotal = $ovr !== null
                ? min((int) $ovr->available_count, $baseTotal)
                : $baseTotal;

            // Compute price — explicit daily price beats permanent weekly rule
            $weeklyRule = $weeklyRules[(int) $cursor->format('N')] ?? null;
            $dailyHasPrice = $ovr !== null && (
                ($ovr->custom_price !== null && $ovr->custom_price > 0)
                || ($ovr->discount_percentage !== null && $ovr->discount_percentage !== 0)
                || filled($ovr->price_label)
            );

            if ($dailyHasPrice) {
                $dayCustomPrice = $ovr->custom_price;
                $dayDiscount    = $ovr->discount_percentage;
                $dayLabel       = $ovr->price_label;
                $priceSource    = 'daily';
            } elseif ($weeklyRule !== null) {
                $dayCustomPrice = $weeklyRule->custom_price;
                $dayDiscount    = $weeklyRule->discount_percentage;
                $dayLabel       = $weeklyRule->price_label;
                $priceSource    = 'weekly';
            } else {
                $dayCustomPrice = null;
                $dayDiscount    = null;
                $dayLabel       = null;
                $priceSource    = 'default';
            }
            $dayEffectivePrice = $defaultPrice > 0
                ? RoomTypePriceResolver::effectivePrice($defaultPrice, $dayCustomPrice, $dayDiscount)
                : null;

            $blockedRooms = $this->blockedRoomCountForDate($dateStr, $effectiveTotal, $blockedIndex);
            $availableRooms = max(0, $effectiveTotal - $booked - $blockedRooms);

            $map[$dateStr] = [
                'total'           => $effectiveTotal,
                'booked'          => $booked,
                'blocked_rooms'   => $blockedRooms,
                'available_rooms' => $availableRooms,
                'is_blocked'      => $blockedRooms >= $effectiveTotal && $effectiveTotal > 0,
                'is_partially_blocked' => $blockedRooms > 0 && $availableRooms > 0,
                'has_override'    => $ovr !== null,
                'override_count'  => $ovr ? (int) $ovr->available_count : null,
                // Price fields
                'default_price'        => $defaultPrice,
                'custom_price'         => $dayCustomPrice,
                'discount_percentage'  => $dayDiscount,
                'price_label'          => $dayLabel,
                'effective_price'      => $dayEffectivePrice,
                'has_price_override'   => RoomTypePriceResolver::hasPriceAdjustment($dayCustomPrice, $dayDiscount),
                'price_source'         => $priceSource,
                'has_weekly_rule'      => !$dailyHasPrice && $weeklyRule !== null,
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

    /**
     * @return array<string, array{all?:bool, room_ids?:array<int, true>}>
     */
    public function blockedDatesIndex(string $from, string $to): array
    {
        $endExcl = (clone new \DateTime($to))->modify('-1 day')->format('Y-m-d');

        $rows = $this->blockedDates()
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $endExcl)
            ->get(['date', 'room_id']);

        $index = [];
        foreach ($rows as $row) {
            $dateStr = $row->date->format('Y-m-d');
            if ($row->room_id === null) {
                $index[$dateStr]['all'] = true;
            } else {
                $index[$dateStr]['room_ids'][(int) $row->room_id] = true;
            }
        }

        return $index;
    }

    /**
     * @param  array<string, array{all?:bool, room_ids?:array<int, true>}>  $blockedIndex
     */
    public function blockedRoomCountForDate(string $dateStr, int $effectiveTotal, array $blockedIndex): int
    {
        $day = $blockedIndex[$dateStr] ?? [];
        if (!empty($day['all'])) {
            return $effectiveTotal;
        }

        return count($day['room_ids'] ?? []);
    }

    /**
     * @param  array<string, array{all?:bool, room_ids?:array<int, true>}>  $blockedIndex
     */
    public function isRoomBlockedOnDate(int $roomId, string $dateStr, int $effectiveTotal, array $blockedIndex): bool
    {
        $day = $blockedIndex[$dateStr] ?? [];
        if (!empty($day['all'])) {
            return true;
        }

        return isset($day['room_ids'][$roomId]);
    }

    public function blockReasonForRoomOnDate(int $roomId, string $dateStr): ?string
    {
        return $this->blockedDates()
            ->whereDate('date', $dateStr)
            ->where(function ($q) use ($roomId) {
                $q->whereNull('room_id')->orWhere('room_id', $roomId);
            })
            ->orderByRaw('room_id is null desc')
            ->value('reason');
    }
}
