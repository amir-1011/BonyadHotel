{{--
    One service line in financial breakdown (web).
    @param \App\Models\BookingService $service
    @param array<string,mixed>|null $line  pricing line from breakdown
    @param \App\Models\BookingGuestDetail|null $guest
    @param string|null $roomLabel
--}}
@props(['service', 'line' => null, 'guest' => null, 'roomLabel' => null])

@php
    /** @var \App\Models\BookingService $service */
    $lineSub = (int) $service->unit_price * (int) $service->quantity;
    $calculatedTotal = $service->calculatedTotal();
    $manualAdjustment = $service->manualPriceAdjustmentAmount();
    $payableTotal = $service->payableTotal();
    $discountAmount = (int) $service->discount_amount;
    $breakdown = $line['discount_breakdown'] ?? [];
@endphp

<article class="bnb-fin-service">
    <header class="bnb-fin-service__head">
        <div>
            <h6 class="bnb-fin-service__title">{{ $service->name }}</h6>
            @if($guest)
            <div class="bnb-fin-service__meta">
                {{ $guest->full_name }}
                @if($roomLabel)
                · اتاق {{ $roomLabel }}
                @endif
            </div>
            @endif
        </div>
        <div class="bnb-fin-service__qty">
            {{ $service->quantity }} × {{ \App\Support\PdfPersian::toPersianDigits(number_format($service->unit_price)) }}
        </div>
    </header>

    <div class="bnb-fin-service__body">
        <x-booking.financial-row
            label="مبلغ پایه"
            :amount="$lineSub"
            variant="muted"
            compact
        />

        @if(!empty($breakdown))
            @foreach($breakdown as $item)
            @php
                $units = (int) ($item['units'] ?? 0);
                $itemAmount = (int) ($item['discount_amount'] ?? 0);
                $discountLabel = match ($item['type'] ?? '') {
                    'free' => ($units === 1 ? '۱ جلسه رایگان' : $units . ' جلسه رایگان'),
                    'fixed_pay' => ($units === 1 ? '۱ جلسه' : $units . ' جلسه') . ' · نرخ ویژه ' . \App\Support\PdfPersian::toPersianDigits(number_format((int) ($item['pay_amount'] ?? 0))),
                    'percentage' => ($units === 1 ? '۱ جلسه' : $units . ' جلسه') . ' · ' . (int) ($item['discount_percentage'] ?? 0) . '٪ تخفیف',
                    'manual' => 'تخفیف میزبان ' . (int) ($item['discount_percentage'] ?? 0) . '٪',
                    default => 'تخفیف',
                };
            @endphp
            <x-booking.financial-row
                :label="$discountLabel"
                :amount="$itemAmount"
                variant="discount"
                sign="−"
                compact
            />
            @endforeach
        @elseif($discountAmount > 0)
            @php $discountReason = $service->discountReasonLabel(); @endphp
            <x-booking.financial-row
                :label="$discountReason !== '' ? $discountReason : 'تخفیف ایثارگری'"
                :amount="$discountAmount"
                variant="discount"
                sign="−"
                compact
            />
        @endif

        @if($manualAdjustment !== 0)
            <x-booking.financial-row
                label="محاسبه سیستم"
                :amount="$calculatedTotal"
                variant="muted"
                compact
            />
            <x-booking.financial-row
                label="تعدیل مبلغ"
                :amount="abs($manualAdjustment)"
                variant="adjustment"
                :sign="$manualAdjustment > 0 ? '+' : '−'"
                compact
            />
        @endif
    </div>

    <footer class="bnb-fin-service__foot">
        <span>مبلغ این خدمت</span>
        <strong dir="ltr">{{ \App\Support\PdfPersian::toPersianDigits(number_format($payableTotal)) }} <span class="bnb-fin-currency">ریال</span></strong>
    </footer>
</article>
