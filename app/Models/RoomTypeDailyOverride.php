<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomTypeDailyOverride extends Model
{
    protected $fillable = ['room_type_id', 'date', 'available_count', 'reason'];

    protected function casts(): array
    {
        return [
            'date'            => 'date',
            'available_count' => 'integer',
        ];
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }
}
