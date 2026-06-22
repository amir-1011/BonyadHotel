<?php

namespace App\Models;

use App\Support\RoomTypePriceResolver;
use Illuminate\Database\Eloquent\Model;

class RoomTypeDailyOverride extends Model
{
    protected $fillable = [
        'room_type_id', 'date', 'available_count', 'reason',
        'custom_price', 'discount_percentage', 'price_label',
    ];

    protected function casts(): array
    {
        return [
            'date'                => 'date',
            'available_count'     => 'integer',
            'custom_price'        => 'integer',
            'discount_percentage' => 'integer',
        ];
    }

    /**
     * Effective nightly price after applying discount.
     * $basePrice is the rate's default price_per_night — used when custom_price is null.
     */
    public function effectivePrice(int $basePrice): int
    {
        return RoomTypePriceResolver::effectivePrice(
            $basePrice,
            $this->custom_price,
            $this->discount_percentage,
        );
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }
}
