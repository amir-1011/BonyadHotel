<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformCommissionEntry extends Model
{
    public const CATEGORY_ACCOMMODATION = 'accommodation';

    public const CATEGORY_SERVICE = 'service';

    public const TYPE_CREDIT = 'credit';

    public const TYPE_REVERSAL = 'reversal';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const REASON_BOOKING_CONFIRMED = 'booking_confirmed';

    public const REASON_BOOKING_CANCELLED = 'booking_cancelled';

    public const REASON_AMOUNT_ADJUSTED = 'amount_adjusted';

    protected $fillable = [
        'booking_id',
        'accommodation_id',
        'category',
        'category_key',
        'service_catalog_id',
        'service_name',
        'entry_type',
        'reason',
        'transaction_amount',
        'commission_percentage',
        'commission_cap',
        'commission_amount',
        'meta',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_amount'    => 'integer',
            'commission_percentage' => 'integer',
            'commission_cap'        => 'integer',
            'commission_amount'     => 'integer',
            'meta'                  => 'array',
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

    public function serviceCatalog(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalog::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function entryTypeLabel(): string
    {
        return match ($this->entry_type) {
            self::TYPE_CREDIT      => 'واریز',
            self::TYPE_REVERSAL    => 'برگشت',
            self::TYPE_ADJUSTMENT  => $this->commission_amount >= 0 ? 'افزایش' : 'کاهش',
            default                => $this->entry_type,
        };
    }

    public function reasonLabel(): string
    {
        return match ($this->reason) {
            self::REASON_BOOKING_CONFIRMED => 'ثبت رزرو',
            self::REASON_BOOKING_CANCELLED => 'لغو رزرو',
            self::REASON_AMOUNT_ADJUSTED   => 'تغییر مبلغ',
            default                        => $this->reason,
        };
    }

    public function categoryLabel(): string
    {
        if ($this->category === self::CATEGORY_ACCOMMODATION) {
            return 'اقامت / رزرو';
        }

        return $this->serviceCatalog?->name
            ?? $this->service_name
            ?? 'خدمت';
    }

    public function isCredit(): bool
    {
        return $this->commission_amount > 0;
    }

    public function rawCommissionBeforeCap(): int
    {
        return (int) round($this->transaction_amount * $this->commission_percentage / 100);
    }

    public function wasCapped(): bool
    {
        return $this->rawCommissionBeforeCap() > $this->commission_cap;
    }

    public function bookingSourceLabel(): string
    {
        return match ($this->meta['booking_source'] ?? $this->booking?->booking_source ?? '') {
            'manual' => 'رزرو دستی (پنل)',
            'online' => 'رزرو آنلاین',
            default  => '—',
        };
    }

    public function paymentMethodLabel(): string
    {
        $method = $this->meta['payment_method'] ?? $this->booking?->payment_method;

        return match ($method) {
            'cash'          => 'نقدی',
            'card_terminal' => 'کارتخوان',
            default         => $method ? (string) $method : '—',
        };
    }

    /** @return list<string> */
    public function commissionCalculationSteps(): array
    {
        $steps = [];
        $steps[] = 'مبلغ پایه تراکنش: ' . number_format($this->transaction_amount) . ' تومان';
        $steps[] = 'نرخ کارمزد: ' . $this->commission_percentage . '٪';
        $raw = $this->rawCommissionBeforeCap();
        $steps[] = 'محاسبه خام: ' . number_format($this->transaction_amount) . ' × ' . $this->commission_percentage . '٪ = ' . number_format($raw) . ' تومان';

        if ($this->wasCapped()) {
            $steps[] = 'سقف کارمزد: ' . number_format($this->commission_cap) . ' تومان (مبلغ خام بیش از سقف بود)';
            $steps[] = 'کارمزد نهایی این رکورد: ' . number_format(abs($this->commission_amount)) . ' تومان';
        } else {
            $steps[] = 'کارمزد نهایی این رکورد: ' . number_format(abs($this->commission_amount)) . ' تومان (زیر سقف)';
        }

        return $steps;
    }

    public function fullExplanation(): string
    {
        $category = $this->categoryLabel();
        $tracking = $this->meta['tracking_code'] ?? $this->booking?->tracking_code ?? '—';
        $amount = number_format(abs($this->commission_amount));

        if ($this->entry_type === self::TYPE_CREDIT && $this->reason === self::REASON_BOOKING_CONFIRMED) {
            return "با ثبت و تأیید رزرو «{$tracking}»، برای بخش «{$category}» به مبلغ "
                . number_format($this->transaction_amount) . " تومان، کارمزد "
                . $this->commission_percentage . "٪ محاسبه شد"
                . ($this->wasCapped() ? " و به دلیل سقف " . number_format($this->commission_cap) . " تومان،" : "")
                . " مبلغ {$amount} تومان به کیف پول کارمزد واریز گردید.";
        }

        if ($this->entry_type === self::TYPE_REVERSAL && $this->reason === self::REASON_BOOKING_CANCELLED) {
            $reversed = number_format($this->meta['reversed_net_commission'] ?? abs($this->commission_amount));

            return "رزرو «{$tracking}» لغو شد. کل کارمزد خالص ثبت‌شده برای «{$category}» برابر {$reversed} تومان بود "
                . "که به‌طور کامل ({$amount} تومان) از کیف پول برگشت داده شد.";
        }

        if ($this->reason === self::REASON_AMOUNT_ADJUSTED) {
            $prevTx = number_format($this->meta['previous_transaction_amount'] ?? 0);
            $prevNet = number_format($this->meta['previous_net_commission'] ?? 0);
            $newTx = number_format($this->meta['new_transaction_amount'] ?? $this->transaction_amount);
            $newTarget = number_format($this->meta['new_target_commission'] ?? 0);
            $direction = $this->commission_amount >= 0 ? 'افزایش' : 'کاهش';

            return "در رزرو «{$tracking}»، مبلغ بخش «{$category}» تغییر کرد. "
                . "مبلغ تراکنش از {$prevTx} به {$newTx} تومان رسید. "
                . "کارمزد خالص قبلی {$prevNet} تومان بود و کارمزد هدف جدید {$newTarget} تومان است. "
                . "در نتیجه {$amount} تومان {$direction} در کیف پول ثبت شد.";
        }

        return $this->entryTypeLabel() . ' — ' . $this->reasonLabel();
    }
}
