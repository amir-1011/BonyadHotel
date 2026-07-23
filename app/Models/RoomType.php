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

    /** In-memory memoization for {@see blockedDatesIndex()}; not persisted. */
    private ?string $blockedDatesIndexCacheKey = null;

    /** @var array<string, array{all?:bool, room_ids?:array<int, true>}>|null */
    private ?array $blockedDatesIndexCache = null;

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

    /** Room group name (نام گروه اتاق). */
    public function groupLabel(): string
    {
        return $this->name ?? '—';
    }

    /** Selectable room category (نوع اتاق), not the room group name. */
    public function categoryLabel(): string
    {
        return filled($this->bed_type) ? $this->bed_type : '—';
    }

    public function categoryFilterLabel(bool $showAccommodation = false): string
    {
        $category = $this->categoryLabel();
        if ($category === '—') {
            return $this->groupLabel();
        }

        if ($showAccommodation && $this->relationLoaded('accommodation') && $this->accommodation) {
            return $this->accommodation->name . ' — ' . $category;
        }

        return $category;
    }

    /**
     * Nightly base price for availability/pricing maps.
     * Uses the selected rate when it belongs to this room type; otherwise the cheapest active rate.
     */
    public function nightlyBasePrice(?RoomRate $roomRate = null): int
    {
        if ($roomRate !== null && (int) $roomRate->room_type_id === (int) $this->id) {
            return (int) $roomRate->price_per_night;
        }

        $defaultRate = $this->rates()->orderBy('price_per_night')->first();

        return $defaultRate ? (int) $defaultRate->price_per_night : 0;
    }

    /**
     * Get availability map for a date range.
     * Returns an array keyed by date string (Y-m-d) with:
     *   available_rooms => int (rooms not yet booked that day)
     *   is_blocked      => bool (manually blocked by host)
     *
     * @param  RoomRate|null  $roomRate  When set, daily effective_price is based on this tariff.
     */
    public function availabilityMap(string $from, string $to, ?RoomRate $roomRate = null): array
    {
        $start     = new \DateTime($from);
        $end       = new \DateTime($to);
        $baseTotal = (int) $this->room_count;

        $this->loadMissing('rates');

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

        // Permanent weekly price rules: ISO weekday → rule (legacy room-type-wide)
        $weeklyRules = $this->weeklyPriceRules()
            ->get(['weekday', 'custom_price', 'discount_percentage', 'price_label'])
            ->keyBy('weekday')
            ->all();

        $rateIds = $this->rates()->pluck('id')->map(fn ($id) => (int) $id)->all();

        // Per-rate daily price overrides: date → rate_id → override
        $rateDailyByDate = [];
        if ($rateIds !== []) {
            $rateDailyRows = RoomRateDailyPriceOverride::query()
                ->whereIn('room_rate_id', $rateIds)
                ->whereDate('date', '>=', $from)
                ->whereDate('date', '<=', $endExcl)
                ->get();

            foreach ($rateDailyRows as $row) {
                $dateKey = $row->date->toDateString();
                $rateDailyByDate[$dateKey][(int) $row->room_rate_id] = $row;
            }
        }

        // Per-rate weekly rules: rate_id → weekday → rule
        $rateWeeklyByRate = [];
        if ($rateIds !== []) {
            $rateWeeklyRows = RoomRateWeeklyPriceRule::query()
                ->whereIn('room_rate_id', $rateIds)
                ->get(['room_rate_id', 'weekday', 'custom_price', 'discount_percentage', 'price_label']);

            foreach ($rateWeeklyRows as $row) {
                $rateWeeklyByRate[(int) $row->room_rate_id][(int) $row->weekday] = $row;
            }
        }

        // Cheapest rate (admin calendar reference) vs selected tariff base for pricing
        $defaultRate = $this->rates()->orderBy('price_per_night')->first();
        $defaultPrice = $defaultRate ? (int) $defaultRate->price_per_night : 0;
        $basePrice = $this->nightlyBasePrice($roomRate);

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

            // Compute price — per-rate daily > legacy daily > per-rate weekly > legacy weekly > default
            $weeklyRule = $weeklyRules[(int) $cursor->format('N')] ?? null;
            $selectedRateId = ($roomRate !== null && (int) $roomRate->room_type_id === (int) $this->id)
                ? (int) $roomRate->id
                : null;
            $rateDailyOvr = $selectedRateId !== null
                ? ($rateDailyByDate[$dateStr][$selectedRateId] ?? null)
                : null;
            $rateWeeklyRule = $selectedRateId !== null
                ? ($rateWeeklyByRate[$selectedRateId][(int) $cursor->format('N')] ?? null)
                : null;

            $dailyHasPrice = $ovr !== null && (
                ($ovr->custom_price !== null && $ovr->custom_price > 0)
                || ($ovr->discount_percentage !== null && $ovr->discount_percentage !== 0)
                || filled($ovr->price_label)
            );
            $rateDailyHasPrice = $rateDailyOvr !== null && $rateDailyOvr->hasPriceAdjustment();

            if ($rateDailyHasPrice) {
                $dayCustomPrice = $rateDailyOvr->custom_price;
                $dayDiscount    = $rateDailyOvr->discount_percentage;
                $dayLabel       = $rateDailyOvr->price_label;
                $priceSource    = 'rate_daily';
            } elseif ($dailyHasPrice) {
                $dayCustomPrice = $ovr->custom_price;
                $dayDiscount    = $ovr->discount_percentage;
                $dayLabel       = $ovr->price_label;
                $priceSource    = 'daily';
            } elseif ($rateWeeklyRule !== null && $rateWeeklyRule->hasPriceAdjustment()) {
                $dayCustomPrice = $rateWeeklyRule->custom_price;
                $dayDiscount    = $rateWeeklyRule->discount_percentage;
                $dayLabel       = $rateWeeklyRule->price_label;
                $priceSource    = 'rate_weekly';
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
            $dayEffectivePrice = $basePrice > 0
                ? RoomTypePriceResolver::effectivePrice($basePrice, $dayCustomPrice, $dayDiscount)
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
                'base_price'           => $basePrice,
                'custom_price'         => $dayCustomPrice,
                'discount_percentage'  => $dayDiscount,
                'price_label'          => $dayLabel,
                'effective_price'      => $dayEffectivePrice,
                'has_price_override'   => RoomTypePriceResolver::hasPriceAdjustment($dayCustomPrice, $dayDiscount),
                'price_source'         => $priceSource,
                'has_weekly_rule'      => in_array($priceSource, ['weekly', 'rate_weekly'], true),
                'rate_price_overrides' => $this->buildRatePriceOverridesForDate(
                    $dateStr,
                    (int) $cursor->format('N'),
                    $ovr,
                    $weeklyRule,
                    $rateDailyByDate[$dateStr] ?? [],
                    $rateWeeklyByRate,
                    $dailyHasPrice,
                ),
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
        // Memoize per instance/range: this is frequently called twice for the same
        // [from, to) window within a single request (once internally by availabilityMap(),
        // once by callers needing the raw index), so avoid issuing the query twice.
        $cacheKey = $from.'|'.$to;
        if ($this->blockedDatesIndexCache !== null && $this->blockedDatesIndexCacheKey === $cacheKey) {
            return $this->blockedDatesIndexCache;
        }

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

        $this->blockedDatesIndexCacheKey = $cacheKey;
        $this->blockedDatesIndexCache = $index;

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

    /**
     * @param  array<int, RoomRateDailyPriceOverride>  $rateDailyForDate
     * @param  array<int, array<int, RoomRateWeeklyPriceRule>>  $rateWeeklyByRate
     * @return array<int, array{discount_percentage: ?int, custom_price: ?int, price_label: ?string, effective_price: ?int}>
     */
    private function buildRatePriceOverridesForDate(
        string $dateStr,
        int $weekday,
        ?RoomTypeDailyOverride $ovr,
        ?RoomTypeWeeklyPriceRule $weeklyRule,
        array $rateDailyForDate,
        array $rateWeeklyByRate,
        bool $legacyDailyHasPrice,
    ): array {
        $result = [];

        foreach ($this->rates as $rate) {
            $rateId = (int) $rate->id;
            $basePrice = (int) $rate->price_per_night;
            $rateDaily = $rateDailyForDate[$rateId] ?? null;
            $rateWeekly = $rateWeeklyByRate[$rateId][$weekday] ?? null;

            if ($rateDaily !== null && $rateDaily->hasPriceAdjustment()) {
                $custom = $rateDaily->custom_price;
                $disc = $rateDaily->discount_percentage;
                $label = $rateDaily->price_label;
            } elseif ($legacyDailyHasPrice && $ovr !== null) {
                $custom = $ovr->custom_price;
                $disc = $ovr->discount_percentage;
                $label = $ovr->price_label;
            } elseif ($rateWeekly !== null && $rateWeekly->hasPriceAdjustment()) {
                $custom = $rateWeekly->custom_price;
                $disc = $rateWeekly->discount_percentage;
                $label = $rateWeekly->price_label;
            } elseif ($weeklyRule !== null) {
                $custom = $weeklyRule->custom_price;
                $disc = $weeklyRule->discount_percentage;
                $label = $weeklyRule->price_label;
            } else {
                continue;
            }

            $result[$rateId] = [
                'rate_name'           => $rate->name,
                'discount_percentage' => $disc,
                'custom_price'        => $custom,
                'price_label'         => $label,
                'effective_price'     => $basePrice > 0
                    ? RoomTypePriceResolver::effectivePrice($basePrice, $custom, $disc)
                    : null,
            ];
        }

        return $result;
    }
}
