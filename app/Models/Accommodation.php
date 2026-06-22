<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Accommodation extends Model
{
    public const MANAGEMENT_OUTSOURCED     = 'outsourced';
    public const MANAGEMENT_SELF_GOVERNING = 'self_governing';

    protected $fillable = [
        'city_id', 'host_id', 'name', 'description', 'type', 'management_status',
        'price_per_night', 'capacity', 'children_under_6_allocate_bed', 'children_under_6_discount_percentage', 'rooms',
        'address', 'phone_numbers', 'lat', 'lng', 'amenities', 'image', 'images', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amenities'                     => 'array',
            'phone_numbers'                 => 'array',
            'images'                        => 'array',
            'is_active'                     => 'boolean',
            'children_under_6_allocate_bed' => 'boolean',
            'lat'                           => 'float',
            'lng'                           => 'float',
        ];
    }

    public function childrenUnder6AllocateBed(): bool
    {
        return (bool) ($this->children_under_6_allocate_bed ?? true);
    }

    public function childrenUnder6DiscountPercentage(): int
    {
        return max(0, min(100, (int) ($this->children_under_6_discount_percentage ?? 50)));
    }

    public function childrenUnder6PayMultiplier(): float
    {
        return (100 - $this->childrenUnder6DiscountPercentage()) / 100;
    }

    public static function managementStatusOptions(): array
    {
        return [
            self::MANAGEMENT_OUTSOURCED     => 'برون‌سپاری',
            self::MANAGEMENT_SELF_GOVERNING => 'خودگردان',
        ];
    }

    public function managementStatusLabel(): ?string
    {
        return self::managementStatusOptions()[$this->management_status] ?? null;
    }

    public function host()
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function hosts()
    {
        return $this->belongsToMany(User::class, 'accommodation_host')->withTimestamps();
    }

    public function isManagedBy(User $user): bool
    {
        return $this->hosts()->where('users.id', $user->id)->exists();
    }

    public function grantHostAccess(User $user): void
    {
        $this->hosts()->syncWithoutDetaching([$user->id]);

        if (!$this->host_id) {
            $this->update(['host_id' => $user->id]);
        }
    }

    public function revokeHostAccess(User $user): void
    {
        $this->hosts()->detach($user->id);

        if ((int) $this->host_id === (int) $user->id) {
            $this->update(['host_id' => $this->hosts()->first()?->id]);
        }
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
        return AccommodationType::labelFor($this->type);
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
