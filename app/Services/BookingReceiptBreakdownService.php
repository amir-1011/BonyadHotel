<?php

namespace App\Services;

use App\Models\Booking;
use App\Support\VeteranGroups;

/**
 * Rebuilds pricing breakdown (including per-service discount details) for display/PDF.
 */
class BookingReceiptBreakdownService
{
    public function __construct(
        private readonly BookingPricingService $pricing,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forBooking(Booking $booking): array
    {
        $booking->loadMissing(['services', 'guestDetails', 'accommodation', 'bookingRooms.roomType', 'bookingRooms.roomRate']);

        $services = $booking->services->map(fn ($s) => [
            'name'               => $s->name,
            'unit_price'         => $s->unit_price,
            'quantity'           => $s->quantity,
            'service_catalog_id' => $s->service_catalog_id,
            'discount_override'  => null,
        ])->all();

        $guestDetails = $booking->guestDetails->map(fn ($g) => [
            'excluded_from_veteran_discount' => $g->excluded_from_veteran_discount,
            'manual_discount_percentage'     => $g->manual_discount_percentage,
        ])->all();

        $billingGuests = max(1, (int) $booking->guests - (int) $booking->extra_guests);
        $veteranTypes = $booking->veteranTypesApplied();
        $veteranDiscountPct = VeteranGroups::accommodationDiscountForTypes(
            $veteranTypes,
            $booking->accommodation_id,
        );

        $perGuestSlots = $this->pricing->buildPerGuestSlotsFromGuestDetails(
            $guestDetails,
            $billingGuests,
            (int) ($booking->children_under_6 ?? 0),
            $veteranTypes[0] ?? null,
            $veteranDiscountPct,
        );

        $bookingRooms = $booking->bookingRooms;
        $params = [
            'check_in'            => $booking->check_in->format('Y-m-d'),
            'check_out'           => $booking->check_out->format('Y-m-d'),
            'guests'              => $booking->guests,
            'children_under_6'    => $booking->children_under_6 ?? 0,
            'extra_guests'        => $booking->extra_guests,
            'bill_full_rooms'     => false,
            'veteran_type'        => $veteranTypes[0] ?? null,
            'secondary_veteran_type' => $veteranTypes[1] ?? null,
            'veteran_types'       => $veteranTypes,
            'services'            => $services,
            'accommodation'       => $booking->accommodation,
            'national_id'         => $booking->guestDetails->value('national_id') ?? $booking->user?->national_id,
            'user_id'             => $booking->user_id,
            'exclude_booking_id'  => $booking->id,
            'non_veteran_discount_guests' => $booking->guestDetails
                ->where('excluded_from_veteran_discount', true)
                ->count(),
            'per_guest_slots'     => $perGuestSlots,
        ];

        if ($bookingRooms->isNotEmpty()) {
            $params['room_lines'] = $bookingRooms->map(fn ($line) => [
                'room_type'        => $line->roomType,
                'room_rate'        => $line->roomRate,
                'guests'           => $line->guests,
                'children_under_6' => $line->children_under_6,
                'extra_guests'     => $line->extra_guests,
                'bill_full_rooms'  => $line->bill_full_rooms,
            ])->all();
        } else {
            $params['room_type'] = $booking->roomType;
            $params['room_rate'] = $booking->roomRate;
        }

        $pricing = $this->pricing->calculate($params);

        return $pricing;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function serviceLinesForBooking(Booking $booking): array
    {
        return $this->forBooking($booking)['service_lines'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function pricingForBooking(Booking $booking): array
    {
        return $this->forBooking($booking);
    }

    /**
     * Match pricing line to a stored booking service by sort order.
     *
     * @return array<string, mixed>|null
     */
    public function serviceLineFor(Booking $booking, int $sortOrder): ?array
    {
        $lines = $this->serviceLinesForBooking($booking);

        return $lines[$sortOrder] ?? null;
    }
}
