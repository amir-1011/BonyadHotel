<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $fillable = [
        'room_type_id',
        'name',
        'description',
        'amenities',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amenities' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function bookingRooms()
    {
        return $this->hasMany(BookingRoom::class);
    }

    public function displayAmenities(): array
    {
        return array_values(array_filter($this->amenities ?? []));
    }

    public function displayLabelWithGroup(): string
    {
        $group = $this->roomType?->name;
        $physical = $this->name;

        if ($group && $physical) {
            return $group . ' · ' . $physical;
        }

        return $physical ?? $group ?? '—';
    }
}
