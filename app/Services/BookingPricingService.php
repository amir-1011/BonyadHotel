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

    /**
     * @param  array<int, array<string, mixed>>  $roomLines
     */
    public function calculateMultiRoom(array $params, array $roomLines): array
    {
        $checkIn = $params['check_in'];
        $checkOut = $params['check_out'];
        $services = $params['services'] ?? [];
        $veteranType = $params['veteran_type'] ?? null;
        $accommodation = $params['accommodation'];
        $remainingExcluded = max(0, (int) ($params['non_veteran_discount_guests'] ?? 0));

        $accommodationDiscountPct = isset($params['discount_percentage'])
            ? (int) $params['discount_percentage']
            : $this->veteranPolicy->accommodationDiscount($veteranType);

        $aggregated = [
            'nights' => 0,
            'rooms_needed' => 0,
            'billing_guests' => 0,
            'children_under_6' => 0,
            'non_veteran_discount_guests' => 0,
            'room_subtotal' => 0,
            'children_discount_amount' => 0,
            'veteran_accommodation_discount_amount' => 0,
            'extra_guests_total' => 0,
            'room_lines' => [],
        ];

        foreach ($roomLines as $index => $line) {
            $roomType = $line['room_type'] ?? null;
            $roomRate = $line['room_rate'] ?? null;
            $guests = max(1, (int) ($line['guests'] ?? 1));
            $childrenUnder6 = max(0, (int) ($line['children_under_6'] ?? 0));
            $extraGuests = max(0, (int) ($line['extra_guests'] ?? 0));
            $billFullRooms = (bool) ($line['bill_full_rooms'] ?? false);

            $roomsNeeded = $this->roomsNeeded($guests, $extraGuests, $roomType);
            $billingGuests = $this->billingGuests($guests, $extraGuests, $billFullRooms, $roomsNeeded, $roomType);
            $lineExcluded = min($remainingExcluded, $billingGuests);
            $remainingExcluded -= $lineExcluded;

            $linePricing = $this->calculateSingleRoom([
                'check_in'                    => $checkIn,
                'check_out'                   => $checkOut,
                'guests'                      => $guests,
                'children_under_6'            => $childrenUnder6,
                'extra_guests'                => $extraGuests,
                'bill_full_rooms'             => $billFullRooms,
                'veteran_type'                => $veteranType,
                'discount_percentage'         => $accommodationDiscountPct,
                'services'                    => [],
                'accommodation'               => $accommodation,
                'room_type'                   => $roomType,
                'room_rate'                   => $roomRate,
                'national_id'                 => $params['national_id'] ?? null,
                'user_id'                     => $params['user_id'] ?? null,
                'exclude_booking_id'          => $params['exclude_booking_id'] ?? null,
                'non_veteran_discount_guests' => $lineExcluded,
            ]);

            $aggregated['nights'] = $linePricing['nights'];
            $aggregated['rooms_needed'] += $linePricing['rooms_needed'];
            $aggregated['billing_guests'] += $linePricing['billing_guests'];
            $aggregated['children_under_6'] += $linePricing['children_under_6'];
            $aggregated['non_veteran_discount_guests'] += $linePricing['non_veteran_discount_guests'];
            $aggregated['room_subtotal'] += $linePricing['room_subtotal'];
            $aggregated['children_discount_amount'] += $linePricing['children_discount_amount'];
            $aggregated['veteran_accommodation_discount_amount'] += $linePricing['veteran_accommodation_discount_amount'];
            $aggregated['extra_guests_total'] += $linePricing['extra_guests_total'];
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

        $enrichedServices = $this->veteranPolicy->enrichServicesWithDiscounts($veteranType, $services);
        $serviceLines = $this->calculateServiceLines($enrichedServices, [
            'veteran_type'       => $veteranType,
            'national_id'        => $params['national_id'] ?? null,
            'user_id'            => isset($params['user_id']) ? (int) $params['user_id'] : null,
            'reference_date'     => $checkIn,
            'exclude_booking_id' => isset($params['exclude_booking_id']) ? (int) $params['exclude_booking_id'] : null,
        ]);
        $servicesSubtotal = collect($serviceLines)->sum('line_subtotal');
        $servicesDiscountAmount = collect($serviceLines)->sum('discount_amount');

        $accommodationSubtotal = $aggregated['room_subtotal'] + $aggregated['extra_guests_total'];
        $accommodationDiscountAmount = $aggregated['veteran_accommodation_discount_amount'];
        $subtotal = $accommodationSubtotal + $servicesSubtotal;
        $totalDiscount = $accommodationDiscountAmount + $servicesDiscountAmount;
        $totalPrice = $subtotal - $totalDiscount;
        $effectiveDiscountPct = $subtotal > 0
            ? (int) round($totalDiscount / $subtotal * 100)
            : 0;

        $veteranDiscountNights = $this->resolveVeteranDiscountNights(
            $params,
            $aggregated['nights'],
            $veteranType,
        );

        return [
            'nights'                          => $aggregated['nights'],
            'rooms_needed'                    => $aggregated['rooms_needed'],
            'billing_guests'                  => $aggregated['billing_guests'],
            'children_under_6'                => $aggregated['children_under_6'],
            'non_veteran_discount_guests'     => $aggregated['non_veteran_discount_guests'],
            'room_subtotal'                   => $aggregated['room_subtotal'],
            'children_discount_amount'        => $aggregated['children_discount_amount'],
            'veteran_accommodation_discount_amount' => $accommodationDiscountAmount,
            'veteran_discount_nights'         => $veteranDiscountNights,
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
        $veteranType   = $params['veteran_type'] ?? null;
        $nonDiscountGuests = max(0, (int) ($params['non_veteran_discount_guests'] ?? 0));

        $accommodationDiscountPct = isset($params['discount_percentage'])
            ? (int) $params['discount_percentage']
            : $this->veteranPolicy->accommodationDiscount($veteranType);

        $accommodation = $params['accommodation'];
        $roomType      = $params['room_type'] ?? null;
        $roomRate      = $params['room_rate'] ?? null;

        $nights = (int) (new \DateTime($checkIn))->diff(new \DateTime($checkOut))->days;
        $veteranDiscountNights = $this->resolveVeteranDiscountNights($params, $nights, $veteranType);
        $pricePerNight = $roomRate ? $roomRate->price_per_night : $accommodation->price_per_night;

        $roomsNeeded = $this->roomsNeeded($guests, $extraGuests, $roomType);

        $extraGuestsTotal = 0;
        if ($extraGuests > 0 && $roomType && $roomType->extra_capacity_price) {
            $extraGuestsTotal = $extraGuests * (int) $roomType->extra_capacity_price * $nights;
        }

        $billingGuests = $this->billingGuests($guests, $extraGuests, $billFullRooms, $roomsNeeded, $roomType);
        $childrenUnder6 = min($childrenUnder6, max(0, $billingGuests));
        $nonDiscountGuests = min($nonDiscountGuests, $billingGuests);

        $availMap = $roomType ? $roomType->availabilityMap($checkIn, $checkOut) : [];
        $roomSubtotal = 0;
        $roomAfterVeteran = 0;
        $childrenDiscountAmount = 0;
        $cursor = new \DateTime($checkIn);
        $endDate = new \DateTime($checkOut);
        $nightIndex = 0;

        while ($cursor < $endDate) {
            $dayKey = $cursor->format('Y-m-d');
            $dayData = $availMap[$dayKey] ?? null;
            $nightPrice = ($dayData && isset($dayData['effective_price']) && $dayData['effective_price'] !== null)
                ? (int) $dayData['effective_price']
                : $pricePerNight;

            $nightVeteranPct = $nightIndex < $veteranDiscountNights ? $accommodationDiscountPct : 0;
            $nightBreakdown = $this->accommodationNightBreakdown(
                $nightPrice,
                $billingGuests,
                $childrenUnder6,
                $nonDiscountGuests,
                $nightVeteranPct,
            );

            $roomSubtotal += $nightBreakdown['gross'];
            $roomAfterVeteran += $nightBreakdown['net'];
            $childrenDiscountAmount += $nightBreakdown['children_discount_amount'];
            $nightIndex++;
            $cursor->modify('+1 day');
        }

        $roomVeteranDiscount = $roomSubtotal - $roomAfterVeteran;

        $enrichedServices = $this->veteranPolicy->enrichServicesWithDiscounts($veteranType, $services);
        $serviceLines = $this->calculateServiceLines($enrichedServices, [
            'veteran_type'       => $veteranType,
            'national_id'        => $params['national_id'] ?? null,
            'user_id'            => isset($params['user_id']) ? (int) $params['user_id'] : null,
            'reference_date'     => $checkIn,
            'exclude_booking_id' => isset($params['exclude_booking_id']) ? (int) $params['exclude_booking_id'] : null,
        ]);
        $servicesSubtotal = collect($serviceLines)->sum('line_subtotal');
        $servicesDiscountAmount = collect($serviceLines)->sum('discount_amount');

        $accommodationSubtotal = $roomSubtotal + $extraGuestsTotal;
        $discountEligibleRatio = $billingGuests > 0
            ? ($billingGuests - $nonDiscountGuests) / $billingGuests
            : 1.0;
        $extraVeteranDiscount = $accommodationDiscountPct > 0 && $nights > 0 && $veteranDiscountNights > 0
            ? (int) round(
                $extraGuestsTotal * $accommodationDiscountPct / 100 * $discountEligibleRatio * $veteranDiscountNights / $nights
            )
            : 0;
        $accommodationDiscountAmount = $roomVeteranDiscount + $extraVeteranDiscount;

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
            'non_veteran_discount_guests'     => $nonDiscountGuests,
            'room_subtotal'                   => $roomSubtotal,
            'children_discount_amount'        => $childrenDiscountAmount,
            'veteran_accommodation_discount_amount' => $accommodationDiscountAmount,
            'veteran_discount_nights'           => $veteranDiscountNights,
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

    private function resolveVeteranDiscountNights(array $params, int $nights, ?string $veteranType): int
    {
        if (!$veteranType || $nights <= 0) {
            return 0;
        }

        if (array_key_exists('veteran_discount_nights', $params)) {
            return min($nights, max(0, (int) $params['veteran_discount_nights']));
        }

        $usage = $this->veteranPolicy->checkAccommodationUsage(
            $veteranType,
            max(1, (int) ($params['guests'] ?? 1)),
            $nights,
            $params['national_id'] ?? null,
            isset($params['user_id']) ? (int) $params['user_id'] : null,
            isset($params['exclude_booking_id']) ? (int) $params['exclude_booking_id'] : null,
        );

        return min($nights, max(0, (int) ($usage['discounted_nights'] ?? $nights)));
    }

    /**
     * @param  array<int, array<string, mixed>>  $services
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    private function calculateServiceLines(array $services, array $context = []): array
    {
        $lines = [];
        $veteranType = $context['veteran_type'] ?? null;
        $nationalId = $context['national_id'] ?? null;
        $userId = $context['user_id'] ?? null;
        $referenceDate = $context['reference_date'] ?? now()->format('Y-m-d');
        $excludeBookingId = $context['exclude_booking_id'] ?? null;

        // Track free sessions already granted per service type within this booking.
        $freeUsedByService = [];

        foreach ($services as $service) {
            if (empty(trim($service['name'] ?? ''))) {
                continue;
            }

            $qty  = max(1, (int) ($service['quantity'] ?? 1));
            $unit = max(0, (int) ($service['unit_price'] ?? 0));
            $lineSubtotal = $qty * $unit;
            $discountPct  = (int) ($service['discount_percentage'] ?? 0);

            $freeEligible = (bool) ($service['free_sessions_eligible'] ?? false);
            $weeklyFree   = (int) ($service['weekly_free_sessions'] ?? 0);

            $serviceKey = $service['service_catalog_id'] ?? trim($service['name']);
            $catalogId = isset($service['service_catalog_id']) ? (int) $service['service_catalog_id'] : null;

            $alreadyFreeInBooking = $freeUsedByService[$serviceKey] ?? 0;
            $alreadyFreeThisWeek = ($freeEligible && $catalogId)
                ? $this->veteranPolicy->usedFreeSessionsInWeek(
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
                $freeUsedByService[$serviceKey] = $alreadyFreeInBooking + $freeUnits;
            }

            $freeDiscount   = $freeUnits * $unit;
            $paidDiscount   = (int) round($paidUnits * $unit * $discountPct / 100);
            $discountAmount = $freeDiscount + $paidDiscount;

            $lines[] = [
                'name'                   => trim($service['name']),
                'service_catalog_id'     => $service['service_catalog_id'] ?? null,
                'unit_price'             => $unit,
                'quantity'               => $qty,
                'line_subtotal'          => $lineSubtotal,
                'discount_percentage'    => $discountPct,
                'discount_amount'        => $discountAmount,
                'line_total'             => $lineSubtotal - $discountAmount,
                'free_sessions_eligible' => $freeEligible,
                'free_units'             => $freeUnits,
            ];
        }

        return $lines;
    }

    public function roomsNeeded(int $guests, int $extraGuests, ?RoomType $roomType): int
    {
        if (!$roomType) {
            return 1;
        }

        $capacity = max(1, (int) $roomType->capacity);

        if ($extraGuests > 0) {
            $standardGuests = $guests - $extraGuests;

            return max(1, (int) ceil($standardGuests / $capacity));
        }

        return (int) ceil($guests / $capacity);
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

        return max(1, $guests - $extraGuests);
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
    ): array {
        $nonDiscount = min(max(0, $nonDiscountGuests), $billingGuests);
        $children = min(max(0, $childrenUnder6), $billingGuests);
        $fullRate = $billingGuests - $children;

        $nonDiscountChildren = min($nonDiscount, $children);
        $nonDiscountAdults = $nonDiscount - $nonDiscountChildren;
        $discountChildren = $children - $nonDiscountChildren;
        $discountAdults = $fullRate - $nonDiscountAdults;

        $gross = ($fullRate + $children) * $nightPrice;
        $childrenDiscountAmount = (int) round($nightPrice * 0.5 * $children);

        $net = $nonDiscountAdults * $nightPrice
            + (int) round($nightPrice * 0.5 * $nonDiscountChildren);

        if ($veteranDiscountPct > 0) {
            $net += (int) round($nightPrice * (100 - $veteranDiscountPct) / 100 * $discountAdults);
            $net += (int) round($nightPrice * 0.5 * (100 - $veteranDiscountPct) / 100 * $discountChildren);
        } else {
            $net += $discountAdults * $nightPrice;
            $net += (int) round($nightPrice * 0.5 * $discountChildren);
        }

        return [
            'gross'                    => $gross - $childrenDiscountAmount,
            'net'                      => $net,
            'children_discount_amount' => $childrenDiscountAmount,
        ];
    }
}
