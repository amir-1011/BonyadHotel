<?php

namespace App\Models;

use App\Support\VeteranGroups;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'user_id', 'created_by', 'accommodation_id', 'room_type_id', 'room_rate_id',
        'check_in', 'check_out',
        'guests', 'children_under_6', 'guest_contact_name', 'guest_contact_mobile',
        'rooms_consumed', 'extra_guests', 'extra_guests_price', 'bill_full_rooms',
        'nights', 'base_price', 'services_subtotal', 'discount_percentage',
        'veteran_type_applied', 'discount_amount', 'total_price',
        'status', 'tracking_code', 'booking_source', 'payment_method',
        'notes', 'form_file_path',
    ];

    protected function casts(): array
    {
        return [
            'check_in'        => 'date',
            'check_out'       => 'date',
            'bill_full_rooms' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
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

    public function services()
    {
        return $this->hasMany(BookingService::class)->orderBy('sort_order');
    }

    public function guestDetails()
    {
        return $this->hasMany(BookingGuestDetail::class)->orderBy('sort_order');
    }

    public function bookingRooms()
    {
        return $this->hasMany(BookingRoom::class)->orderBy('sort_order');
    }

    public function commissionEntries()
    {
        return $this->hasMany(PlatformCommissionEntry::class);
    }

    public function isManual(): bool
    {
        return $this->booking_source === 'manual';
    }

    public function bookerName(): string
    {
        return $this->guest_contact_name
            ?? $this->user?->name
            ?? $this->user?->mobile
            ?? '—';
    }

    public function bookerMobile(): string
    {
        return $this->guest_contact_mobile
            ?? $this->user?->mobile
            ?? '—';
    }

    public function veteranLabelApplied(): string
    {
        return VeteranGroups::label($this->veteran_type_applied);
    }

    public function paymentMethodLabel(): string
    {
        return match ($this->payment_method) {
            'cash'          => 'نقدی',
            'card_terminal' => 'کارتخوان',
            default         => '—',
        };
    }

    public function roomSubtotal(): int
    {
        return max(0, $this->base_price - $this->services_subtotal - $this->extra_guests_price);
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
