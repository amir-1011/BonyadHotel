<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingGuestDetail extends Model
{
    protected $fillable = [
        'booking_id', 'sort_order', 'full_name', 'national_id', 'mobile', 'relation',
        'excluded_from_veteran_discount', 'manual_discount_percentage', 'manual_discount_reason', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'excluded_from_veteran_discount' => 'boolean',
            'manual_discount_percentage'      => 'integer',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
