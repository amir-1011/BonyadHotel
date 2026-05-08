<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Accommodation extends Model
{
    protected $fillable = [
        'city_id', 'host_id', 'name', 'description', 'type',
        'price_per_night', 'capacity', 'rooms',
        'address', 'lat', 'lng', 'amenities', 'image', 'images', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amenities'   => 'array',
            'images'      => 'array',
            'is_active'   => 'boolean',
            'lat'         => 'float',
            'lng'         => 'float',
        ];
    }

    public function host()
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function averageRating(): float
    {
        return round($this->reviews()->where('is_visible', true)->avg('rating') ?? 0, 1);
    }

    public function reviewCount(): int
    {
        return $this->reviews()->where('is_visible', true)->count();
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function favoritedBy()
    {
        return $this->belongsToMany(User::class, 'user_favorites')->withTimestamps();
    }

    public function isFavoritedBy(User $user): bool
    {
        return $this->favoritedBy()->where('user_id', $user->id)->exists();
    }

    public function roomTypes()
    {
        return $this->hasMany(RoomType::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Get the lowest price among all room types and rates.
     */
    public function getLowestPriceAttribute(): ?float
    {
        // Try to get the minimum price from active room rates related to this accommodation
        $minRate = RoomRate::whereIn('room_type_id', function ($query) {
            $query->select('id')
                ->from('room_types')
                ->where('accommodation_id', $this->id)
                ->where('is_active', true);
        })
        ->where('is_active', true)
        ->min('price_per_night');

        return $minRate ?: $this->price_per_night;
    }

    public function typeLabel(): string
    {
        return match($this->type) {
            'hotel'       => 'هتل',
            'villa'       => 'ویلا',
            'apartment'   => 'آپارتمان',
            'hostel'      => 'هاستل',
            'traditional' => 'اقامتگاه سنتی',
            default       => $this->type,
        };
    }

    public function isAvailable(string $checkIn, string $checkOut): bool
    {
        return !$this->bookings()
            ->whereIn('status', ['confirmed', 'pending'])
            ->where(function ($q) use ($checkIn, $checkOut) {
                $q->whereBetween('check_in', [$checkIn, $checkOut])
                  ->orWhereBetween('check_out', [$checkIn, $checkOut])
                  ->orWhere(function ($q2) use ($checkIn, $checkOut) {
                      $q2->where('check_in', '<=', $checkIn)
                         ->where('check_out', '>=', $checkOut);
                  });
            })
            ->exists();
    }
}
