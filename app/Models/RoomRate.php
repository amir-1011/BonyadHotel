<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomRate extends Model
{
    protected $fillable = [
        'room_type_id', 'name', 'price_per_night',
        'breakfast_included', 'breakfast_price_per_person',
        'cancellation_policy', 'payment_type', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'breakfast_included' => 'boolean',
            'is_active'          => 'boolean',
        ];
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /** Human-readable cancellation label */
    public function cancellationLabel(): string
    {
        return $this->cancellation_policy === 'free' ? 'لغو رایگان' : 'غیر قابل استرداد';
    }

    /** Human-readable payment label */
    public function paymentLabel(): string
    {
        return $this->payment_type === 'pay_at_hotel' ? 'پرداخت در محل' : 'پرداخت آنلاین';
    }
}
