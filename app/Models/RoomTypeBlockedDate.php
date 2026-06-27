<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomTypeBlockedDate extends Model
{
    protected $fillable = ['room_type_id', 'room_id', 'date', 'reason'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function roomLabel(): string
    {
        return $this->room?->name ?? 'همه اتاق‌ها';
    }
}
