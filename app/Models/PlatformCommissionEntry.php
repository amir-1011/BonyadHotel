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

    public function usesFlatBookingFee(): bool
    {
        return $this->commission_percentage === 0
            || ($this->meta['commission_model'] ?? null) === 'fixed_per_booking';
    }

    public function rawCommissionBeforeCap(): int
    {
        if ($this->usesFlatBookingFee()) {
            return $this->commission_cap;
        }

        return (int) round($this->transaction_amount * $this->commission_percentage / 100);
    }

    public function wasCapped(): bool
    {
        if ($this->usesFlatBookingFee()) {
            return false;
        }

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
            'medical_accommodation' => 'اسکان درمانی',
            'credit' => 'اعتباری',
            default         => $method ? (string) $method : '—',
        };
    }

    /** @return list<string> */
    public function commissionCalculationSteps(): array
    {
        $steps = [];

        if ($this->usesFlatBookingFee()) {
            $steps[] = 'نوع کارمزد: مبلغ ثابت برای هر رزرو';
            $steps[] = 'مبلغ رزرو: ' . number_format($this->transaction_amount) . ' ریال';

            if ($this->isCommissionExemptBooking()) {
                $steps[] = $this->commissionExemptReason() . ' — کارمزد صفر';
            } elseif ($this->transaction_amount <= 0) {
                $steps[] = 'مبلغ رزرو صفر — کارمزد صفر';
            } else {
                $steps[] = 'کارمزد ثابت هر رزرو: ' . number_format($this->commission_cap) . ' ریال';
                $steps[] = 'خدمات: بدون کارمزد';
            }

            $steps[] = 'کارمزد نهایی این رکورد: ' . number_format(abs($this->commission_amount)) . ' ریال';

            return $steps;
        }

        $steps[] = 'مبلغ پایه تراکنش: ' . number_format($this->transaction_amount) . ' ریال';
        $steps[] = 'نرخ کارمزد: ' . $this->commission_percentage . '٪';
        $raw = $this->rawCommissionBeforeCap();
        $steps[] = 'محاسبه خام: ' . number_format($this->transaction_amount) . ' × ' . $this->commission_percentage . '٪ = ' . number_format($raw) . ' ریال';

        if ($this->wasCapped()) {
            $steps[] = 'سقف کارمزد: ' . number_format($this->commission_cap) . ' ریال (مبلغ خام بیش از سقف بود)';
            $steps[] = 'کارمزد نهایی این رکورد: ' . number_format(abs($this->commission_amount)) . ' ریال';
        } else {
            $steps[] = 'کارمزد نهایی این رکورد: ' . number_format(abs($this->commission_amount)) . ' ریال (زیر سقف)';
        }

        return $steps;
    }

    public function fullExplanation(): string
    {
        $category = $this->categoryLabel();
        $tracking = $this->meta['tracking_code'] ?? $this->booking?->tracking_code ?? '—';
        $amount = number_format(abs($this->commission_amount));

        if ($this->entry_type === self::TYPE_CREDIT && $this->reason === self::REASON_BOOKING_CONFIRMED) {
            if ($this->usesFlatBookingFee()) {
                if ($this->commission_amount === 0) {
                    if ($this->isCommissionExemptBooking()) {
                        return "با ثبت و تأیید رزرو «{$tracking}»، {$this->commissionExemptReason()} و کارمزدی به کیف پول واریز نشد.";
                    }

                    return "با ثبت و تأیید رزرو «{$tracking}»، مبلغ رزرو صفر است و کارمزدی به کیف پول واریز نشد.";
                }

                return "با ثبت و تأیید رزرو «{$tracking}» به مبلغ "
                    . number_format($this->transaction_amount) . " ریال، کارمزد ثابت "
                    . number_format($this->commission_cap) . " ریال (بدون کارمزد خدمات) "
                    . "به کیف پول کارمزد واریز گردید.";
            }

            return "با ثبت و تأیید رزرو «{$tracking}»، برای بخش «{$category}» به مبلغ "
                . number_format($this->transaction_amount) . " ریال، کارمزد "
                . $this->commission_percentage . "٪ محاسبه شد"
                . ($this->wasCapped() ? " و به دلیل سقف " . number_format($this->commission_cap) . " ریال،" : "")
                . " مبلغ {$amount} ریال به کیف پول کارمزد واریز گردید.";
        }

        if ($this->entry_type === self::TYPE_REVERSAL && $this->reason === self::REASON_BOOKING_CANCELLED) {
            $reversed = number_format($this->meta['reversed_net_commission'] ?? abs($this->commission_amount));

            return "رزرو «{$tracking}» لغو شد. کل کارمزد خالص ثبت‌شده برای «{$category}» برابر {$reversed} ریال بود "
                . "که به‌طور کامل ({$amount} ریال) از کیف پول برگشت داده شد.";
        }

        if ($this->reason === self::REASON_AMOUNT_ADJUSTED) {
            $prevTx = number_format($this->meta['previous_transaction_amount'] ?? 0);
            $prevNet = number_format($this->meta['previous_net_commission'] ?? 0);
            $newTx = number_format($this->meta['new_transaction_amount'] ?? $this->transaction_amount);
            $newTarget = number_format($this->meta['new_target_commission'] ?? 0);
            $direction = $this->commission_amount >= 0 ? 'افزایش' : 'کاهش';

            return "در رزرو «{$tracking}»، مبلغ بخش «{$category}» تغییر کرد. "
                . "مبلغ تراکنش از {$prevTx} به {$newTx} ریال رسید. "
                . "کارمزد خالص قبلی {$prevNet} ریال بود و کارمزد هدف جدید {$newTarget} ریال است. "
                . "در نتیجه {$amount} ریال {$direction} در کیف پول ثبت شد.";
        }

        return $this->entryTypeLabel() . ' — ' . $this->reasonLabel();
    }

    private function isCommissionExemptBooking(): bool
    {
        if ($this->meta['is_commission_exempt'] ?? false) {
            return true;
        }

        if (($this->meta['is_program_booking'] ?? false) || ($this->meta['booking_source'] ?? '') === 'program') {
            return true;
        }

        if ($this->meta['is_credit_booking'] ?? false) {
            return true;
        }

        if ($this->meta['is_medical_accommodation_booking'] ?? false) {
            return true;
        }

        $paymentMethod = $this->meta['payment_method'] ?? $this->booking?->payment_method;

        return in_array($paymentMethod, ['credit', 'medical_accommodation'], true);
    }

    private function commissionExemptReason(): string
    {
        if (($this->meta['is_program_booking'] ?? false) || ($this->meta['booking_source'] ?? '') === 'program') {
            return 'رزرو از نوع اردو / برنامه است';
        }

        if (($this->meta['is_credit_booking'] ?? false) || ($this->meta['payment_method'] ?? $this->booking?->payment_method) === 'credit') {
            return 'رزرو از نوع اعتباری است';
        }

        if (($this->meta['is_medical_accommodation_booking'] ?? false) || ($this->meta['payment_method'] ?? $this->booking?->payment_method) === 'medical_accommodation') {
            return 'رزرو از نوع اسکان درمانی است';
        }

        return 'این رزرو از کارمزد معاف است';
    }
}
