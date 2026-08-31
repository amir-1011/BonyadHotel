{{--
    Single amount row for financial breakdown.
    @param string $label
    @param int|string|null $amount
    @param string $variant  default|muted|discount|adjustment|emphasis|total|hero
    @param string|null $hint  secondary line under label
    @param string $sign  ''|'+'|'−'
    @param bool $compact  smaller padding
--}}
@props([
    'label',
    'amount' => null,
    'variant' => 'default',
    'hint' => null,
    'sign' => '',
    'compact' => false,
])

@php
    $variantClass = match ($variant) {
        'muted' => 'bnb-fin-row--muted',
        'discount' => 'bnb-fin-row--discount',
        'adjustment' => 'bnb-fin-row--adjustment',
        'emphasis' => 'bnb-fin-row--emphasis',
        'total' => 'bnb-fin-row--total',
        'hero' => 'bnb-fin-row--hero',
        default => '',
    };
    $formattedAmount = $amount === null
        ? '—'
        : (is_numeric($amount) ? \App\Support\PdfPersian::toPersianDigits(number_format((int) $amount)) : (string) $amount);
@endphp

<div {{ $attributes->merge(['class' => 'bnb-fin-row ' . $variantClass . ($compact ? ' bnb-fin-row--compact' : '')]) }}>
    <div class="bnb-fin-row__label">
        <span>{{ $label }}</span>
        @if($hint)
        <small class="bnb-fin-row__hint">{{ $hint }}</small>
        @endif
    </div>
    @if($amount !== null)
    <div class="bnb-fin-row__amount" dir="ltr">
        @if($sign !== '')<span class="bnb-fin-row__sign">{{ $sign }}</span>@endif
        <span>{{ $formattedAmount }}</span>
        <span class="bnb-fin-currency">ریال</span>
    </div>
    @endif
</div>
