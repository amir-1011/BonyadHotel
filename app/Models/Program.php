<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    protected $fillable = [
        'accommodation_id', 'title', 'description', 'program_type',
        'start_date', 'end_date', 'rooms_allocated', 'guest_count',
        'employer', 'contractor', 'total_amount', 'deposit_amount',
        'discount_amount', 'discount_percentage', 'is_supportive_service',
        'supportive_service_type', 'notes', 'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date'            => 'date',
            'end_date'              => 'date',
            'is_supportive_service' => 'boolean',
        ];
    }

    /* ── روابط ──────────────────────────────────────────────────────────── */

    public function accommodation()
    {
        return $this->belongsTo(Accommodation::class);
    }

    public function roomTypes()
    {
        return $this->belongsToMany(RoomType::class, 'program_room_types')
                    ->withPivot('rooms_count')
                    ->withTimestamps();
    }

    /* ── متدهای کمکی ────────────────────────────────────────────────────── */

    public function statusLabel(): string
    {
        return match($this->status) {
            'active'    => 'فعال',
            'completed' => 'پایان‌یافته',
            'cancelled' => 'لغو‌شده',
            default     => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match($this->status) {
            'active'    => 'success',
            'completed' => 'secondary',
            'cancelled' => 'danger',
            default     => 'light',
        };
    }

    public function programTypeLabel(): string
    {
        return match($this->program_type) {
            'camp'  => 'اردو',
            'event' => 'رویداد',
            default => 'سایر',
        };
    }

    /** مانده پس از کسر بیعانه */
    public function remainingAmount(): int
    {
        return max(0, (int)$this->total_amount - (int)$this->deposit_amount);
    }

    /** مبلغ خالص پس از تخفیف */
    public function netAmount(): int
    {
        return max(0, (int)$this->total_amount - (int)$this->discount_amount);
    }
}
