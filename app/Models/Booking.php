<?php

namespace App\Models;

use App\Support\VeteranGroups;
use App\Services\VeteranPolicyService;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'user_id', 'created_by', 'accommodation_id', 'room_type_id', 'room_rate_id',
        'check_in', 'check_out',
        'guests', 'children_under_6', 'guest_contact_name', 'guest_contact_mobile',
        'rooms_consumed', 'extra_guests', 'extra_guests_price', 'bill_full_rooms',
        'nights', 'base_price', 'services_subtotal', 'discount_percentage',
        'veteran_type_applied', 'secondary_veteran_type_applied', 'veteran_accommodation_group_usage', 'discount_amount', 'total_price',
        'status', 'tracking_code', 'booking_source', 'payment_method',
        'notes', 'guest_discount_snapshot', 'form_file_path',
    ];

    protected function casts(): array
    {
        return [
            'check_in'        => 'date',
            'check_out'       => 'date',
            'bill_full_rooms' => 'boolean',
            'guest_discount_snapshot' => 'array',
            'veteran_accommodation_group_usage' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function accommodation()
    {
        return $this->belongsTo(Accommodation::class);
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function roomRate()
    {
        return $this->belongsTo(RoomRate::class);
    }

    public function services()
    {
        return $this->hasMany(BookingService::class)->orderBy('sort_order');
    }

    public function guestDetails()
    {
        return $this->hasMany(BookingGuestDetail::class)->orderBy('sort_order');
    }

    public function bookingRooms()
    {
        return $this->hasMany(BookingRoom::class)->orderBy('sort_order');
    }

    public function commissionEntries()
    {
        return $this->hasMany(PlatformCommissionEntry::class);
    }

    public function isManual(): bool
    {
        return $this->booking_source === 'manual';
    }

    public function bookerName(): string
    {
        return $this->guest_contact_name
            ?? $this->user?->name
            ?? $this->user?->mobile
            ?? '—';
    }

    public function bookerMobile(): string
    {
        return $this->guest_contact_mobile
            ?? $this->user?->mobile
            ?? '—';
    }

    public function veteranLabelApplied(): string
    {
        return VeteranGroups::labelsForTypes(
            $this->veteranTypesApplied(),
            $this->accommodation_id,
        );
    }

    /**
     * @return array<int, string>
     */
    public function veteranTypesApplied(): array
    {
        return app(VeteranPolicyService::class)
            ->forAccommodation($this->accommodation_id)
            ->normalizeVeteranTypes(
                $this->veteran_type_applied,
                $this->secondary_veteran_type_applied,
            );
    }

    public function paymentMethodLabel(): string
    {
        return match ($this->payment_method) {
            'cash'          => 'نقدی',
            'card_terminal' => 'کارتخوان',
            default         => '—',
        };
    }

    public function roomSubtotal(): int
    {
        return max(0, $this->base_price - $this->services_subtotal - $this->extra_guests_price);
    }

    public function servicesDiscountTotal(): int
    {
        return (int) $this->services->sum('discount_amount');
    }

    public function accommodationDiscountTotal(): int
    {
        return max(0, (int) $this->discount_amount - $this->servicesDiscountTotal());
    }

    public function billingGuests(): int
    {
        return max(1, (int) $this->guests - (int) $this->extra_guests);
    }

    public function bookerNationalId(): ?string
    {
        $fromGuest = $this->guestDetails->first()?->national_id;

        return filled($fromGuest) ? $fromGuest : $this->user?->national_id;
    }

    public function bookingSourceLabel(): string
    {
        return match ($this->booking_source) {
            'manual'  => 'رزرو دستی',
            'online'  => 'آنلاین',
            default   => $this->booking_source ?: '—',
        };
    }

    public function veteranDiscountLabel(): string
    {
        if ($this->veteran_type_applied) {
            return $this->veteranLabelApplied();
        }

        return $this->user?->veteranLabel() ?: 'عادی (بدون تخفیف ایثارگری)';
    }

    public function hasMultiRoomLines(): bool
    {
        return $this->bookingRooms->count() > 0;
    }

    public function physicalRoomNamesSummary(): string
    {
        $lines = $this->relationLoaded('bookingRooms')
            ? $this->bookingRooms
            : ($this->exists ? $this->bookingRooms()->with('room')->get() : collect());

        if ($lines->isNotEmpty()) {
            $names = $lines->map(fn (BookingRoom $line) => $line->room?->name)->filter()->values();
            if ($names->isNotEmpty()) {
                return $names->join('، ');
            }
        }

        return $this->roomType?->name ?? '—';
    }

    public function roomLinesSummary(): string
    {
        $lines = $this->relationLoaded('bookingRooms')
            ? $this->bookingRooms
            : ($this->exists ? $this->bookingRooms()->with('room')->get() : collect());

        if ($lines->isEmpty()) {
            $label = $this->physicalRoomNamesSummary();

            return $this->roomType && $label !== ($this->roomType->name ?? '')
                ? $this->roomType->name . ' · ' . $label
                : ($label ?: '—');
        }

        $count = $lines->count();
        $guests = $lines->sum('guests');
        $physical = $lines
            ->map(fn (BookingRoom $line) => $line->physicalRoomLabel())
            ->filter(fn ($n) => $n !== '—')
            ->values();

        $base = $count . ' اتاق · ' . $guests . ' نفر';

        return $physical->isNotEmpty()
            ? $base . ' (' . $physical->join('، ') . ')'
            : $base;
    }

    /**
     * Per-guest discount flags saved at booking time (independent of optional guest contact fields).
     *
     * @return array<int, array<string, mixed>>
     */
    public function guestDiscountSlots(): array
    {
        $snapshot = $this->guest_discount_snapshot;
        if (!is_array($snapshot) || $snapshot === []) {
            return [];
        }

        return collect($snapshot)
            ->filter(fn ($slot) => is_array($slot))
            ->sortBy(fn ($slot) => (int) ($slot['sort_order'] ?? 0))
            ->values()
            ->all();
    }

    /**
     * Slots with manual discount from snapshot and/or persisted guest rows.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function manualDiscountSlotsForDisplay()
    {
        $byIndex = [];

        foreach ($this->guestDiscountSlots() as $slot) {
            $index = (int) ($slot['sort_order'] ?? 0);
            if ((int) ($slot['manual_discount_percentage'] ?? 0) <= 0) {
                continue;
            }
            $byIndex[$index] = (object) [
                'sort_order'                 => $index,
                'full_name'                  => $slot['label'] ?? ('مهمان ' . ($index + 1)),
                'relation'                   => null,
                'manual_discount_percentage' => (int) $slot['manual_discount_percentage'],
                'manual_discount_reason'     => $slot['manual_discount_reason'] ?? null,
                'from_snapshot'              => true,
            ];
        }

        foreach ($this->guestDetails as $guest) {
            if ((int) ($guest->manual_discount_percentage ?? 0) <= 0) {
                continue;
            }
            $byIndex[$guest->sort_order] = (object) [
                'sort_order'                 => $guest->sort_order,
                'full_name'                  => $guest->full_name,
                'relation'                   => $guest->relation,
                'manual_discount_percentage' => (int) $guest->manual_discount_percentage,
                'manual_discount_reason'     => $guest->manual_discount_reason,
                'from_snapshot'              => false,
            ];
        }

        return collect($byIndex)->sortKeys()->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function excludedDiscountSlotsForDisplay()
    {
        $byIndex = [];

        foreach ($this->guestDiscountSlots() as $slot) {
            if (empty($slot['excluded_from_veteran_discount'])) {
                continue;
            }
            $index = (int) ($slot['sort_order'] ?? 0);
            $byIndex[$index] = (object) [
                'sort_order'                 => $index,
                'full_name'                  => $slot['label'] ?? ('مهمان ' . ($index + 1)),
                'manual_discount_percentage' => (int) ($slot['manual_discount_percentage'] ?? 0) ?: null,
                'manual_discount_reason'     => $slot['manual_discount_reason'] ?? null,
            ];
        }

        foreach ($this->guestDetails->where('excluded_from_veteran_discount', true) as $guest) {
            $byIndex[$guest->sort_order] = (object) [
                'sort_order'                 => $guest->sort_order,
                'full_name'                  => $guest->full_name,
                'manual_discount_percentage' => $guest->manual_discount_percentage,
                'manual_discount_reason'     => $guest->manual_discount_reason,
            ];
        }

        return collect($byIndex)->sortKeys()->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, object|\App\Models\BookingGuestDetail>
     */
    public function guestRowsForDisplay()
    {
        $rows = $this->guestDetails->keyBy('sort_order');
        $merged = collect();

        for ($i = 0; $i < $this->billingGuests(); $i++) {
            if ($rows->has($i)) {
                $merged->push($rows->get($i));

                continue;
            }

            $slot = collect($this->guestDiscountSlots())->firstWhere('sort_order', $i);
            if (!$slot) {
                continue;
            }

            $hasDiscountData = !empty($slot['excluded_from_veteran_discount'])
                || (int) ($slot['manual_discount_percentage'] ?? 0) > 0;

            if (!$hasDiscountData) {
                continue;
            }

            $merged->push((object) [
                'sort_order'                     => $i,
                'full_name'                      => $slot['label'] ?? ('مهمان ' . ($i + 1)),
                'national_id'                    => null,
                'mobile'                         => null,
                'relation'                       => null,
                'excluded_from_veteran_discount' => !empty($slot['excluded_from_veteran_discount']),
                'manual_discount_percentage'     => (int) ($slot['manual_discount_percentage'] ?? 0) ?: null,
                'manual_discount_reason'         => $slot['manual_discount_reason'] ?? null,
                'discount_only'                  => true,
            ]);
        }

        return $merged->values();
    }

    public function statusLabel(): string
    {
        return match($this->status) {
            'pending'   => 'در انتظار تأیید',
            'confirmed' => 'تأیید شده',
            'cancelled' => 'لغو شده',
            default     => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match($this->status) {
            'pending'   => 'warning',
            'confirmed' => 'success',
            'cancelled' => 'danger',
            default     => 'secondary',
        };
    }
}
