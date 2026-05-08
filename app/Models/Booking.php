<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'user_id', 'accommodation_id', 'room_type_id', 'room_rate_id',
        'check_in', 'check_out',
        'guests', 'nights', 'base_price', 'discount_percentage',
        'discount_amount', 'total_price', 'status', 'tracking_code',
    ];

    protected function casts(): array
    {
        return [
            'check_in'  => 'date',
            'check_out' => 'date',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function accommodation()
    {
        return $this->belongsTo(Accommodation::class);
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function roomRate()
    {
        return $this->belongsTo(RoomRate::class);
    }

    public function statusLabel(): string
    {
        return match($this->status) {
            'pending'   => 'در انتظار تأیید',
            'confirmed' => 'تأیید شده',
            'cancelled' => 'لغو شده',
            default     => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match($this->status) {
            'pending'   => 'warning',
            'confirmed' => 'success',
            'cancelled' => 'danger',
            default     => 'secondary',
        };
    }
}
