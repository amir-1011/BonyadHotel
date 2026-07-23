<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CancellationRequest extends Model
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'booking_id', 'requested_by', 'status',
        'cancellation_reason_id', 'reason_text', 'notes',
        'refund_account_number', 'refund_account_holder_name',
        'days_before_checkin', 'refund_percentage', 'refund_amount',
        'decided_by', 'decided_at', 'rejection_reason',
        'settled_by', 'settled_at',
        'settled_amount', 'settled_account_number', 'settlement_notes',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function reason()
    {
        return $this->belongsTo(CancellationReason::class, 'cancellation_reason_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decidedBy()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function settledBy()
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isSettled(): bool
    {
        return $this->settled_at !== null;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING  => 'در انتظار بررسی',
            self::STATUS_APPROVED => $this->isSettled() ? 'تایید شده و تسویه شده' : 'تایید شده (در انتظار تسویه)',
            self::STATUS_REJECTED => 'رد شده',
            default               => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING  => 'warning',
            self::STATUS_APPROVED => $this->isSettled() ? 'success' : 'info',
            self::STATUS_REJECTED => 'danger',
            default               => 'secondary',
        };
    }

    public function reasonDisplay(): string
    {
        if ($this->reason && $this->reason->is_custom && $this->reason_text) {
            return $this->reason_text;
        }

        return $this->reason_text ?: ($this->reason?->label ?? '—');
    }

    /**
     * Whether this request was filed mid-stay (after check-in, before/on check-out),
     * derived from the frozen `days_before_checkin` snapshot taken at request time.
     */
    public function isMidStay(): bool
    {
        return $this->days_before_checkin !== null && $this->days_before_checkin < 0;
    }

    public function nightsTotal(): int
    {
        return max(1, (int) ($this->booking?->nights ?? 1));
    }

    public function nightsElapsed(): int
    {
        if (!$this->isMidStay()) {
            return 0;
        }

        return min($this->nightsTotal(), max(0, -(int) $this->days_before_checkin));
    }

    public function nightsRemaining(): int
    {
        return max(0, $this->nightsTotal() - $this->nightsElapsed());
    }

    /**
     * The prorated booking amount (before the refund percentage is applied) that the
     * remaining nights represent, for mid-stay requests.
     */
    public function nightsBasisAmountDisplay(): int
    {
        $total = (int) ($this->booking?->total_price ?? 0);

        return (int) round($total * $this->nightsRemaining() / $this->nightsTotal());
    }
}
