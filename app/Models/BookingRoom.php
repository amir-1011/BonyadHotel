<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingRoom extends Model
{
    protected $fillable = [
        'booking_id',
        'room_type_id',
        'room_rate_id',
        'room_id',
        'adults',
        'children_under_6',
        'guests',
        'extra_guests',
        'bill_full_rooms',
        'rooms_consumed',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'bill_full_rooms' => 'boolean',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function roomRate()
    {
        return $this->belongsTo(RoomRate::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function guestDetails()
    {
        return $this->hasMany(BookingGuestDetail::class)->orderBy('sort_order');
    }

    public function physicalRoomLabel(): string
    {
        return $this->physicalRoomDisplayLabel();
    }

    public function physicalRoomDisplayLabel(): string
    {
        $group = $this->roomType?->name;
        $physical = $this->room?->name;

        if ($group && $physical) {
            return $group . ' · ' . $physical;
        }

        return $physical ?? $group ?? '—';
    }
}
