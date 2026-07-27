<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    public const TYPE_CAMP = 'camp';
    public const TYPE_EVENT = 'event';
    public const TYPE_OTHER = 'other';

    public const PAYMENT_CASH = 'payment';
    public const PAYMENT_CREDIT = 'credit';
    public const PAYMENT_SUPPORTIVE = 'supportive';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'booking_id',
        'accommodation_id',
        'created_by',
        'title',
        'description',
        'program_type',
        'program_employer_id',
        'contractor',
        'guest_count',
        'rooms_allocated',
        'payment_type',
        'payment_documents',
        'guest_list_documents',
        'base_price',
        'services_subtotal',
        'discount_amount',
        'deposit_amount',
        'total_amount',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'payment_documents' => 'array',
            'guest_list_documents' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function accommodation(): BelongsTo
    {
        return $this->belongsTo(Accommodation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function beneficiaryCosts(): HasMany
    {
        return $this->hasMany(ProgramBeneficiaryCost::class);
    }

    public function employer(): BelongsTo
    {
        return $this->belongsTo(ProgramEmployer::class, 'program_employer_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE    => 'فعال',
            self::STATUS_COMPLETED => 'پایان‌یافته',
            self::STATUS_CANCELLED => 'لغو‌شده',
            default                => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE    => 'success',
            self::STATUS_COMPLETED => 'secondary',
            self::STATUS_CANCELLED => 'danger',
            default                => 'light',
        };
    }

    public function programTypeLabel(): string
    {
        return match ($this->program_type) {
            self::TYPE_CAMP  => 'اردو',
            self::TYPE_EVENT => 'رویداد',
            default          => 'سایر',
        };
    }

    public function paymentTypeLabel(): string
    {
        return match ($this->payment_type) {
            self::PAYMENT_CASH      => 'پرداخت (بیعانه / باقیمانده)',
            self::PAYMENT_CREDIT    => 'اعتباری',
            self::PAYMENT_SUPPORTIVE => 'خدمات حمایتی',
            default                 => $this->payment_type,
        };
    }

    public function remainingAmount(): int
    {
        return max(0, (int) $this->total_amount - (int) $this->deposit_amount);
    }

    public function netAmount(): int
    {
        return max(0, (int) $this->total_amount);
    }

    public function startDate()
    {
        return $this->booking?->check_in;
    }

    public function endDate()
    {
        return $this->booking?->check_out;
    }

    /** @return array<string, string> */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_CAMP  => 'اردو',
            self::TYPE_EVENT => 'رویداد',
            self::TYPE_OTHER => 'سایر',
        ];
    }

    /** @return array<string, string> */
    public static function paymentTypeOptions(): array
    {
        return [
            self::PAYMENT_CASH      => 'پرداخت (بیعانه / باقیمانده)',
            self::PAYMENT_CREDIT    => 'اعتباری',
            self::PAYMENT_SUPPORTIVE => 'خدمات حمایتی',
        ];
    }

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_ACTIVE    => 'فعال',
            self::STATUS_COMPLETED => 'پایان‌یافته',
            self::STATUS_CANCELLED => 'لغو‌شده',
        ];
    }
}
