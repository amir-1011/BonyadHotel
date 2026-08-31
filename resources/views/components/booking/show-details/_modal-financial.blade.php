<div class="bnb-fin-modal">
    <x-booking.financial-breakdown :booking="$booking" :pricing="$pricingBreakdown" />
    <x-booking.payment-records :booking="$booking" :panel="$panel ?? 'admin'" />
</div>
