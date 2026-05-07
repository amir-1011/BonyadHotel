<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'user_id', 'accommodation_id', 'booking_id',
        'rating', 'comment', 'is_visible',
        'host_reply', 'host_replied_at',
    ];

    protected function casts(): array
    {
        return [
            'is_visible'      => 'boolean',
            'host_replied_at' => 'datetime',
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

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function stars(): string
    {
        return str_repeat('★', $this->rating) . str_repeat('☆', 5 - $this->rating);
    }
}
