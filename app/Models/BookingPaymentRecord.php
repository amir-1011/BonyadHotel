<?php

namespace App\Models;

use App\Support\ProgramDocumentPaths;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingPaymentRecord extends Model
{
    public const CONTEXT_MANUAL_BOOKING = 'manual_booking';

    public const CONTEXT_PRICE_CHANGE = 'price_change';

    protected $fillable = [
        'booking_id',
        'amount',
        'amount_delta',
        'price_adjustment_reason',
        'card_last_four',
        'transaction_tracking',
        'payment_at',
        'pos_terminal_id',
        'document_paths',
        'context',
        'action',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'amount_delta' => 'integer',
            'payment_at' => 'datetime',
            'document_paths' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function posTerminal(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return list<string> */
    public function documentPaths(): array
    {
        return ProgramDocumentPaths::normalize($this->document_paths);
    }

    public function hasDocuments(): bool
    {
        return $this->documentPaths() !== [];
    }

    public function contextLabel(): string
    {
        return match ($this->context) {
            self::CONTEXT_MANUAL_BOOKING => 'ثبت رزرو',
            self::CONTEXT_PRICE_CHANGE => 'تغییر مبلغ رزرو',
            default => $this->context,
        };
    }
}
