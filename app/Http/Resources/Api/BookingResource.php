<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Booking */
class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'tracking_code'       => $this->tracking_code,
            'status'              => $this->status,
            'check_in'            => $this->check_in->toDateString(),
            'check_out'           => $this->check_out->toDateString(),
            'guests'              => (int) $this->guests,
            'children_under_6'    => (int) ($this->children_under_6 ?? 0),
            'rooms_consumed'      => (int) $this->rooms_consumed,
            'extra_guests'        => (int) ($this->extra_guests ?? 0),
            'nights'              => (int) $this->nights,
            'base_price'          => (int) $this->base_price,
            'discount_percentage' => (int) $this->discount_percentage,
            'discount_amount'     => (int) $this->discount_amount,
            'total_price'         => (int) $this->total_price,
            'booking_source'      => $this->booking_source,
            'can_request_cancellation' => $this->canRequestCancellation(),
            'can_review'          => $this->status === 'confirmed'
                && $this->check_out->toDateString() < now()->toDateString(),
            'accommodation'       => new AccommodationResource($this->whenLoaded('accommodation')),
            'room_type'           => $this->whenLoaded('roomType', fn () => [
                'id'   => $this->roomType->id,
                'name' => $this->roomType->name,
            ]),
            'room_rate'           => $this->whenLoaded('roomRate', fn () => [
                'id'              => $this->roomRate->id,
                'name'            => $this->roomRate->name,
                'price_per_night' => (int) $this->roomRate->price_per_night,
            ]),
            'cancellation_requests' => CancellationRequestResource::collection(
                $this->whenLoaded('cancellationRequests')
            ),
            'created_at'          => $this->created_at?->toIso8601String(),
        ];
    }
}
