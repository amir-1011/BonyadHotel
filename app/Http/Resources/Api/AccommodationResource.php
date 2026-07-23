<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Accommodation */
class AccommodationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'type'             => $this->type,
            'type_label'       => $this->typeLabel(),
            'description'      => $this->description,
            'price_per_night'  => (int) $this->price_per_night,
            'lowest_price'     => (int) ($this->lowest_price ?? $this->price_per_night),
            'capacity'         => (int) $this->capacity,
            'rooms'            => (int) $this->rooms,
            'address'          => $this->address,
            'lat'              => $this->lat,
            'lng'              => $this->lng,
            'amenities'        => $this->amenities ?? [],
            'image'            => $this->image ? asset('storage/' . $this->image) : null,
            'images'           => collect($this->images ?? [])->map(fn ($img) => asset('storage/' . $img))->values(),
            'average_rating'   => round($this->averageRating(), 1),
            'review_count'     => $this->reviewCount(),
            'city'             => $this->whenLoaded('city', fn () => [
                'id'       => $this->city->id,
                'name'     => $this->city->name,
                'province' => $this->city->relationLoaded('province') ? [
                    'id'   => $this->city->province->id,
                    'name' => $this->city->province->name,
                ] : null,
            ]),
            'is_favorited'     => $user ? $this->isFavoritedBy($user) : false,
            'user_discount_pct'=> $user ? $user->accommodationDiscountFor($this->id) : 0,
        ];
    }
}
