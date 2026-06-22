<?php

namespace App\Services;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\BookingGuestDetail;
use App\Models\BookingRoom;
use App\Models\BookingService;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use App\Support\VeteranGroups;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManualBookingService
{
    public function __construct(
        private readonly BookingPricingService $pricing,
        private readonly VeteranPolicyService $veteranPolicy,
        private readonly PlatformCommissionService $commission,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Accommodation $accommodation, array $data, User $createdBy): Booking
    {
        return DB::transaction(function () use ($accommodation, $data, $createdBy) {
            $roomLinesInput = $this->normalizeRoomLines($accommodation, $data);
            $isMultiRoom = count($roomLinesInput) > 1
                || (count($roomLinesInput) === 1 && !empty($data['room_lines']));

            $roomType = null;
            $roomRate = null;
            if (!$isMultiRoom && count($roomLinesInput) === 1) {
                $roomType = $roomLinesInput[0]['room_type'];
                $roomRate = $roomLinesInput[0]['room_rate'];
            } elseif (count($roomLinesInput) > 0) {
                $roomType = $roomLinesInput[0]['room_type'];
                $roomRate = $roomLinesInput[0]['room_rate'];
            }

            $guestUser = $this->resolveGuestUser($data);

            $veteranType = $data['veteran_type'] ?? null;
            $services = $data['services'] ?? [];
            $guestDetails = $data['guest_details'] ?? [];
            $primaryNationalId = $this->primaryNationalId($guestDetails, $data);

            $nonVeteranDiscountGuests = collect($guestDetails)
                ->filter(fn ($g) => !empty($g['excluded_from_veteran_discount']))
                ->count();

            $totalGuests = collect($roomLinesInput)->sum('guests');
            $totalChildrenUnder6 = collect($roomLinesInput)->sum('children_under_6');
            $totalExtraGuests = collect($roomLinesInput)->sum('extra_guests');
            $billingGuests = max(1, $totalGuests - $totalExtraGuests);
            $veteranDiscountPct = VeteranGroups::accommodationDiscount($veteranType);
            $perGuestSlots = $this->pricing->buildPerGuestSlotsFromGuestDetails(
                $guestDetails,
                $billingGuests,
                $totalChildrenUnder6,
                $veteranType,
                $veteranDiscountPct,
            );

            $pricingParams = [
                'check_in'             => $data['check_in'],
                'check_out'            => $data['check_out'],
                'guests'               => $totalGuests,
                'children_under_6'     => $totalChildrenUnder6,
                'extra_guests'         => $totalExtraGuests,
                'bill_full_rooms'      => false,
                'veteran_type'         => $veteranType,
                'services'             => $services,
                'accommodation'        => $accommodation,
                'national_id'          => $primaryNationalId,
                'user_id'              => $guestUser->id,
                'non_veteran_discount_guests' => min($totalGuests, $nonVeteranDiscountGuests),
                'per_guest_slots'      => $perGuestSlots,
            ];

            if ($isMultiRoom || count($roomLinesInput) > 0) {
                $pricingParams['room_lines'] = collect($roomLinesInput)->map(fn ($line) => [
                    'room_type'        => $line['room_type'],
                    'room_rate'        => $line['room_rate'],
                    'guests'           => $line['guests'],
                    'children_under_6' => $line['children_under_6'],
                    'extra_guests'     => $line['extra_guests'],
                    'bill_full_rooms'  => $line['bill_full_rooms'],
                ])->all();
            } else {
                $pricingParams['room_type'] = $roomType;
                $pricingParams['room_rate'] = $roomRate;
            }

            $pricing = $this->pricing->calculate($pricingParams);

            $consumptionByRoomType = [];
            foreach ($roomLinesInput as $i => $line) {
                $lineRoomType = $line['room_type'];
                if (!$lineRoomType) {
                    continue;
                }
                $linePricing = $pricing['room_lines'][$i] ?? null;
                $roomsNeeded = $linePricing['rooms_needed']
                    ?? $this->pricing->roomsNeeded($line['guests'], $line['extra_guests'], $lineRoomType, $line['children_under_6'], $accommodation);
                $consumptionByRoomType[$lineRoomType->id] = ($consumptionByRoomType[$lineRoomType->id] ?? 0) + $roomsNeeded;
            }

            foreach ($consumptionByRoomType as $roomTypeId => $roomsNeeded) {
                $rt = RoomType::where('accommodation_id', $accommodation->id)->find($roomTypeId);
                if ($rt && !$rt->isAvailable($data['check_in'], $data['check_out'], $roomsNeeded)) {
                    throw new \RuntimeException('ظرفیت کافی برای بازه انتخابی وجود ندارد.');
                }
            }

            $accommodationDiscountPct = VeteranGroups::accommodationDiscount($veteranType);
            $billingGuests = max(1, $totalGuests - $totalExtraGuests);
            $guestDiscountSnapshot = $this->buildGuestDiscountSnapshot(
                $guestDetails,
                $billingGuests,
                $veteranType,
                $data,
            );

            $booking = Booking::create([
                'user_id'               => $guestUser->id,
                'created_by'            => $createdBy->id,
                'accommodation_id'      => $accommodation->id,
                'room_type_id'          => $roomType?->id,
                'room_rate_id'          => $roomRate?->id,
                'check_in'              => $data['check_in'],
                'check_out'             => $data['check_out'],
                'guests'                => $totalGuests,
                'children_under_6'      => $totalChildrenUnder6,
                'guest_contact_name'    => $data['guest_contact_name'] ?? $guestUser->name,
                'guest_contact_mobile'  => $data['guest_contact_mobile'] ?? $guestUser->mobile,
                'rooms_consumed'        => $pricing['rooms_needed'],
                'extra_guests'          => $totalExtraGuests,
                'extra_guests_price'    => $pricing['extra_guests_total'],
                'bill_full_rooms'       => false,
                'nights'                => $pricing['nights'],
                'base_price'            => $pricing['subtotal_before_discount'],
                'services_subtotal'     => $pricing['services_subtotal'],
                'discount_percentage'   => $accommodationDiscountPct,
                'veteran_type_applied'  => $veteranType ?: null,
                'discount_amount'       => $pricing['discount_amount'],
                'total_price'           => $pricing['total_price'],
                'status'                => 'confirmed',
                'booking_source'        => 'manual',
                'payment_method'        => $data['payment_method'] ?? null,
                'notes'                 => $data['notes'] ?? null,
                'guest_discount_snapshot' => $guestDiscountSnapshot,
                'tracking_code'         => strtoupper(Str::random(10)),
            ]);

            foreach ($roomLinesInput as $i => $line) {
                $linePricing = $pricing['room_lines'][$i] ?? [];
                BookingRoom::create([
                    'booking_id'       => $booking->id,
                    'room_type_id'     => $line['room_type']?->id,
                    'room_rate_id'     => $line['room_rate']?->id,
                    'adults'           => $line['adults'],
                    'children_under_6' => $line['children_under_6'],
                    'guests'           => $line['guests'],
                    'extra_guests'     => $line['extra_guests'],
                    'bill_full_rooms'  => $line['bill_full_rooms'],
                    'rooms_consumed'   => $linePricing['rooms_needed']
                        ?? $this->pricing->roomsNeeded($line['guests'], $line['extra_guests'], $line['room_type'], $line['children_under_6'], $accommodation),
                    'sort_order'       => $i,
                ]);
            }

            foreach ($pricing['service_lines'] as $i => $line) {
                BookingService::create([
                    'booking_id'          => $booking->id,
                    'service_catalog_id'  => $line['service_catalog_id'] ?: null,
                    'name'                => $line['name'],
                    'unit_price'          => $line['unit_price'],
                    'quantity'            => $line['quantity'],
                    'free_units'          => $line['free_units'] ?? 0,
                    'discount_percentage' => $line['discount_percentage'],
                    'discount_amount'     => $line['discount_amount'],
                    'total'               => $line['line_total'],
                    'sort_order'          => $i,
                ]);
            }

            $this->persistGuestDetails($booking, $guestDetails, $billingGuests, $veteranType, $data);

            $booking = $booking->fresh(['services.serviceCatalog', 'guestDetails', 'bookingRooms.roomType', 'bookingRooms.roomRate', 'user', 'accommodation.city', 'roomType', 'roomRate']);
            $this->commission->syncBookingCommissions($booking, $createdBy);

            return $booking;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{
     *   room_type: ?RoomType,
     *   room_rate: ?RoomRate,
     *   adults: int,
     *   children_under_6: int,
     *   guests: int,
     *   extra_guests: int,
     *   bill_full_rooms: bool
     * }>
     */
    private function normalizeRoomLines(Accommodation $accommodation, array $data): array
    {
        if (!empty($data['room_lines']) && is_array($data['room_lines'])) {
            return collect($data['room_lines'])->map(function ($line) use ($accommodation) {
                return $this->resolveRoomLine($accommodation, $line);
            })->values()->all();
        }

        $adults = max(1, (int) ($data['guests'] ?? 1) - (int) ($data['children_under_6'] ?? 0));

        return [
            $this->resolveRoomLine($accommodation, [
                'room_type_id'     => $data['room_type_id'] ?? null,
                'room_rate_id'     => $data['room_rate_id'] ?? null,
                'adults'           => $adults,
                'children_under_6' => (int) ($data['children_under_6'] ?? 0),
                'guests'           => (int) ($data['guests'] ?? 1),
                'extra_guests'     => (int) ($data['extra_guests'] ?? 0),
                'bill_full_rooms'  => (bool) ($data['bill_full_rooms'] ?? false),
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array{
     *   room_type: ?RoomType,
     *   room_rate: ?RoomRate,
     *   adults: int,
     *   children_under_6: int,
     *   guests: int,
     *   extra_guests: int,
     *   bill_full_rooms: bool
     * }
     */
    private function resolveRoomLine(Accommodation $accommodation, array $line): array
    {
        $roomType = null;
        $roomRate = null;

        if (!empty($line['room_rate_id'])) {
            $roomRate = RoomRate::findOrFail($line['room_rate_id']);
            if ($roomRate->roomType->accommodation_id !== $accommodation->id) {
                abort(422, 'تعرفه انتخاب‌شده معتبر نیست.');
            }
            $roomType = $roomRate->roomType;
        } elseif (!empty($line['room_type_id'])) {
            $roomType = RoomType::where('accommodation_id', $accommodation->id)->findOrFail($line['room_type_id']);
        }

        $childrenUnder6 = max(0, (int) ($line['children_under_6'] ?? 0));
        $adults = max(1, (int) ($line['adults'] ?? (($line['guests'] ?? 1) - $childrenUnder6)));
        $guests = max(1, (int) ($line['guests'] ?? ($adults + $childrenUnder6)));

        return [
            'room_type'        => $roomType,
            'room_rate'        => $roomRate,
            'adults'           => $adults,
            'children_under_6' => $childrenUnder6,
            'guests'           => $guests,
            'extra_guests'     => max(0, (int) ($line['extra_guests'] ?? 0)),
            'bill_full_rooms'  => (bool) ($line['bill_full_rooms'] ?? false),
        ];
    }

    private function primaryNationalId(array $guestDetails, array $data): ?string
    {
        $bookerId = preg_replace('/\D/', '', $data['booker_national_id'] ?? '');
        if (strlen($bookerId) === 10) {
            return $bookerId;
        }

        foreach ($guestDetails as $guest) {
            $id = preg_replace('/\D/', '', $guest['national_id'] ?? '');
            if (strlen($id) === 10) {
                return $id;
            }
        }

        if (!empty($data['user_id'])) {
            $user = User::find($data['user_id']);

            return $user?->national_id;
        }

        return null;
    }

    private function resolveGuestUser(array $data): User
    {
        if (!empty($data['user_id'])) {
            return User::findOrFail($data['user_id']);
        }

        $nationalId = preg_replace('/\D/', '', $data['booker_national_id'] ?? '');
        $name = trim($data['guest_contact_name'] ?? '') ?: 'مهمان';
        $mobile = preg_replace('/\D/', '', $data['guest_contact_mobile'] ?? '');
        $veteranType = $data['veteran_type'] ?? null;
        $discountPct = VeteranGroups::accommodationDiscount($veteranType);

        if (strlen($nationalId) !== 10) {
            throw new \RuntimeException('کد ملی رزرو‌کننده معتبر نیست.');
        }

        if (!$mobile || !preg_match('/^09[0-9]{9}$/', $mobile)) {
            throw new \RuntimeException('شماره موبایل رزرو‌کننده معتبر نیست.');
        }

        $byNationalId = User::query()
            ->where('national_id', $nationalId)
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['super_admin', 'host']))
            ->first();

        if ($byNationalId) {
            if ($byNationalId->mobile !== $mobile) {
                throw new \RuntimeException(
                    "کد ملی با شماره موبایل هم‌خوانی ندارد. این کد ملی متعلق به {$byNationalId->mobile} است."
                );
            }

            return $byNationalId;
        }

        $byMobile = User::where('mobile', $mobile)->first();
        if ($byMobile) {
            if ($this->isStaffUser($byMobile)) {
                throw new \RuntimeException('این شماره موبایل متعلق به حساب کارکنان است.');
            }

            throw new \RuntimeException(
                'این شماره موبایل قبلاً ثبت شده است'
                . ($byMobile->national_id ? " (کد ملی: {$byMobile->national_id})" : '')
                . '. لطفاً با همان کد ملی «بررسی» کنید.'
            );
        }

        if (User::where('national_id', $nationalId)->exists()) {
            throw new \RuntimeException('این کد ملی قبلاً برای کاربر دیگری ثبت شده است.');
        }

        $user = User::create([
            'name'                    => $name,
            'mobile'                  => $mobile,
            'national_id'             => $nationalId,
            'veteran_type'            => $veteranType ?: null,
            'discount_percentage'     => $discountPct,
            'national_id_verified_at' => now(),
            'mobile_verified_at'      => now(),
        ]);

        if (!$user->hasAnyRole(['super_admin', 'host', 'guest'])) {
            $user->assignRole('guest');
        }

        return $user;
    }

    private function isStaffUser(User $user): bool
    {
        return $user->isAdmin() || $user->isHost();
    }

    public function recalculateTotals(Booking $booking): void
    {
        // Always fetch fresh rows from the database so that any edits made
        // just before this call (e.g. saveServiceEdits) are reflected.
        $freshServices = $booking->services()->orderBy('sort_order')->get();
        $guestDetails = $booking->guestDetails()->orderBy('sort_order')->get();

        $services = $freshServices->map(fn ($s) => [
            'name'               => $s->name,
            'unit_price'         => $s->unit_price,
            'quantity'           => $s->quantity,
            'service_catalog_id' => $s->service_catalog_id,
            // Always re-derive from policy matrix so free-session services
            // (e.g. veteran_70 + pool with matrix discount = 0%) are not
            // incorrectly clamped to min_discount when the stored value is 0.
            'discount_override'  => null,
        ])->all();

        $bookingRooms = $booking->bookingRooms()->with(['roomType', 'roomRate'])->orderBy('sort_order')->get();
        $billingGuests = max(1, (int) $booking->guests - (int) $booking->extra_guests);
        $veteranDiscountPct = VeteranGroups::accommodationDiscount($booking->veteran_type_applied);
        $guestDetailsArray = $guestDetails->map(fn ($g) => [
            'excluded_from_veteran_discount' => $g->excluded_from_veteran_discount,
            'manual_discount_percentage'     => $g->manual_discount_percentage,
            'manual_discount_reason'         => $g->manual_discount_reason,
        ])->all();
        $perGuestSlots = $this->pricing->buildPerGuestSlotsFromGuestDetails(
            $guestDetailsArray,
            $billingGuests,
            (int) ($booking->children_under_6 ?? 0),
            $booking->veteran_type_applied,
            $veteranDiscountPct,
        );

        if ($bookingRooms->isNotEmpty()) {
            $roomLines = $bookingRooms->map(fn ($line) => [
                'room_type'        => $line->roomType,
                'room_rate'        => $line->roomRate,
                'guests'           => $line->guests,
                'children_under_6' => $line->children_under_6,
                'extra_guests'     => $line->extra_guests,
                'bill_full_rooms'  => $line->bill_full_rooms,
            ])->all();

            $pricingParams = [
                'check_in'            => $booking->check_in->format('Y-m-d'),
                'check_out'           => $booking->check_out->format('Y-m-d'),
                'guests'              => $booking->guests,
                'children_under_6'    => $booking->children_under_6 ?? 0,
                'extra_guests'        => $booking->extra_guests,
                'bill_full_rooms'     => false,
                'veteran_type'        => $booking->veteran_type_applied,
                'services'            => $services,
                'accommodation'       => $booking->accommodation,
                'national_id'         => $booking->guestDetails()->value('national_id') ?? $booking->user?->national_id,
                'user_id'             => $booking->user_id,
                'exclude_booking_id'  => $booking->id,
                'non_veteran_discount_guests' => $booking->guestDetails()
                    ->where('excluded_from_veteran_discount', true)
                    ->count(),
                'per_guest_slots'             => $perGuestSlots,
                'room_lines'          => $roomLines,
            ];
        } else {
            $pricingParams = [
                'check_in'            => $booking->check_in->format('Y-m-d'),
                'check_out'           => $booking->check_out->format('Y-m-d'),
                'guests'              => $booking->guests,
                'children_under_6'    => $booking->children_under_6 ?? 0,
                'extra_guests'        => $booking->extra_guests,
                'bill_full_rooms'     => $booking->bill_full_rooms,
                'veteran_type'        => $booking->veteran_type_applied,
                'services'            => $services,
                'accommodation'       => $booking->accommodation,
                'room_type'           => $booking->roomType,
                'room_rate'           => $booking->roomRate,
                'national_id'         => $booking->guestDetails()->value('national_id') ?? $booking->user?->national_id,
                'user_id'             => $booking->user_id,
                'exclude_booking_id'  => $booking->id,
                'non_veteran_discount_guests' => $booking->guestDetails()
                    ->where('excluded_from_veteran_discount', true)
                    ->count(),
                'per_guest_slots'             => $perGuestSlots,
            ];
        }

        $pricing = $this->pricing->calculate($pricingParams);

        foreach ($pricing['service_lines'] as $i => $line) {
            $service = $freshServices->get($i);
            if (!$service) {
                continue;
            }
            $service->update([
                'discount_percentage' => $line['discount_percentage'],
                'discount_amount'     => $line['discount_amount'],
                'free_units'          => $line['free_units'] ?? 0,
                'total'               => $line['line_total'],
                'sort_order'          => $i,
            ]);
        }

        $booking->update([
            'base_price'        => $pricing['subtotal_before_discount'],
            'services_subtotal' => $pricing['services_subtotal'],
            'extra_guests_price'=> $pricing['extra_guests_total'],
            'discount_amount'   => $pricing['discount_amount'],
            'total_price'       => $pricing['total_price'],
        ]);

        $booking->refresh()->load(['services.serviceCatalog', 'accommodation']);
        $this->commission->syncBookingCommissions($booking);
    }

    /**
     * @param  array<int, array<string, mixed>>  $guestDetails
     * @param  array<string, mixed>  $data
     */
    private function persistGuestDetails(
        Booking $booking,
        array $guestDetails,
        int $billingGuests,
        ?string $veteranType,
        array $data,
    ): void {
        for ($i = 0; $i < $billingGuests; $i++) {
            $guest = $guestDetails[$i] ?? [];

            if (!$this->shouldPersistGuestDetail($guest, $i, $veteranType, $data)) {
                continue;
            }

            $fullName = trim($guest['full_name'] ?? '');
            if ($fullName === '') {
                $fullName = $i === 0
                    ? trim($data['guest_contact_name'] ?? '') ?: 'رزرو‌کننده'
                    : 'مهمان ' . ($i + 1);
            }

            BookingGuestDetail::create([
                'booking_id'  => $booking->id,
                'sort_order'  => $i,
                'full_name'   => $fullName,
                'national_id' => $guest['national_id'] ?? null,
                'mobile'      => $guest['mobile'] ?? null,
                'relation'    => $guest['relation'] ?? null,
                'excluded_from_veteran_discount' => !empty($guest['excluded_from_veteran_discount']),
                'manual_discount_percentage' => $this->normalizedManualDiscountPct($guest, $veteranType),
                'manual_discount_reason'     => $this->normalizedManualDiscountReason($guest, $veteranType),
                'notes'       => $guest['notes'] ?? null,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $guestDetails
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>|null
     */
    private function buildGuestDiscountSnapshot(
        array $guestDetails,
        int $billingGuests,
        ?string $veteranType,
        array $data,
    ): ?array {
        $snapshot = [];

        for ($i = 0; $i < $billingGuests; $i++) {
            $guest = $guestDetails[$i] ?? [];
            $excluded = !empty($guest['excluded_from_veteran_discount']);
            $manualPct = $this->normalizedManualDiscountPct($guest, $veteranType);
            $rawManualPct = $this->rawManualDiscountPct($guest);

            if (!$excluded && !$manualPct && !$rawManualPct) {
                continue;
            }

            $label = trim($guest['full_name'] ?? '');
            if ($label === '') {
                $label = $i === 0
                    ? trim($data['guest_contact_name'] ?? '') ?: 'رزرو‌کننده'
                    : 'مهمان ' . ($i + 1);
            }

            $reason = $this->normalizedManualDiscountReason($guest, $veteranType);
            if (!$reason && $rawManualPct) {
                $reason = trim((string) ($guest['manual_discount_reason'] ?? '')) ?: null;
            }

            $snapshot[] = [
                'sort_order'                     => $i,
                'label'                          => $label,
                'excluded_from_veteran_discount' => $excluded,
                'manual_discount_percentage'     => $manualPct ?? $rawManualPct,
                'manual_discount_reason'         => $reason,
            ];
        }

        return $snapshot === [] ? null : $snapshot;
    }

    /**
     * @param  array<string, mixed>  $guest
     */
    private function rawManualDiscountPct(array $guest): ?int
    {
        $raw = $guest['manual_discount_percentage'] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }

        $pct = max(0, min(100, (int) $raw));

        return $pct > 0 ? $pct : null;
    }

    /**
     * @param  array<string, mixed>  $guest
     */
    private function hasManualDiscountInput(array $guest): bool
    {
        return $this->rawManualDiscountPct($guest) !== null;
    }

    /**
     * @param  array<string, mixed>  $guest
     * @param  array<string, mixed>  $data
     */
    private function shouldPersistGuestDetail(array $guest, int $index, ?string $veteranType, array $data): bool
    {
        if ($index === 0) {
            return true;
        }

        if (trim($guest['full_name'] ?? '') !== '') {
            return true;
        }

        if (!empty($guest['excluded_from_veteran_discount'])) {
            return true;
        }

        if ($this->hasManualDiscountInput($guest)) {
            return true;
        }

        if ($this->normalizedManualDiscountPct($guest, $veteranType)) {
            return true;
        }

        if (trim($guest['national_id'] ?? '') !== '') {
            return true;
        }

        if (trim($guest['mobile'] ?? '') !== '') {
            return true;
        }

        return trim($guest['relation'] ?? '') !== '';
    }

    /**
     * @param  array<string, mixed>  $guest
     */
    private function normalizedManualDiscountPct(array $guest, ?string $veteranType = null): ?int
    {
        $veteranDiscountPct = VeteranGroups::accommodationDiscount($veteranType);
        $excluded = !empty($guest['excluded_from_veteran_discount']);

        if ($veteranType && $veteranDiscountPct > 0 && !$excluded) {
            return null;
        }

        $raw = $guest['manual_discount_percentage'] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }

        $pct = max(0, min(100, (int) $raw));

        return $pct > 0 ? $pct : null;
    }

    /**
     * @param  array<string, mixed>  $guest
     */
    private function normalizedManualDiscountReason(array $guest, ?string $veteranType = null): ?string
    {
        $pct = $this->normalizedManualDiscountPct($guest, $veteranType);
        if (!$pct) {
            return null;
        }

        $reason = trim((string) ($guest['manual_discount_reason'] ?? ''));

        return $reason !== '' ? $reason : null;
    }
}
