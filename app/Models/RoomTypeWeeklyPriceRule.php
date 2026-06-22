<?php

namespace App\Models;

use App\Support\RoomTypePriceResolver;
use Illuminate\Database\Eloquent\Model;

class RoomTypeWeeklyPriceRule extends Model
{
    /** @var array<int, string> ISO weekday (PHP date N) → Persian label */
    public const WEEKDAY_LABELS = [
        6 => 'شنبه',
        7 => 'یکشنبه',
        1 => 'دوشنبه',
        2 => 'سه‌شنبه',
        3 => 'چهارشنبه',
        4 => 'پنج‌شنبه',
        5 => 'جمعه',
    ];

    protected $fillable = [
        'room_type_id', 'weekday', 'custom_price', 'discount_percentage', 'price_label', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'weekday'             => 'integer',
            'custom_price'        => 'integer',
            'discount_percentage' => 'integer',
        ];
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function weekdayLabel(): string
    {
        return self::WEEKDAY_LABELS[$this->weekday] ?? (string) $this->weekday;
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
        );
    }
}
