<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomTypeBlockedDate extends Model
{
    protected $fillable = ['room_type_id', 'date', 'reason'];

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
}
