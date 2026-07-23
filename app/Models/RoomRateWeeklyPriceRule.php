<?php

namespace App\Models;

use App\Support\RoomTypePriceResolver;
use Illuminate\Database\Eloquent\Model;

class RoomRateWeeklyPriceRule extends Model
{
    protected $fillable = [
        'room_rate_id', 'weekday', 'custom_price', 'discount_percentage', 'price_label', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'weekday'             => 'integer',
            'custom_price'        => 'integer',
            'discount_percentage' => 'integer',
        ];
    }

    public function weekdayLabel(): string
    {
        return RoomTypeWeeklyPriceRule::WEEKDAY_LABELS[$this->weekday] ?? (string) $this->weekday;
    }

    public function effectivePrice(int $basePrice): int
    {
        return RoomTypePriceResolver::effectivePrice(
            $basePrice,
            $this->custom_price,
            $this->discount_percentage,
        );
    }

    public function hasPriceAdjustment(): bool
    {
        return RoomTypePriceResolver::hasPriceAdjustment(
            $this->custom_price,
            $this->discount_percentage,
        ) || filled($this->price_label);
    }

    public function roomRate()
    {
        return $this->belongsTo(RoomRate::class);
    }
}
