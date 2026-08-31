<?php

namespace App\Services;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\BookingBeneficiaryCost;
use App\Models\BookingGuestDetail;
use App\Models\BookingPaymentRecord;
use App\Models\ProgramBeneficiary;
use App\Models\BookingRoom;
use App\Models\BookingService;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use App\Support\VeteranGroups;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManualBookingService
{
    public function __construct(
        private readonly BookingPricingService $pricing,
        private readonly VeteranPolicyService $veteranPolicy,
        private readonly PlatformCommissionService $commission,
        private readonly RoomAvailabilityService $roomAvailability,
        private readonly ProgramDocumentService $documents,
        private readonly BeneficiaryUserProvisioner $beneficiaryUsers,
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

            $isMedicalAccommodation = $this->isMedicalAccommodationPayload($data);
            if ($isMedicalAccommodation) {
                $data = $this->sanitizeMedicalAccommodationPayload($data);
            }

            $isCredit = !$isMedicalAccommodation && $this->isCreditPayload($data);
            if ($isCredit) {
                $data = $this->sanitizeCreditPayload($data);
            }

            $guestUser = $this->resolveGuestUser($data, $accommodation->id);

            $hasProfileVeteranTypes = array_key_exists('profile_veteran_types', $data)
                && is_array($data['profile_veteran_types']);

            $profileVeteranTypes = $this->veteranPolicy
                ->forAccommodation($accommodation->id)
                ->normalizeVeteranTypes(
                    $hasProfileVeteranTypes
                        ? $data['profile_veteran_types']
                        : ($data['veteran_types'] ?? [
                            $data['veteran_type'] ?? null,
                            $data['secondary_veteran_type'] ?? null,
                        ]),
                );

            if ($isMedicalAccommodation || $isCredit) {
                $veteranType = null;
                $secondaryVeteranType = null;
                $veteranTypes = [];
            } else {
                $veteranTypes = $profileVeteranTypes;
                [$veteranType, $secondaryVeteranType] = $this->veteranPolicy
                    ->forAccommodation($accommodation->id)
                    ->splitVeteranTypes($veteranTypes);
            }

            $shouldSyncVeteranProfile = $hasProfileVeteranTypes
                || $profileVeteranTypes !== []
                || !($isMedicalAccommodation || $isCredit);

            if ($shouldSyncVeteranProfile) {
                $this->syncUserVeteranProfile($guestUser, $profileVeteranTypes, $accommodation->id);
            }

            $services = $data['services'] ?? [];
            $guestDetails = $data['guest_details'] ?? [];
            $primaryNationalId = $this->primaryNationalId($guestDetails, $data);

            $nonVeteranDiscountGuests = collect($guestDetails)
                ->filter(fn ($g) => !empty($g['excluded_from_veteran_discount']))
                ->count();

            $totalGuests = collect($roomLinesInput)->sum('guests');
            $totalChildrenUnder6 = collect($roomLinesInput)->sum('children_under_6');
            $totalExtraGuests = collect($roomLinesInput)->sum('extra_guests');
            $billingGuests = $this->pricing->totalBillingGuestsForRoomLines(
                collect($roomLinesInput)->map(fn ($line) => [
                    'room_type'        => $line['room_type'],
                    'guests'           => $line['guests'],
                    'children_under_6' => $line['children_under_6'],
                    'extra_guests'     => $line['extra_guests'],
                    'bill_full_rooms'  => $line['bill_full_rooms'],
                ])->all(),
                $accommodation,
            );
            $veteranDiscountPct = VeteranGroups::accommodationDiscountForTypes($veteranTypes, $accommodation->id);
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
                'secondary_veteran_type' => $secondaryVeteranType,
                'veteran_types'        => $veteranTypes,
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

            $medicalContext = null;
            if ($isMedicalAccommodation) {
                $medicalContext = app(MedicalAccommodationBillingService::class)->assertReadyForBooking(
                    $accommodation,
                    $data['check_in'],
                    $data['check_out'],
                    max(1, $totalGuests + $totalExtraGuests),
                    isset($data['medical_tariff_id']) ? (int) $data['medical_tariff_id'] : null,
                    !empty($data['medical_contract_id']) ? (int) $data['medical_contract_id'] : null,
                );
                $pricing = app(MedicalAccommodationPricingService::class)->overlayQuote(
                    $pricing,
                    $medicalContext['quote'],
                );
            }

            $pricing = $this->commission->overlayPricing($pricing, [
                'booking_source'           => 'manual',
                'payment_method'           => $data['payment_method'] ?? null,
                'is_credit'                => $isCredit,
                'is_medical_accommodation' => $isMedicalAccommodation,
            ]);

            $priceAdjustment = (int) ($data['total_price_adjustment'] ?? 0);
            if ($priceAdjustment !== 0) {
                $pricing['total_price'] = max(0, (int) $pricing['total_price'] + $priceAdjustment);
            }

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

            foreach ($roomLinesInput as $line) {
                if (empty($line['room_id'])) {
                    continue;
                }
                $room = Room::with('roomType')->find($line['room_id']);
                if (!$room || $room->roomType?->accommodation_id !== $accommodation->id) {
                    throw new \RuntimeException('اتاق اختصاص‌داده‌شده معتبر نیست.');
                }
                $otherAssigned = collect($roomLinesInput)
                    ->pluck('room_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->reject(fn ($id) => $id === (int) $line['room_id'])
                    ->values()
                    ->all();
                if (!$this->roomAvailability->isRoomAvailable($room, $data['check_in'], $data['check_out'], $otherAssigned)) {
                    throw new \RuntimeException('اتاق «' . $room->name . '» در بازه انتخابی در دسترس نیست.');
                }
            }

            $accommodationDiscountPct = VeteranGroups::accommodationDiscountForTypes($veteranTypes, $accommodation->id);
            $billingGuests = max(1, $totalGuests - $totalExtraGuests);
            $guestDiscountSnapshot = $this->buildGuestDiscountSnapshot(
                $guestDetails,
                $billingGuests,
                $veteranType,
                $data,
                $accommodation->id,
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
                'secondary_veteran_type_applied' => $secondaryVeteranType,
                'veteran_accommodation_group_usage' => $pricing['veteran_accommodation_group_usage'] ?? null,
                'discount_amount'       => $pricing['discount_amount'],
                'total_price'           => $pricing['total_price'],
                'status'                => 'confirmed',
                'booking_source'        => 'manual',
                'payment_method'        => $data['payment_method'] ?? null,
                'is_medical_accommodation' => $isMedicalAccommodation,
                'medical_contract_id'    => $medicalContext['contract']->id ?? null,
                'medical_tariff_id'      => $medicalContext['tariff']->id ?? null,
                'medical_tariff_snapshot'=> $medicalContext['quote'] ?? null,
                'medical_companion_count'=> $medicalContext['quote']['companion_count'] ?? 0,
                'program_employer_id'    => $medicalContext['employer_id'] ?? null,
                'employer_debt_amount'   => $isMedicalAccommodation ? (int) $pricing['total_price'] : 0,
                'is_credit'              => $isCredit,
                'notes'                 => $data['notes'] ?? null,
                'guest_discount_snapshot' => $guestDiscountSnapshot,
                'tracking_code'         => strtoupper(Str::random(10)),
            ]);

            $bookingRoomIdsBySort = [];

            foreach ($roomLinesInput as $i => $line) {
                $linePricing = $pricing['room_lines'][$i] ?? [];
                $roomsConsumed = !empty($line['room_id'])
                    ? 1
                    : ($linePricing['rooms_needed']
                        ?? $this->pricing->roomsNeeded($line['guests'], $line['extra_guests'], $line['room_type'], $line['children_under_6'], $accommodation));

                $bookingRoom = BookingRoom::create([
                    'booking_id'       => $booking->id,
                    'room_type_id'     => $line['room_type']?->id,
                    'room_rate_id'     => $line['room_rate']?->id,
                    'room_id'          => $line['room_id'] ?? null,
                    'adults'           => $line['adults'],
                    'children_under_6' => $line['children_under_6'],
                    'guests'           => $line['guests'],
                    'extra_guests'     => $line['extra_guests'],
                    'bill_full_rooms'  => $line['bill_full_rooms'],
                    'rooms_consumed'   => $roomsConsumed,
                    'sort_order'       => $i,
                ]);

                $bookingRoomIdsBySort[$i] = $bookingRoom->id;
            }

            foreach ($pricing['service_lines'] as $i => $line) {
                BookingService::create([
                    'booking_id'                 => $booking->id,
                    'guest_sort_order'           => $line['guest_sort_order'] ?? null,
                    'service_catalog_id'         => $line['service_catalog_id'] ?: null,
                    'service_catalog_variant_id'   => $line['service_catalog_variant_id'] ?? null,
                    'name'                       => $line['name'],
                    'unit_price'                 => $line['unit_price'],
                    'quantity'                   => $line['quantity'],
                    'free_units'                 => $line['free_units'] ?? 0,
                    'discount_percentage'        => $line['discount_percentage'],
                    'discount_amount'            => $line['discount_amount'],
                    'total'                      => $line['line_total'],
                    'sort_order'                 => $i,
                    'veteran_group_usage'        => $line['veteran_group_usage'] ?? null,
                    'excluded_from_veteran_quota' => !empty($line['excluded_from_veteran_quota']),
                    'manual_discount_percentage' => $line['manual_discount_percentage'] ?? null,
                    'manual_discount_reason'     => $line['manual_discount_reason'] ?? null,
                ]);
            }

            $this->persistGuestDetails($booking, $guestDetails, $billingGuests, $veteranType, $data, $bookingRoomIdsBySort);

            $this->persistBeneficiaryCosts($booking, $data['beneficiary_costs'] ?? []);

            $this->persistMedicalReferralLetter($booking, $data['medical_referral_letter'] ?? null, $isMedicalAccommodation);
            $this->persistCreditLetter($booking, $data['credit_letter'] ?? null, $isCredit);

            $booking = $booking->fresh(['services.serviceCatalog', 'guestDetails', 'bookingRooms.roomType', 'bookingRooms.roomRate', 'bookingRooms.room', 'user', 'accommodation.city', 'roomType', 'roomRate', 'beneficiaryCosts.beneficiary.user']);
            $this->commission->syncBookingCommissions($booking, $createdBy);

            $this->persistPaymentCaptureForManualBooking($booking, $data, $createdBy, $priceAdjustment);

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
     *   room_id: ?int,
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
        $roomId = !empty($line['room_id']) ? (int) $line['room_id'] : null;

        if (!empty($line['room_rate_id'])) {
            $roomRate = RoomRate::findOrFail($line['room_rate_id']);
            if ($roomRate->roomType->accommodation_id !== $accommodation->id) {
                abort(422, 'تعرفه انتخاب‌شده معتبر نیست.');
            }
            $roomType = $roomRate->roomType;
        } elseif (!empty($line['room_type_id'])) {
            $roomType = RoomType::where('accommodation_id', $accommodation->id)->findOrFail($line['room_type_id']);
        }

        if ($roomId) {
            $room = Room::find($roomId);
            if (!$room || $room->room_type_id !== $roomType?->id) {
                abort(422, 'اتاق انتخاب‌شده معتبر نیست.');
            }
        }

        $childrenUnder6 = max(0, (int) ($line['children_under_6'] ?? 0));
        $adults = max(1, (int) ($line['adults'] ?? (($line['guests'] ?? 1) - $childrenUnder6)));
        $guests = max(1, (int) ($line['guests'] ?? ($adults + $childrenUnder6)));

        return [
            'room_type'        => $roomType,
            'room_rate'        => $roomRate,
            'room_id'          => $roomId,
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

    private function resolveGuestUser(array $data, int $accommodationId): User
    {
        if (!empty($data['user_id'])) {
            return User::findOrFail($data['user_id']);
        }

        if (!empty($data['booker_is_foreign_guest'])) {
            return $this->resolveForeignGuestUser($data);
        }

        $nationalId = preg_replace('/\D/', '', $data['booker_national_id'] ?? '');
        $name = trim($data['guest_contact_name'] ?? '') ?: 'مهمان';
        $mobile = preg_replace('/\D/', '', $data['guest_contact_mobile'] ?? '');
        $veteranType = $data['veteran_type'] ?? null;
        $secondaryVeteranType = $data['secondary_veteran_type'] ?? null;
        $veteranTypes = $this->veteranPolicy->forAccommodation($accommodationId)->normalizeVeteranTypes(
            $data['profile_veteran_types'] ?? $data['veteran_types'] ?? [$veteranType, $secondaryVeteranType],
        );
        [$veteranType, $secondaryVeteranType] = $this->veteranPolicy
            ->forAccommodation($accommodationId)
            ->splitVeteranTypes($veteranTypes);
        $discountPct = VeteranGroups::accommodationDiscountForTypes($veteranTypes, $accommodationId);

        if (strlen($nationalId) !== 10) {
            throw new \RuntimeException('کد ملی مهمان اصلی معتبر نیست.');
        }

        if (!$mobile || !preg_match('/^09[0-9]{9}$/', $mobile)) {
            throw new \RuntimeException('شماره موبایل مهمان اصلی معتبر نیست.');
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
            'secondary_veteran_type'  => $secondaryVeteranType,
            'discount_percentage'     => $discountPct,
            'national_id_verified_at' => now(),
            'mobile_verified_at'      => now(),
        ]);

        if (!$user->hasAnyRole(['super_admin', 'host', 'guest'])) {
            $user->assignRole('guest');
        }

        return $user;
    }

    private function resolveForeignGuestUser(array $data): User
    {
        $passport = strtoupper(trim($data['booker_passport_number'] ?? ''));
        $name = trim($data['guest_contact_name'] ?? '') ?: 'مهمان خارجی';
        $mobile = preg_replace('/\D/', '', $data['guest_contact_mobile'] ?? '');
        $countryId = !empty($data['foreign_country_id']) ? (int) $data['foreign_country_id'] : null;
        $residenceCityId = !empty($data['foreign_residence_city_id']) ? (int) $data['foreign_residence_city_id'] : null;

        if ($passport === '' || strlen($passport) < 5) {
            throw new \RuntimeException('شماره پاسپورت مهمان خارجی معتبر نیست.');
        }

        if (!$mobile || !preg_match('/^09[0-9]{9}$/', $mobile)) {
            throw new \RuntimeException('شماره موبایل مهمان خارجی معتبر نیست.');
        }

        if (!$countryId || !$residenceCityId) {
            throw new \RuntimeException('کشور و شهر اقامت مهمان خارجی الزامی است.');
        }

        $byPassport = User::query()
            ->where('passport_number', $passport)
            ->where('is_foreign_guest', true)
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['super_admin', 'host']))
            ->first();

        if ($byPassport) {
            if ($byPassport->mobile !== $mobile) {
                throw new \RuntimeException(
                    "شماره پاسپورت با شماره موبایل هم‌خوانی ندارد. این پاسپورت متعلق به {$byPassport->mobile} است."
                );
            }

            return $byPassport;
        }

        $byMobile = User::where('mobile', $mobile)->first();
        if ($byMobile) {
            if ($this->isStaffUser($byMobile)) {
                throw new \RuntimeException('این شماره موبایل متعلق به حساب کارکنان است.');
            }

            throw new \RuntimeException(
                'این شماره موبایل قبلاً ثبت شده است'
                . ($byMobile->passport_number ? " (پاسپورت: {$byMobile->passport_number})" : '')
                . '. لطفاً با همان شماره پاسپورت «بررسی» کنید.'
            );
        }

        if (User::where('passport_number', $passport)->exists()) {
            throw new \RuntimeException('این شماره پاسپورت قبلاً برای کاربر دیگری ثبت شده است.');
        }

        $user = User::create([
            'name'               => $name,
            'mobile'             => $mobile,
            'is_foreign_guest'   => true,
            'passport_number'    => $passport,
            'country_id'         => $countryId,
            'residence_city_id' => $residenceCityId,
            'mobile_verified_at' => now(),
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

    /**
     * @param  array<int, string>  $veteranTypes
     */
    private function syncUserVeteranProfile(User $user, array $veteranTypes, int $accommodationId): void
    {
        if ($this->isStaffUser($user) || $user->is_foreign_guest) {
            return;
        }

        $policy = $this->veteranPolicy->forAccommodation($accommodationId);
        $veteranTypes = $policy->normalizeVeteranTypes($veteranTypes);
        [$primary, $secondary] = $policy->splitVeteranTypes($veteranTypes);

        $user->update([
            'veteran_type'           => $primary,
            'secondary_veteran_type'   => $secondary,
            'discount_percentage'      => VeteranGroups::accommodationDiscountForTypes($veteranTypes, $accommodationId),
        ]);
    }

    public function recalculateTotals(Booking $booking): void
    {
        // Always fetch fresh rows from the database so that any edits made
        // just before this call (e.g. saveServiceEdits) are reflected.
        $freshServices = $booking->services()->orderBy('sort_order')->get();
        $guestDetails = $booking->guestDetails()->orderBy('sort_order')->get();

        $services = $freshServices->map(fn ($s) => [
            'name'                       => $s->name,
            'unit_price'                 => $s->unit_price,
            'quantity'                   => $s->quantity,
            'service_catalog_id'         => $s->service_catalog_id,
            'service_catalog_variant_id' => $s->service_catalog_variant_id,
            'guest_sort_order'           => $s->guest_sort_order,
            'excluded_from_veteran_quota' => $s->excluded_from_veteran_quota,
            'manual_discount_percentage'   => $s->manual_discount_percentage,
            'manual_discount_reason'     => $s->manual_discount_reason,
            'discount_override'          => null,
        ])->all();

        $bookingRooms = $booking->bookingRooms()->with(['roomType', 'roomRate'])->orderBy('sort_order')->get();
        $billingGuests = max(1, (int) $booking->guests - (int) $booking->extra_guests);
        if ($bookingRooms->isNotEmpty()) {
            $billingGuests = $this->pricing->totalBillingGuestsForRoomLines(
                $bookingRooms->map(fn ($line) => [
                    'room_type'        => $line->roomType,
                    'guests'           => $line->guests,
                    'children_under_6' => $line->children_under_6,
                    'extra_guests'     => $line->extra_guests,
                    'bill_full_rooms'  => $line->bill_full_rooms,
                ])->all(),
                $booking->accommodation,
            );
        }
        $isRegularRate = $booking->billsAsRegularGuest();
        $veteranTypes = $isRegularRate
            ? []
            : $this->veteranPolicy
                ->forAccommodation($booking->accommodation_id)
                ->normalizeVeteranTypes(
                    $booking->veteran_type_applied,
                    $booking->secondary_veteran_type_applied,
                );
        $veteranDiscountPct = VeteranGroups::accommodationDiscountForTypes($veteranTypes, $booking->accommodation_id);
        $guestDetailsArray = $guestDetails->map(fn ($g) => [
            'excluded_from_veteran_discount' => $g->excluded_from_veteran_discount,
            'manual_discount_percentage'     => $g->manual_discount_percentage,
            'manual_discount_reason'         => $g->manual_discount_reason,
        ])->all();
        $perGuestSlots = $this->pricing->buildPerGuestSlotsFromGuestDetails(
            $guestDetailsArray,
            $billingGuests,
            (int) ($booking->children_under_6 ?? 0),
            $isRegularRate ? null : $booking->veteran_type_applied,
            $veteranDiscountPct,
        );
        [$primaryType, $secondaryType] = $this->veteranPolicy
            ->forAccommodation($booking->accommodation_id)
            ->splitVeteranTypes($veteranTypes);

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
                'veteran_type'        => $primaryType,
                'secondary_veteran_type' => $secondaryType,
                'veteran_types'       => $veteranTypes,
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
                'veteran_type'        => $primaryType,
                'secondary_veteran_type' => $secondaryType,
                'veteran_types'       => $veteranTypes,
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

        if ($booking->isMedicalAccommodation()) {
            $guests = max(1, (int) $booking->guests);
            $extraGuests = max(0, (int) $booking->extra_guests);
            $companions = app(MedicalAccommodationPricingService::class)
                ->companionCountFromOccupancy($guests, $extraGuests);
            app(MedicalAccommodationBillingService::class)->assertCompanionLimit($booking, $guests, $extraGuests);
            $quote = app(MedicalAccommodationPricingService::class)->quoteForBooking(
                $booking,
                (int) ($pricing['nights'] ?? $booking->nights),
                $companions,
            );
            if ($quote) {
                $pricing = app(MedicalAccommodationPricingService::class)->overlayQuote($pricing, $quote);
                $booking->medical_tariff_snapshot = $quote;
                $booking->medical_companion_count = $companions;
            } else {
                $pricing = app(MedicalAccommodationPricingService::class)->overlayBooking($pricing, $booking);
            }
        }

        $pricing = $this->commission->overlayPricingForBooking($pricing, $booking);

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
                'veteran_group_usage' => $line['veteran_group_usage'] ?? null,
                'manual_discount_percentage' => $line['manual_discount_percentage'] ?? null,
                'manual_discount_reason'     => $line['manual_discount_reason'] ?? null,
            ]);
        }

        $totals = [
            'base_price'        => $pricing['subtotal_before_discount'],
            'services_subtotal' => $pricing['services_subtotal'],
            'extra_guests_price'=> $pricing['extra_guests_total'],
            'discount_amount'   => $pricing['discount_amount'],
            'total_price'       => $pricing['total_price'],
            'nights'            => $pricing['nights'],
            'veteran_accommodation_group_usage' => $isRegularRate
                ? null
                : ($pricing['veteran_accommodation_group_usage'] ?? null),
        ];

        if ($isRegularRate) {
            $totals['discount_percentage'] = 0;
            $totals['veteran_type_applied'] = null;
            $totals['secondary_veteran_type_applied'] = null;
        }

        if ($booking->isMedicalAccommodation()) {
            $totals['medical_tariff_snapshot'] = $booking->medical_tariff_snapshot;
            $totals['medical_companion_count'] = $booking->medical_companion_count;
            $totals['employer_debt_amount'] = (int) $pricing['total_price'];
            if ($booking->program_employer_id) {
                $totals['program_employer_id'] = $booking->program_employer_id;
            }
        }

        $booking->update($totals);

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
        array $bookingRoomIdsBySort = [],
    ): void {
        for ($i = 0; $i < $billingGuests; $i++) {
            $guest = $guestDetails[$i] ?? [];

            if (!$this->shouldPersistGuestDetail($guest, $i, $veteranType, $data, $booking->accommodation_id)) {
                continue;
            }

            $fullName = trim($guest['full_name'] ?? '');
            if ($fullName === '') {
                $fullName = $i === 0
                    ? trim($data['guest_contact_name'] ?? '') ?: 'رزرو‌کننده'
                    : 'مهمان ' . ($i + 1);
            }

            $roomLineIndex = isset($guest['room_line_index']) ? (int) $guest['room_line_index'] : null;
            $bookingRoomId = ($roomLineIndex !== null && isset($bookingRoomIdsBySort[$roomLineIndex]))
                ? (int) $bookingRoomIdsBySort[$roomLineIndex]
                : null;

            BookingGuestDetail::create([
                'booking_id'  => $booking->id,
                'booking_room_id' => $bookingRoomId,
                'sort_order'  => $i,
                'full_name'   => $fullName,
                'national_id' => ($guest['national_id'] ?? '') !== '' ? $guest['national_id'] : null,
                'is_foreign_guest' => !empty($guest['is_foreign_guest']),
                'passport_number' => !empty($guest['passport_number']) ? strtoupper(trim((string) $guest['passport_number'])) : null,
                'country_id' => !empty($guest['country_id']) ? (int) $guest['country_id'] : null,
                'residence_city_id' => !empty($guest['residence_city_id']) ? (int) $guest['residence_city_id'] : null,
                'mobile'      => $guest['mobile'] ?? null,
                'relation'    => $guest['relation'] ?? null,
                'excluded_from_veteran_discount' => !empty($guest['excluded_from_veteran_discount']),
                'manual_discount_percentage' => $this->normalizedManualDiscountPct($guest, $veteranType, $booking->accommodation_id),
                'manual_discount_reason'     => $this->normalizedManualDiscountReason($guest, $veteranType, $booking->accommodation_id),
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
        int $accommodationId,
    ): ?array {
        $snapshot = [];

        for ($i = 0; $i < $billingGuests; $i++) {
            $guest = $guestDetails[$i] ?? [];
            $excluded = !empty($guest['excluded_from_veteran_discount']);
            $manualPct = $this->normalizedManualDiscountPct($guest, $veteranType, $accommodationId);
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

            $reason = $this->normalizedManualDiscountReason($guest, $veteranType, $accommodationId);
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
    private function shouldPersistGuestDetail(array $guest, int $index, ?string $veteranType, array $data, int $accommodationId): bool
    {
        if ($index === 0) {
            return true;
        }

        if (isset($guest['room_line_index']) && $guest['room_line_index'] !== null && $guest['room_line_index'] !== '') {
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

        if ($this->normalizedManualDiscountPct($guest, $veteranType, $accommodationId)) {
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
    private function normalizedManualDiscountPct(array $guest, ?string $veteranType = null, ?int $accommodationId = null): ?int
    {
        $veteranDiscountPct = VeteranGroups::accommodationDiscount($veteranType, $accommodationId);
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
    private function normalizedManualDiscountReason(array $guest, ?string $veteranType = null, ?int $accommodationId = null): ?string
    {
        $pct = $this->normalizedManualDiscountPct($guest, $veteranType, $accommodationId);
        if (!$pct) {
            return null;
        }

        $reason = trim((string) ($guest['manual_discount_reason'] ?? ''));

        return $reason !== '' ? $reason : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isMedicalAccommodationPayload(array $data): bool
    {
        if (!empty($data['is_medical_accommodation'])) {
            return true;
        }

        return ($data['payment_method'] ?? null) === Booking::PAYMENT_MEDICAL_ACCOMMODATION;
    }

    /**
     * Keep the guest's assigned veteran groups for the user profile even when
     * medical/credit bookings price the stay as a regular guest.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function preserveProfileVeteranTypes(array $data): array
    {
        if (array_key_exists('profile_veteran_types', $data) && is_array($data['profile_veteran_types'])) {
            return $data;
        }

        $fromTypes = !empty($data['veteran_types']) && is_array($data['veteran_types'])
            ? array_values($data['veteran_types'])
            : array_values(array_filter([
                $data['veteran_type'] ?? null,
                $data['secondary_veteran_type'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''));

        if ($fromTypes !== []) {
            $data['profile_veteran_types'] = $fromTypes;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeMedicalAccommodationPayload(array $data): array
    {
        $data = $this->preserveProfileVeteranTypes($data);
        $data['payment_method'] = Booking::PAYMENT_MEDICAL_ACCOMMODATION;
        $data['is_medical_accommodation'] = true;
        $data['veteran_type'] = null;
        $data['secondary_veteran_type'] = null;
        $data['veteran_types'] = [];
        $data['guest_details'] = $this->sanitizeGuestsForMedicalAccommodation($data['guest_details'] ?? []);
        $data['services'] = $this->sanitizeServicesForMedicalAccommodation($data['services'] ?? []);

        return $data;
    }

    /**
     * @param  array<int, array<string, mixed>>  $guestDetails
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeGuestsForMedicalAccommodation(array $guestDetails): array
    {
        return collect($guestDetails)->map(function ($guest) {
            if (!is_array($guest)) {
                return $guest;
            }

            $guest['excluded_from_veteran_discount'] = false;
            $guest['manual_discount_percentage'] = '';
            $guest['manual_discount_reason'] = '';

            if (!empty($guest['services']) && is_array($guest['services'])) {
                $guest['services'] = $this->sanitizeServicesForMedicalAccommodation($guest['services']);
            }

            return $guest;
        })->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $services
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeServicesForMedicalAccommodation(array $services): array
    {
        return collect($services)->map(function ($service) {
            if (!is_array($service)) {
                return $service;
            }

            $service['excluded_from_veteran_quota'] = true;
            $service['discount_override'] = null;
            $service['manual_discount_percentage'] = null;
            $service['manual_discount_reason'] = null;

            return $service;
        })->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistPaymentCaptureForManualBooking(
        Booking $booking,
        array $data,
        User $createdBy,
        int $priceAdjustment,
    ): void {
        $captureService = app(BookingPaymentCaptureService::class);
        $capture = $data['payment_capture'] ?? null;
        $reason = $data['price_adjustment_reason'] ?? null;

        if (is_array($capture)) {
            $captureService->record(
                $booking,
                $priceAdjustment,
                $capture,
                BookingPaymentRecord::CONTEXT_MANUAL_BOOKING,
                'submitManualBooking',
                $createdBy,
                $this->normalizeUploadedFiles($data['payment_capture_uploads'] ?? []),
            );

            return;
        }

        $captureService->recordOptionalAdjustmentNote(
            $booking,
            $priceAdjustment,
            $reason,
            BookingPaymentRecord::CONTEXT_MANUAL_BOOKING,
            'submitManualBooking',
            $createdBy,
        );
    }

    private function persistMedicalReferralLetter(Booking $booking, mixed $letters, bool $isMedicalAccommodation): void
    {
        if (!$isMedicalAccommodation) {
            return;
        }

        $files = $this->normalizeUploadedFiles($letters);
        if ($files === []) {
            return;
        }

        $paths = $this->documents->storeMany(
            $files,
            'booking-documents/medical-referral/' . $booking->id,
        );
        if ($paths === []) {
            throw new \RuntimeException('ذخیره سند معرفی‌نامه با خطا مواجه شد.');
        }

        $booking->update(['medical_referral_letter_path' => $paths]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isCreditPayload(array $data): bool
    {
        if (!empty($data['is_credit'])) {
            return true;
        }

        return ($data['payment_method'] ?? null) === Booking::PAYMENT_CREDIT;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeCreditPayload(array $data): array
    {
        $data = $this->preserveProfileVeteranTypes($data);
        $data['payment_method'] = Booking::PAYMENT_CREDIT;
        $data['is_credit'] = true;
        $data['veteran_type'] = null;
        $data['secondary_veteran_type'] = null;
        $data['veteran_types'] = [];
        $data['guest_details'] = $this->sanitizeGuestsForMedicalAccommodation($data['guest_details'] ?? []);
        $data['services'] = $this->sanitizeServicesForMedicalAccommodation($data['services'] ?? []);

        return $data;
    }

    private function persistCreditLetter(Booking $booking, mixed $letters, bool $isCredit): void
    {
        if (!$isCredit) {
            return;
        }

        $files = $this->normalizeUploadedFiles($letters);
        if ($files === []) {
            return;
        }

        $paths = $this->documents->storeMany(
            $files,
            'booking-documents/credit-letter/' . $booking->id,
        );
        if ($paths === []) {
            throw new \RuntimeException('ذخیره سند معرفی‌نامه اعتباری با خطا مواجه شد.');
        }

        $booking->update(['credit_letter_path' => $paths]);
    }

    /**
     * @return list<UploadedFile>
     */
    private function normalizeUploadedFiles(mixed $input): array
    {
        if ($input instanceof UploadedFile) {
            return [$input];
        }

        if (!is_array($input)) {
            return [];
        }

        return array_values(array_filter(
            $input,
            static fn (mixed $file): bool => $file instanceof UploadedFile,
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function persistBeneficiaryCosts(Booking $booking, array $rows): void
    {
        foreach ($rows as $index => $row) {
            $beneficiaryId = (int) ($row['program_beneficiary_id'] ?? 0);
            if ($beneficiaryId <= 0) {
                continue;
            }

            $beneficiary = ProgramBeneficiary::find($beneficiaryId);
            if (!$beneficiary) {
                continue;
            }

            if (!$beneficiary->user_id) {
                $beneficiary = $this->beneficiaryUsers->linkBeneficiary($beneficiary);
            }

            $docs = $this->documents->storeMany(
                $row['documents'] ?? [],
                'booking-documents/beneficiary/' . $booking->id,
            );

            BookingBeneficiaryCost::create([
                'booking_id'              => $booking->id,
                'program_beneficiary_id'  => $beneficiary->id,
                'user_id'                 => $beneficiary->user_id,
                'debt_amount'             => (int) ($row['debt_amount'] ?? 0),
                'description'             => $row['description'] ?? null,
                'documents'               => $docs,
                'sort_order'              => $index,
            ]);
        }
    }
}
