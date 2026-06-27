<?php

namespace App\Services;

use App\Models\Accommodation;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;

class BookingPricingService
{
    public function __construct(
        private readonly VeteranPolicyService $veteranPolicy
    ) {}

    /**
     * @return array{
     *   nights:int, rooms_needed:int, billing_guests:int, children_under_6:int,
     *   room_subtotal:int, children_discount_amount:int, extra_guests_total:int, services_subtotal:int,
     *   services_discount_amount:int, accommodation_discount_percentage:int,
     *   subtotal_before_discount:int, discount_percentage:int,
     *   discount_amount:int, total_price:int,
     *   service_lines:array<int, array<string, mixed>>
     * }
     */
    public function calculate(array $params): array
    {
        $roomLines = $params['room_lines'] ?? null;
        if (is_array($roomLines) && count($roomLines) > 0) {
            return $this->calculateMultiRoom($params, $roomLines);
        }

        return $this->calculateSingleRoom($params);
    }

    private function policyFor(array $params): VeteranPolicyService
    {
        $accommodation = $params['accommodation'] ?? null;
        $accommodationId = $accommodation instanceof Accommodation ? $accommodation->id : null;

        return $this->veteranPolicy->forAccommodation($accommodationId);
    }

    /**
     * @param  array<int, array<string, mixed>>  $roomLines
     */
    public function calculateMultiRoom(array $params, array $roomLines): array
    {
        $checkIn = $params['check_in'];
        $checkOut = $params['check_out'];
        $services = $params['services'] ?? [];
        $veteranTypes = $this->resolveVeteranTypes($params);
        $veteranType = $veteranTypes[0] ?? null;
        $accommodation = $params['accommodation'];
        $remainingExcluded = max(0, (int) ($params['non_veteran_discount_guests'] ?? 0));
        $allGuestSlots = $params['per_guest_slots'] ?? null;
        $guestSlotOffset = 0;

        $accommodationDiscountPct = isset($params['discount_percentage'])
            ? (int) $params['discount_percentage']
            : $this->policyFor($params)->accommodationDiscountForTypes($veteranTypes);

        $bookingGuests = max(1, (int) ($params['guests'] ?? collect($roomLines)->sum(fn ($line) => (int) ($line['guests'] ?? 1))));
        $nights = (int) (new \DateTime($checkIn))->diff(new \DateTime($checkOut))->days;
        $accommodationNightPlan = $params['accommodation_night_plan']
            ?? $this->resolveAccommodationNightPlan($params, $nights, $veteranTypes, $bookingGuests);

        $aggregated = [
            'nights' => 0,
            'rooms_needed' => 0,
            'billing_guests' => 0,
            'children_under_6' => 0,
            'non_veteran_discount_guests' => 0,
            'room_subtotal' => 0,
            'children_discount_amount' => 0,
            'veteran_accommodation_discount_amount' => 0,
            'manual_accommodation_discount_amount' => 0,
            'extra_guests_total' => 0,
            'room_lines' => [],
        ];

        $mergedAccommodationBreakdown = [];

        foreach ($roomLines as $index => $line) {
            $roomType = $line['room_type'] ?? null;
            $roomRate = $line['room_rate'] ?? null;
            $guests = max(1, (int) ($line['guests'] ?? 1));
            $childrenUnder6 = max(0, (int) ($line['children_under_6'] ?? 0));
            $extraGuests = max(0, (int) ($line['extra_guests'] ?? 0));
            $billFullRooms = (bool) ($line['bill_full_rooms'] ?? false);

            $roomsNeeded = $this->roomsNeeded($guests, $extraGuests, $roomType, $childrenUnder6, $accommodation);
            $billingGuests = $this->billingGuests($guests, $extraGuests, $billFullRooms, $roomsNeeded, $roomType);
            $lineExcluded = min($remainingExcluded, $billingGuests);
            $remainingExcluded -= $lineExcluded;

            $lineGuestSlots = null;
            if (is_array($allGuestSlots) && count($allGuestSlots) > 0) {
                $lineGuestSlots = array_slice($allGuestSlots, $guestSlotOffset, $billingGuests);
                $guestSlotOffset += $billingGuests;
            }

            $linePricing = $this->calculateSingleRoom([
                'check_in'                    => $checkIn,
                'check_out'                   => $checkOut,
                'guests'                      => $guests,
                'children_under_6'            => $childrenUnder6,
                'extra_guests'                => $extraGuests,
                'bill_full_rooms'             => $billFullRooms,
                'veteran_type'                => $veteranType,
                'veteran_types'               => $veteranTypes,
                'discount_percentage'         => $accommodationDiscountPct,
                'accommodation_night_plan'    => $accommodationNightPlan,
                'services'                    => [],
                'accommodation'               => $accommodation,
                'room_type'                   => $roomType,
                'room_rate'                   => $roomRate,
                'national_id'                 => $params['national_id'] ?? null,
                'user_id'                     => $params['user_id'] ?? null,
                'exclude_booking_id'          => $params['exclude_booking_id'] ?? null,
                'non_veteran_discount_guests' => $lineExcluded,
                'per_guest_slots'             => $lineGuestSlots,
            ]);

            $aggregated['nights'] = $linePricing['nights'];
            $aggregated['rooms_needed'] += $linePricing['rooms_needed'];
            $aggregated['billing_guests'] += $linePricing['billing_guests'];
            $aggregated['children_under_6'] += $linePricing['children_under_6'];
            $aggregated['non_veteran_discount_guests'] += $linePricing['non_veteran_discount_guests'];
            $aggregated['room_subtotal'] += $linePricing['room_subtotal'];
            $aggregated['children_discount_amount'] += $linePricing['children_discount_amount'];
            $aggregated['veteran_accommodation_discount_amount'] += $linePricing['veteran_accommodation_discount_amount'];
            $aggregated['manual_accommodation_discount_amount'] += $linePricing['manual_accommodation_discount_amount'] ?? 0;
            $aggregated['extra_guests_total'] += $linePricing['extra_guests_total'];
            foreach ($linePricing['accommodation_discount_breakdown'] ?? [] as $item) {
                $groupKey = (string) ($item['veteran_group_key'] ?? '');
                if ($groupKey === '') {
                    continue;
                }
                if (!isset($mergedAccommodationBreakdown[$groupKey])) {
                    $mergedAccommodationBreakdown[$groupKey] = $item;
                    continue;
                }
                $mergedAccommodationBreakdown[$groupKey]['discount_amount'] += (int) ($item['discount_amount'] ?? 0);
            }
            $aggregated['room_lines'][] = array_merge($linePricing, [
                'sort_order'      => $index,
                'room_type_id'    => $roomType?->id,
                'room_rate_id'    => $roomRate?->id,
                'guests'          => $guests,
                'children_under_6'=> $childrenUnder6,
                'extra_guests'    => $extraGuests,
                'bill_full_rooms' => $billFullRooms,
            ]);
        }

        $enrichedServices = $this->policyFor($params)->enrichServicesWithDiscountsForTypes($veteranTypes, $services);
        $serviceLines = $this->calculateServiceLines($enrichedServices, [
            'veteran_types'      => $veteranTypes,
            'veteran_type'       => $veteranType,
            'national_id'        => $params['national_id'] ?? null,
            'user_id'            => isset($params['user_id']) ? (int) $params['user_id'] : null,
            'reference_date'     => $checkIn,
            'exclude_booking_id' => isset($params['exclude_booking_id']) ? (int) $params['exclude_booking_id'] : null,
            'accommodation_id'   => ($params['accommodation'] ?? null) instanceof Accommodation ? $params['accommodation']->id : null,
        ]);
        $servicesSubtotal = collect($serviceLines)->sum('line_subtotal');
        $servicesDiscountAmount = collect($serviceLines)->sum('discount_amount');

        $accommodationSubtotal = $aggregated['room_subtotal'] + $aggregated['extra_guests_total'];
        $accommodationDiscountAmount = $aggregated['veteran_accommodation_discount_amount']
            + $aggregated['manual_accommodation_discount_amount'];
        $subtotal = $accommodationSubtotal + $servicesSubtotal;
        $totalDiscount = $accommodationDiscountAmount + $servicesDiscountAmount;
        $totalPrice = $subtotal - $totalDiscount;
        $effectiveDiscountPct = $subtotal > 0
            ? (int) round($totalDiscount / $subtotal * 100)
            : 0;

        $veteranDiscountNights = $this->countDiscountedNights($accommodationNightPlan['night_discounts'] ?? []);

        return [
            'nights'                          => $aggregated['nights'],
            'rooms_needed'                    => $aggregated['rooms_needed'],
            'billing_guests'                  => $aggregated['billing_guests'],
            'children_under_6'                => $aggregated['children_under_6'],
            'children_under_6_discount_percentage' => $accommodation->childrenUnder6DiscountPercentage(),
            'non_veteran_discount_guests'     => $aggregated['non_veteran_discount_guests'],
            'room_subtotal'                   => $aggregated['room_subtotal'],
            'children_discount_amount'        => $aggregated['children_discount_amount'],
            'veteran_accommodation_discount_amount' => $aggregated['veteran_accommodation_discount_amount'],
            'manual_accommodation_discount_amount'  => $aggregated['manual_accommodation_discount_amount'],
            'veteran_discount_nights'         => $veteranDiscountNights,
            'veteran_accommodation_group_usage' => $accommodationNightPlan['group_usage'] ?? [],
            'accommodation_discount_breakdown'  => array_values($mergedAccommodationBreakdown),
            'extra_guests_total'              => $aggregated['extra_guests_total'],
            'services_subtotal'               => $servicesSubtotal,
            'services_discount_amount'        => $servicesDiscountAmount,
            'accommodation_discount_percentage' => $accommodationDiscountPct,
            'subtotal_before_discount'        => $subtotal,
            'discount_percentage'             => $effectiveDiscountPct,
            'discount_amount'                 => $totalDiscount,
            'total_price'                     => $totalPrice,
            'service_lines'                   => $serviceLines,
            'room_lines'                      => $aggregated['room_lines'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $guestDetails
     * @return array<int, array{is_child:bool, veteran_eligible:bool, manual_discount_pct:int}>
     */
    public function buildPerGuestSlotsFromGuestDetails(
        array $guestDetails,
        int $billingGuests,
        int $childrenUnder6,
        ?string $veteranType,
        int $veteranDiscountPct,
    ): array {
        $billingGuests = max(1, $billingGuests);
        $childrenUnder6 = min(max(0, $childrenUnder6), $billingGuests);
        $adultCount = $billingGuests - $childrenUnder6;
        $slots = [];

        for ($i = 0; $i < $billingGuests; $i++) {
            $guest = $guestDetails[$i] ?? [];
            $excluded = !empty($guest['excluded_from_veteran_discount']);
            $veteranEligible = $veteranType && $veteranDiscountPct > 0 && !$excluded;
            $manualPct = 0;

            if (!$veteranEligible) {
                $raw = $guest['manual_discount_percentage'] ?? '';
                if ($raw !== '' && $raw !== null) {
                    $manualPct = max(0, min(100, (int) $raw));
                }
            }

            $slots[] = [
                'is_child'             => $i >= $adultCount,
                'veteran_eligible'     => $veteranEligible,
                'manual_discount_pct'  => $manualPct,
            ];
        }

        return $slots;
    }

    /**
     * @return array{
     *   nights:int, rooms_needed:int, billing_guests:int, children_under_6:int,
     *   room_subtotal:int, children_discount_amount:int, extra_guests_total:int, services_subtotal:int,
     *   services_discount_amount:int, accommodation_discount_percentage:int,
     *   subtotal_before_discount:int, discount_percentage:int,
     *   discount_amount:int, total_price:int,
     *   service_lines:array<int, array<string, mixed>>
     * }
     */
    private function calculateSingleRoom(array $params): array
    {
        $checkIn       = $params['check_in'];
        $checkOut      = $params['check_out'];
        $guests           = (int) ($params['guests'] ?? 1);
        $childrenUnder6     = max(0, (int) ($params['children_under_6'] ?? 0));
        $extraGuests        = (int) ($params['extra_guests'] ?? 0);
        $billFullRooms      = (bool) ($params['bill_full_rooms'] ?? false);
        $services      = $params['services'] ?? [];
        $veteranTypes  = $this->resolveVeteranTypes($params);
        $veteranType   = $veteranTypes[0] ?? null;
        $nonDiscountGuests = max(0, (int) ($params['non_veteran_discount_guests'] ?? 0));

        $accommodationDiscountPct = isset($params['discount_percentage'])
            ? (int) $params['discount_percentage']
            : $this->policyFor($params)->accommodationDiscountForTypes($veteranTypes);

        $accommodation = $params['accommodation'];
        $roomType      = $params['room_type'] ?? null;
        $roomRate      = $params['room_rate'] ?? null;

        $nights = (int) (new \DateTime($checkIn))->diff(new \DateTime($checkOut))->days;
        $bookingGuests = max(1, (int) ($params['guests'] ?? 1));
        $accommodationNightPlan = $params['accommodation_night_plan']
            ?? $this->resolveAccommodationNightPlan($params, $nights, $veteranTypes, $bookingGuests);
        $nightDiscounts = $accommodationNightPlan['night_discounts'] ?? array_fill(0, $nights, 0);
        $nightGroupKeys = $accommodationNightPlan['night_group_keys'] ?? array_fill(0, $nights, null);
        $veteranDiscountNights = $this->countDiscountedNights($nightDiscounts);
        $pricePerNight = $roomRate ? $roomRate->price_per_night : $accommodation->price_per_night;

        $roomsNeeded = $this->roomsNeeded($guests, $extraGuests, $roomType, $childrenUnder6, $accommodation);
        $childDiscountPct = $accommodation->childrenUnder6DiscountPercentage();

        $extraGuestsTotal = 0;
        if ($extraGuests > 0 && $roomType && $roomType->extra_capacity_price) {
            $extraGuestsTotal = $extraGuests * (int) $roomType->extra_capacity_price * $nights;
        }

        $billingGuests = $this->billingGuests($guests, $extraGuests, $billFullRooms, $roomsNeeded, $roomType);
        $childrenUnder6 = min($childrenUnder6, max(0, $billingGuests));
        $nonDiscountGuests = min($nonDiscountGuests, $billingGuests);
        $perGuestSlots = $params['per_guest_slots'] ?? null;
        if (is_array($perGuestSlots) && count($perGuestSlots) > 0) {
            $perGuestSlots = array_slice($perGuestSlots, 0, $billingGuests);
            $nonDiscountGuests = collect($perGuestSlots)
                ->filter(fn ($slot) => empty($slot['veteran_eligible']))
                ->count();
        }

        $availMap = $roomType ? $roomType->availabilityMap($checkIn, $checkOut) : [];
        $roomSubtotal = 0;
        $roomAfterAllDiscounts = 0;
        $childrenDiscountAmount = 0;
        $manualAccommodationDiscount = 0;
        $groupVeteranDiscountAmounts = [];
        $cursor = new \DateTime($checkIn);
        $endDate = new \DateTime($checkOut);
        $nightIndex = 0;

        while ($cursor < $endDate) {
            $dayKey = $cursor->format('Y-m-d');
            $dayData = $availMap[$dayKey] ?? null;
            $nightPrice = ($dayData && isset($dayData['effective_price']) && $dayData['effective_price'] !== null)
                ? (int) $dayData['effective_price']
                : $pricePerNight;

            $nightVeteranPct = (int) ($nightDiscounts[$nightIndex] ?? 0);

            if (is_array($perGuestSlots) && count($perGuestSlots) > 0) {
                $nightBreakdown = $this->accommodationNightBreakdownPerGuest(
                    $nightPrice,
                    $perGuestSlots,
                    $nightVeteranPct,
                    $childDiscountPct,
                );
            } else {
                $nightBreakdown = $this->accommodationNightBreakdown(
                    $nightPrice,
                    $billingGuests,
                    $childrenUnder6,
                    $nonDiscountGuests,
                    $nightVeteranPct,
                    $childDiscountPct,
                );
            }

            $roomSubtotal += $nightBreakdown['gross'];
            $roomAfterAllDiscounts += $nightBreakdown['net'];
            $childrenDiscountAmount += $nightBreakdown['children_discount_amount'];
            $manualAccommodationDiscount += $nightBreakdown['manual_discount_amount'] ?? 0;

            $nightGroupKey = $nightGroupKeys[$nightIndex] ?? null;
            $nightVeteranAmount = (int) ($nightBreakdown['veteran_discount_amount'] ?? 0);
            if ($nightGroupKey && $nightVeteranAmount > 0) {
                $groupVeteranDiscountAmounts[$nightGroupKey] = ($groupVeteranDiscountAmounts[$nightGroupKey] ?? 0)
                    + $nightVeteranAmount;
            }

            $nightIndex++;
            $cursor->modify('+1 day');
        }

        $roomTotalDiscount = $roomSubtotal - $roomAfterAllDiscounts;
        $roomVeteranDiscount = max(0, $roomTotalDiscount - $manualAccommodationDiscount);

        $enrichedServices = $this->policyFor($params)->enrichServicesWithDiscountsForTypes($veteranTypes, $services);
        $serviceLines = $this->calculateServiceLines($enrichedServices, [
            'veteran_types'      => $veteranTypes,
            'veteran_type'       => $veteranType,
            'national_id'        => $params['national_id'] ?? null,
            'user_id'            => isset($params['user_id']) ? (int) $params['user_id'] : null,
            'reference_date'     => $checkIn,
            'exclude_booking_id' => isset($params['exclude_booking_id']) ? (int) $params['exclude_booking_id'] : null,
            'accommodation_id'   => ($params['accommodation'] ?? null) instanceof Accommodation ? $params['accommodation']->id : null,
        ]);
        $servicesSubtotal = collect($serviceLines)->sum('line_subtotal');
        $servicesDiscountAmount = collect($serviceLines)->sum('discount_amount');

        $accommodationSubtotal = $roomSubtotal + $extraGuestsTotal;
        $discountEligibleRatio = $billingGuests > 0
            ? ($billingGuests - $nonDiscountGuests) / $billingGuests
            : 1.0;
        $extraPerNight = ($extraGuests > 0 && $roomType && $roomType->extra_capacity_price)
            ? $extraGuests * (int) $roomType->extra_capacity_price
            : 0;
        $extraVeteranDiscount = 0;
        if ($extraPerNight > 0 && $discountEligibleRatio > 0) {
            foreach ($nightDiscounts as $nightIdx => $nightPct) {
                if ($nightPct <= 0) {
                    continue;
                }
                $nightExtraDiscount = (int) round($extraPerNight * $nightPct / 100 * $discountEligibleRatio);
                $extraVeteranDiscount += $nightExtraDiscount;
                $nightGroupKey = $nightGroupKeys[$nightIdx] ?? null;
                if ($nightGroupKey && $nightExtraDiscount > 0) {
                    $groupVeteranDiscountAmounts[$nightGroupKey] = ($groupVeteranDiscountAmounts[$nightGroupKey] ?? 0)
                        + $nightExtraDiscount;
                }
            }
        }
        $veteranAccommodationDiscount = $roomVeteranDiscount + $extraVeteranDiscount;
        $accommodationDiscountBreakdown = $this->buildAccommodationDiscountBreakdown(
            $accommodationNightPlan['group_usage'] ?? [],
            $groupVeteranDiscountAmounts,
            $nightDiscounts,
            $nightGroupKeys,
            $this->policyFor($params),
        );
        $accommodationDiscountAmount = $veteranAccommodationDiscount + $manualAccommodationDiscount;

        $subtotal = $accommodationSubtotal + $servicesSubtotal;
        $totalDiscount = $accommodationDiscountAmount + $servicesDiscountAmount;
        $totalPrice = $subtotal - $totalDiscount;

        $effectiveDiscountPct = $subtotal > 0
            ? (int) round($totalDiscount / $subtotal * 100)
            : 0;

        return [
            'nights'                          => $nights,
            'rooms_needed'                    => $roomsNeeded,
            'billing_guests'                  => $billingGuests,
            'children_under_6'                => $childrenUnder6,
            'children_under_6_discount_percentage' => $childDiscountPct,
            'non_veteran_discount_guests'     => $nonDiscountGuests,
            'room_subtotal'                   => $roomSubtotal,
            'children_discount_amount'        => $childrenDiscountAmount,
            'veteran_accommodation_discount_amount' => $veteranAccommodationDiscount,
            'manual_accommodation_discount_amount'  => $manualAccommodationDiscount,
            'veteran_discount_nights'           => $veteranDiscountNights,
            'veteran_accommodation_group_usage' => $accommodationNightPlan['group_usage'] ?? [],
            'accommodation_discount_breakdown'  => $accommodationDiscountBreakdown,
            'extra_guests_total'              => $extraGuestsTotal,
            'services_subtotal'               => $servicesSubtotal,
            'services_discount_amount'        => $servicesDiscountAmount,
            'accommodation_discount_percentage' => $accommodationDiscountPct,
            'subtotal_before_discount'        => $subtotal,
            'discount_percentage'             => $effectiveDiscountPct,
            'discount_amount'                 => $totalDiscount,
            'total_price'                     => $totalPrice,
            'service_lines'                   => $serviceLines,
        ];
    }

    /**
     * @param  array<int, string>  $veteranTypes
     * @return array{night_discounts: array<int, int>, group_usage: array<string, int>}
     */
    private function resolveAccommodationNightPlan(
        array $params,
        int $nights,
        array $veteranTypes,
        int $guests,
    ): array {
        if ($nights <= 0 || empty($veteranTypes)) {
            return [
                'night_discounts'  => array_fill(0, max(0, $nights), 0),
                'night_group_keys' => array_fill(0, max(0, $nights), null),
                'group_usage'      => [],
            ];
        }

        if (array_key_exists('veteran_discount_nights', $params)) {
            $discounted = min($nights, max(0, (int) $params['veteran_discount_nights']));
            $pct = isset($params['discount_percentage'])
                ? (int) $params['discount_percentage']
                : $this->policyFor($params)->accommodationDiscountForTypes($veteranTypes);
            $nightDiscounts = [];
            $nightGroupKeys = [];
            for ($i = 0; $i < $nights; $i++) {
                $nightDiscounts[] = $i < $discounted ? $pct : 0;
                $nightGroupKeys[] = $i < $discounted ? ($veteranTypes[0] ?? null) : null;
            }
            $groupUsage = ($discounted > 0 && !empty($veteranTypes[0]))
                ? [$veteranTypes[0] => $discounted]
                : [];

            return [
                'night_discounts'  => $nightDiscounts,
                'night_group_keys' => $nightGroupKeys,
                'group_usage'      => $groupUsage,
            ];
        }

        return $this->policyFor($params)->accommodationNightPlan(
            $veteranTypes,
            max(1, $guests),
            $nights,
            $params['national_id'] ?? null,
            isset($params['user_id']) ? (int) $params['user_id'] : null,
            isset($params['exclude_booking_id']) ? (int) $params['exclude_booking_id'] : null,
        );
    }

    /**
     * @param  array<int, int>  $nightDiscounts
     */
    private function countDiscountedNights(array $nightDiscounts): int
    {
        return count(array_filter($nightDiscounts, fn (int $pct) => $pct > 0));
    }

    private function resolveVeteranDiscountNights(array $params, int $nights, ?string $veteranType): int
    {
        $veteranTypes = $this->resolveVeteranTypes($params);
        if (empty($veteranTypes) || $nights <= 0) {
            return 0;
        }

        $plan = $this->resolveAccommodationNightPlan(
            $params,
            $nights,
            $veteranTypes,
            max(1, (int) ($params['guests'] ?? 1)),
        );

        return $this->countDiscountedNights($plan['night_discounts']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $services
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    private function calculateServiceLines(array $services, array $context = []): array
    {
        $lines = [];
        $veteranTypes = $context['veteran_types'] ?? [];
        if (empty($veteranTypes) && !empty($context['veteran_type'])) {
            $veteranTypes = [$context['veteran_type']];
        }
        $veteranType = $veteranTypes[0] ?? ($context['veteran_type'] ?? null);
        $nationalId = $context['national_id'] ?? null;
        $userId = $context['user_id'] ?? null;
        $referenceDate = $context['reference_date'] ?? now()->format('Y-m-d');
        $excludeBookingId = $context['exclude_booking_id'] ?? null;
        $policy = $this->veteranPolicy->forAccommodation($context['accommodation_id'] ?? null);
        $multiGroup = count($veteranTypes) > 1;

        // Track sessions already consumed per service within this booking.
        $sessionsUsedByService = [];
        $groupUsageByService = [];

        foreach ($services as $service) {
            if (empty(trim($service['name'] ?? ''))) {
                continue;
            }

            $qty  = max(1, (int) ($service['quantity'] ?? 1));
            $unit = max(0, (int) ($service['unit_price'] ?? 0));
            $lineSubtotal = $qty * $unit;

            $serviceKey = $service['service_catalog_id'] ?? trim($service['name']);
            $catalogId = isset($service['service_catalog_id']) ? (int) $service['service_catalog_id'] : null;

            $groupConfigs = $multiGroup && $catalogId
                ? $policy->groupDiscountConfigsForService($veteranTypes, $catalogId)
                : [];

            $useTiered = (bool) ($service['use_tiered_discount'] ?? false);
            $tiers = $service['discount_tiers'] ?? [];
            $hasMultiGroupRules = $multiGroup && !empty($groupConfigs);

            if ($hasMultiGroupRules || ($multiGroup && $catalogId)) {
                $weeklyConsumed = $catalogId
                    ? $policy->weeklyConsumedPerGroupForService(
                        $veteranTypes,
                        $nationalId,
                        $userId,
                        $catalogId,
                        $referenceDate,
                        $excludeBookingId,
                    )
                    : array_fill_keys($veteranTypes, 0);
                $bookingConsumed = $groupUsageByService[$serviceKey] ?? array_fill_keys($veteranTypes, 0);

                $multiResult = MultiGroupServiceDiscountEngine::calculateLine(
                    $unit,
                    $qty,
                    $groupConfigs,
                    $weeklyConsumed,
                    $bookingConsumed,
                );

                $groupUsageByService[$serviceKey] = $multiResult['group_usage'];
                $sessionsUsedByService[$serviceKey] = ($sessionsUsedByService[$serviceKey] ?? 0) + $qty;

                $lines[] = [
                    'name'                       => trim($service['name']),
                    'service_catalog_id'         => $service['service_catalog_id'] ?? null,
                    'service_catalog_variant_id' => $service['service_catalog_variant_id'] ?? null,
                    'unit_price'                 => $unit,
                    'quantity'                   => $qty,
                    'line_subtotal'              => $lineSubtotal,
                    'discount_percentage'        => $multiResult['effective_discount_percentage'],
                    'discount_amount'            => $multiResult['discount_amount'],
                    'line_total'                 => $lineSubtotal - $multiResult['discount_amount'],
                    'free_sessions_eligible'     => $multiResult['free_units'] > 0,
                    'free_units'                 => $multiResult['free_units'],
                    'use_tiered_discount'        => collect($groupConfigs)->contains(fn ($g) => !empty($g['use_tiered_discount'])),
                    'discount_breakdown'         => $multiResult['discount_breakdown'],
                    'veteran_group_usage'        => collect($multiResult['group_usage'])
                        ->mapWithKeys(fn ($count, $key) => [$key => max(0, (int) $count - (int) ($bookingConsumed[$key] ?? 0))])
                        ->filter(fn ($count) => $count > 0)
                        ->all(),
                ];

                continue;
            }

            if ($useTiered && !empty($tiers)) {
                $alreadyInBooking = $sessionsUsedByService[$serviceKey] ?? 0;
                $alreadyThisWeek = $catalogId
                    ? $policy->usedServiceSessionsInWeek(
                        $veteranType,
                        $nationalId,
                        $userId,
                        $catalogId,
                        $referenceDate,
                        $excludeBookingId,
                    )
                    : 0;

                $tierResult = ServiceDiscountTierEngine::calculateLine(
                    $unit,
                    $qty,
                    $alreadyThisWeek,
                    $alreadyInBooking,
                    $tiers,
                );

                $sessionsUsedByService[$serviceKey] = $alreadyInBooking + $qty;
                $freeUnits = $tierResult['free_units'];
                $discountAmount = $tierResult['discount_amount'];
                $discountPct = $tierResult['effective_discount_percentage'];
                $freeEligible = ServiceDiscountTierEngine::freeTierQuota($tiers) > 0;

                $lines[] = [
                    'name'                       => trim($service['name']),
                    'service_catalog_id'         => $service['service_catalog_id'] ?? null,
                    'service_catalog_variant_id' => $service['service_catalog_variant_id'] ?? null,
                    'unit_price'                 => $unit,
                    'quantity'                   => $qty,
                    'line_subtotal'              => $lineSubtotal,
                    'discount_percentage'        => $discountPct,
                    'discount_amount'            => $discountAmount,
                    'line_total'                 => $lineSubtotal - $discountAmount,
                    'free_sessions_eligible'     => $freeEligible,
                    'free_units'                 => $freeUnits,
                    'use_tiered_discount'        => true,
                    'discount_breakdown'         => $tierResult['discount_breakdown'] ?? [],
                ];

                continue;
            }

            $discountPct  = (int) ($service['discount_percentage'] ?? 0);
            $freeEligible = (bool) ($service['free_sessions_eligible'] ?? false);
            $weeklyFree   = (int) ($service['weekly_free_sessions'] ?? 0);

            $alreadyFreeInBooking = $sessionsUsedByService[$serviceKey] ?? 0;
            $alreadyFreeThisWeek = ($freeEligible && $catalogId)
                ? $policy->usedFreeSessionsInWeek(
                    $veteranType,
                    $nationalId,
                    $userId,
                    $catalogId,
                    $referenceDate,
                    $excludeBookingId,
                )
                : 0;

            $remainingFree = max(0, $weeklyFree - $alreadyFreeThisWeek - $alreadyFreeInBooking);

            $freeUnits = ($freeEligible && $weeklyFree > 0) ? min($qty, $remainingFree) : 0;
            $paidUnits = $qty - $freeUnits;

            if ($freeEligible && $freeUnits > 0) {
                $sessionsUsedByService[$serviceKey] = $alreadyFreeInBooking + $freeUnits;
            }

            $freeDiscount   = $freeUnits * $unit;
            $paidDiscount   = (int) round($paidUnits * $unit * $discountPct / 100);
            $discountAmount = $freeDiscount + $paidDiscount;
            $legacyBreakdown = ServiceDiscountTierEngine::legacyLineBreakdown($unit, $qty, $freeUnits, $discountPct);

            $lines[] = [
                'name'                       => trim($service['name']),
                'service_catalog_id'         => $service['service_catalog_id'] ?? null,
                'service_catalog_variant_id' => $service['service_catalog_variant_id'] ?? null,
                'unit_price'                 => $unit,
                'quantity'               => $qty,
                'line_subtotal'          => $lineSubtotal,
                'discount_percentage'    => $discountPct,
                'discount_amount'        => $discountAmount,
                'line_total'             => $lineSubtotal - $discountAmount,
                'free_sessions_eligible' => $freeEligible,
                'free_units'             => $freeUnits,
                'use_tiered_discount'    => false,
                'discount_breakdown'     => $legacyBreakdown['discount_breakdown'] ?? [],
            ];
        }

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    private function resolveVeteranTypes(array $params): array
    {
        $policy = $this->policyFor($params);

        if (!empty($params['veteran_types']) && is_array($params['veteran_types'])) {
            return $policy->normalizeVeteranTypes($params['veteran_types']);
        }

        return $policy->normalizeVeteranTypes(
            $params['veteran_type'] ?? null,
            $params['secondary_veteran_type'] ?? null,
        );
    }

    public function guestsForBedAllocation(int $guests, int $childrenUnder6, ?Accommodation $accommodation): int
    {
        $guests = max(1, $guests);
        $childrenUnder6 = max(0, min($childrenUnder6, $guests - 1));

        if (!$accommodation || $accommodation->childrenUnder6AllocateBed()) {
            return $guests;
        }

        return max(1, $guests - $childrenUnder6);
    }

    public function roomsNeeded(
        int $guests,
        int $extraGuests,
        ?RoomType $roomType,
        int $childrenUnder6 = 0,
        ?Accommodation $accommodation = null,
    ): int {
        if (!$roomType) {
            return 1;
        }

        $capacity = max(1, (int) $roomType->capacity);
        $bedGuests = $this->guestsForBedAllocation($guests, $childrenUnder6, $accommodation);

        if ($extraGuests > 0) {
            $standardGuests = $bedGuests - $extraGuests;

            return max(1, (int) ceil($standardGuests / $capacity));
        }

        return (int) ceil($bedGuests / $capacity);
    }

    public function billingGuests(
        int $guests,
        int $extraGuests,
        bool $billFullRooms,
        int $roomsNeeded,
        ?RoomType $roomType
    ): int {
        if ($billFullRooms && $roomType) {
            return $roomsNeeded * max(1, (int) $roomType->capacity);
        }

        $capacity = max(1, (int) ($roomType?->capacity ?? 1));

        // Single-line bookings store total headcount in guests (including floor sleepers).
        // Split room lines store bed-only counts per line; extra_guests is separate.
        if ($extraGuests > 0 && $guests > $capacity) {
            return max(1, $guests - $extraGuests);
        }

        return max(1, $guests);
    }

    public function servicesSubtotal(array $services): int
    {
        $total = 0;
        foreach ($services as $service) {
            $qty = max(1, (int) ($service['quantity'] ?? 1));
            $price = max(0, (int) ($service['unit_price'] ?? 0));
            $total += $qty * $price;
        }

        return $total;
    }

    public function discountForUser(?User $user): int
    {
        return $user?->discount_percentage ?? 0;
    }

    /**
     * @return array{gross:int, net:int, children_discount_amount:int}
     */
    private function accommodationNightBreakdown(
        int $nightPrice,
        int $billingGuests,
        int $childrenUnder6,
        int $nonDiscountGuests,
        int $veteranDiscountPct,
        int $childDiscountPct = 50,
    ): array {
        $childMultiplier = (100 - max(0, min(100, $childDiscountPct))) / 100;
        $nonDiscount = min(max(0, $nonDiscountGuests), $billingGuests);
        $children = min(max(0, $childrenUnder6), $billingGuests);
        $fullRate = $billingGuests - $children;

        $nonDiscountChildren = min($nonDiscount, $children);
        $nonDiscountAdults = $nonDiscount - $nonDiscountChildren;
        $discountChildren = $children - $nonDiscountChildren;
        $discountAdults = $fullRate - $nonDiscountAdults;

        $gross = ($fullRate + $children) * $nightPrice;
        $childrenDiscountAmount = (int) round($nightPrice * (1 - $childMultiplier) * $children);

        $net = $nonDiscountAdults * $nightPrice
            + (int) round($nightPrice * $childMultiplier * $nonDiscountChildren);

        if ($veteranDiscountPct > 0) {
            $net += (int) round($nightPrice * (100 - $veteranDiscountPct) / 100 * $discountAdults);
            $net += (int) round($nightPrice * $childMultiplier * (100 - $veteranDiscountPct) / 100 * $discountChildren);
            $veteranDiscountAmount = (int) round($discountAdults * $nightPrice * $veteranDiscountPct / 100)
                + (int) round($nightPrice * $childMultiplier * $discountChildren * $veteranDiscountPct / 100);
        } else {
            $net += $discountAdults * $nightPrice;
            $net += (int) round($nightPrice * $childMultiplier * $discountChildren);
            $veteranDiscountAmount = 0;
        }

        return [
            'gross'                    => $gross - $childrenDiscountAmount,
            'net'                      => $net,
            'children_discount_amount' => $childrenDiscountAmount,
            'veteran_discount_amount'  => $veteranDiscountAmount,
        ];
    }

    /**
     * @param  array<int, array{is_child:bool, veteran_eligible:bool, manual_discount_pct:int}>  $guestSlots
     * @return array{gross:int, net:int, children_discount_amount:int, manual_discount_amount:int}
     */
    private function accommodationNightBreakdownPerGuest(
        int $nightPrice,
        array $guestSlots,
        int $veteranDiscountPct,
        int $childDiscountPct = 50,
    ): array {
        $childMultiplier = (100 - max(0, min(100, $childDiscountPct))) / 100;
        $gross = 0;
        $net = 0;
        $childrenDiscountAmount = 0;
        $manualDiscountAmount = 0;
        $veteranDiscountAmount = 0;

        foreach ($guestSlots as $slot) {
            $isChild = !empty($slot['is_child']);
            $guestGross = $isChild
                ? (int) round($nightPrice * $childMultiplier)
                : $nightPrice;

            if ($isChild) {
                $childrenDiscountAmount += (int) round($nightPrice * (1 - $childMultiplier));
            }

            $gross += $guestGross;

            $afterBase = $guestGross;
            $veteranEligible = !empty($slot['veteran_eligible']);
            $manualPct = max(0, min(100, (int) ($slot['manual_discount_pct'] ?? 0)));

            if ($veteranEligible && $veteranDiscountPct > 0) {
                $afterBase = (int) round($guestGross * (100 - $veteranDiscountPct) / 100);
                $veteranDiscountAmount += $guestGross - $afterBase;
            } elseif ($manualPct > 0) {
                $afterManual = (int) round($guestGross * (100 - $manualPct) / 100);
                $manualDiscountAmount += $guestGross - $afterManual;
                $afterBase = $afterManual;
            }

            $net += $afterBase;
        }

        return [
            'gross'                    => $gross,
            'net'                      => $net,
            'children_discount_amount' => $childrenDiscountAmount,
            'manual_discount_amount'   => $manualDiscountAmount,
            'veteran_discount_amount'  => $veteranDiscountAmount,
        ];
    }

    /**
     * @param  array<string, int>  $groupUsage
     * @param  array<string, int>  $groupDiscountAmounts
     * @param  array<int, int>  $nightDiscounts
     * @param  array<int, string|null>  $nightGroupKeys
     * @return array<int, array<string, mixed>>
     */
    private function buildAccommodationDiscountBreakdown(
        array $groupUsage,
        array $groupDiscountAmounts,
        array $nightDiscounts,
        array $nightGroupKeys,
        VeteranPolicyService $policy,
    ): array {
        if (empty($groupUsage)) {
            return [];
        }

        $pctByGroup = [];
        foreach ($nightDiscounts as $idx => $pct) {
            $key = $nightGroupKeys[$idx] ?? null;
            if ($key && $pct > 0) {
                $pctByGroup[$key] = $pct;
            }
        }

        $breakdown = [];
        foreach ($groupUsage as $groupKey => $units) {
            if ($units <= 0) {
                continue;
            }

            $group = $policy->groupByKey($groupKey);
            $breakdown[] = [
                'veteran_group_key'     => $groupKey,
                'veteran_group_label'   => $group?->label ?? $groupKey,
                'units'                 => (int) $units,
                'discount_percentage'   => (int) ($pctByGroup[$groupKey] ?? 0),
                'discount_amount'       => (int) ($groupDiscountAmounts[$groupKey] ?? 0),
            ];
        }

        return $breakdown;
    }
}
