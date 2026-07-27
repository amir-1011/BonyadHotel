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

    public function cancellationRequests()
    {
        return $this->hasMany(CancellationRequest::class)->latest('id');
    }

    public function program()
    {
        return $this->hasOne(Program::class);
    }

    public function beneficiaryCosts()
    {
        return $this->hasMany(BookingBeneficiaryCost::class)->orderBy('sort_order');
    }

    public function pendingCancellationRequest(): ?CancellationRequest
    {
        return $this->relationLoaded('cancellationRequests')
            ? $this->cancellationRequests->firstWhere('status', 'pending')
            : $this->cancellationRequests()->where('status', 'pending')->first();
    }

    public function latestCancellationRequest(): ?CancellationRequest
    {
        return $this->relationLoaded('cancellationRequests')
            ? $this->cancellationRequests->first()
            : $this->cancellationRequests()->first();
    }

    public function hasPendingCancellationRequest(): bool
    {
        return $this->pendingCancellationRequest() !== null;
    }

    /**
     * Cancellation/refund requests can only be filed through the end of the check_out
     * calendar day; once the stay is fully over, requesting a refund no longer applies.
     */
    public function cancellationWindowClosed(): bool
    {
        return now()->startOfDay()->gt($this->check_out->copy()->startOfDay());
    }

    /**
     * Whether a new cancellation/refund request can be filed for this booking right now.
     */
    public function canRequestCancellation(): bool
    {
        if ($this->status !== 'confirmed') {
            return false;
        }

        if ($this->cancellationWindowClosed()) {
            return false;
        }

        return !$this->hasPendingCancellationRequest();
    }

    public function isManual(): bool
    {
        return $this->booking_source === 'manual';
    }

    public function isProgram(): bool
    {
        return $this->booking_source === 'program';
    }

    public function panelShowUrl(string $panel): string
    {
        if ($this->isProgram()) {
            $program = $this->relationLoaded('program')
                ? $this->program
                : $this->program()->first();

            if ($program) {
                return route(
                    $panel === 'admin' ? 'admin.programs.show' : 'host.programs.show',
                    $program,
                );
            }
        }

        return route(
            $panel === 'admin' ? 'admin.bookings.show' : 'host.bookings.show',
            $this,
        );
    }

    public function hasReserver(): bool
    {
        return $this->isManual();
    }

    public function scopeWithReserver($query)
    {
        return $query->where('booking_source', 'manual');
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

    public function reserverName(): string
    {
        if ($this->booking_source === 'online') {
            return '—';
        }

        if ($this->created_by) {
            return $this->createdBy?->name
                ?? $this->createdBy?->mobile
                ?? '—';
        }

        return $this->user?->name
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
        return $this->bookerIdentityNumber();
    }

    public function bookerIdentityLabel(): string
    {
        $guest = $this->bookerGuestDetail();
        if ($guest && ($guest->is_foreign_guest || filled($guest->national_id) || filled($guest->passport_number))) {
            return $guest->identityFieldLabel();
        }

        if ($this->user) {
            return $this->user->identityFieldLabel();
        }

        return 'کد ملی';
    }

    public function bookerIdentityNumber(): ?string
    {
        $guest = $this->bookerGuestDetail();
        if ($guest && ($guest->is_foreign_guest || filled($guest->passport_number) || filled($guest->national_id))) {
            return $guest->identityNumber();
        }

        if ($this->user?->identityNumber()) {
            return $this->user->identityNumber();
        }

        $fromGuest = $guest?->national_id;

        return filled($fromGuest) ? $fromGuest : $this->user?->national_id;
    }

    public function bookerResidenceLabel(): ?string
    {
        $guest = $this->bookerGuestDetail();
        if ($label = $guest?->residenceLocationLabel()) {
            return $label;
        }

        return $this->user?->residenceLocationLabel();
    }

    public function bookerGuestDetail(): ?BookingGuestDetail
    {
        $details = $this->relationLoaded('guestDetails')
            ? $this->guestDetails
            : ($this->exists ? $this->guestDetails()->orderBy('sort_order')->get() : collect());

        return $details->first();
    }

    public function bookingSourceLabel(): string
    {
        return match ($this->booking_source) {
            'manual'  => 'رزرو دستی',
            'online'  => 'آنلاین',
            'program' => 'برنامه / اردو',
            default   => $this->booking_source ?: '—',
        };
    }

    public function bookingTypeLabel(): string
    {
        return match ($this->booking_source) {
            'manual'  => 'حضوری',
            'online'  => 'اینترنتی',
            'program' => 'اردو',
            default   => $this->bookingSourceLabel(),
        };
    }

    public function roomTypeNamesSummary(): string
    {
        $lines = $this->relationLoaded('bookingRooms')
            ? $this->bookingRooms
            : ($this->exists ? $this->bookingRooms()->with('roomType')->get() : collect());

        if ($lines->isNotEmpty()) {
            $names = $lines
                ->map(fn (BookingRoom $line) => $line->roomType?->categoryLabel())
                ->filter(fn ($label) => $label !== null && $label !== '—')
                ->unique()
                ->values();

            if ($names->isNotEmpty()) {
                return $names->join('، ');
            }
        }

        return $this->roomType?->categoryLabel() ?? '—';
    }

    public function physicalRoomNamesDisplay(): string
    {
        $lines = $this->relationLoaded('bookingRooms')
            ? $this->bookingRooms
            : ($this->exists ? $this->bookingRooms()->with(['room', 'roomType'])->get() : collect());

        if ($lines->isEmpty()) {
            return '—';
        }

        $names = $lines
            ->map(fn (BookingRoom $line) => $line->physicalRoomDisplayLabel())
            ->filter(fn ($label) => $label !== '—')
            ->values();

        return $names->isNotEmpty() ? $names->join('، ') : '—';
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

    /**
     * Every billed guest slot (including unnamed placeholders without discount data).
     *
     * @return \Illuminate\Support\Collection<int, object|\App\Models\BookingGuestDetail>
     */
    public function allGuestSlotsForDisplay()
    {
        $rows = $this->guestDetails->keyBy('sort_order');
        $merged = collect();

        for ($i = 0; $i < $this->billingGuests(); $i++) {
            if ($rows->has($i)) {
                $merged->push($rows->get($i));

                continue;
            }

            $slot = collect($this->guestDiscountSlots())->firstWhere('sort_order', $i);

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
                'is_name_placeholder'            => true,
            ]);
        }

        return $merged->values();
    }

    public function servicesForGuest(?int $sortOrder)
    {
        return $this->services
            ->filter(fn (BookingService $service) => (int) $service->guest_sort_order === $sortOrder)
            ->sortBy('sort_order')
            ->values();
    }

    /** @return \Illuminate\Support\Collection<int, BookingService> */
    public function unassignedGuestServices()
    {
        return $this->services
            ->filter(fn (BookingService $service) => $service->guest_sort_order === null)
            ->sortBy('sort_order')
            ->values();
    }

    public function guestPhysicalRoomLabel(BookingGuestDetail $guest): ?string
    {
        $line = $guest->relationLoaded('bookingRoom')
            ? $guest->bookingRoom
            : $guest->bookingRoom()->with('room')->first();

        if (!$line) {
            return null;
        }

        if ($line->room?->name) {
            return $line->room->name;
        }

        return $line->roomType?->name;
    }

    /**
     * Non-admin users may edit booking details through the end of the check_out calendar day.
     */
    public function isWithinBookingEditWindow(): bool
    {
        return now()->startOfDay()->lte($this->check_out->copy()->startOfDay());
    }

    public function canEditBookingDetails(?User $user = null): bool
    {
        if (!in_array($this->status, ['pending', 'confirmed'], true)) {
            return false;
        }

        $user ??= auth()->user();

        if ($user?->isAdmin()) {
            return true;
        }

        return $this->isWithinBookingEditWindow();
    }

    public function canEditServices(): bool
    {
        return $this->canEditBookingDetails();
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
