<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\PlatformCommissionEntry;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PlatformCommissionService
{
    public function fixedAmount(): int
    {
        return (int) config('platform_commission.fixed_amount', 50_000);
    }

    /** @deprecated Legacy percentage model — kept for historical entry display. */
    public function percentage(): int
    {
        return (int) config('platform_commission.percentage', 5);
    }

    /** @deprecated Legacy percentage model — kept for historical entry display. */
    public function cap(): int
    {
        return (int) config('platform_commission.cap', 50_000);
    }

    public function calculateBookingCommission(Booking $booking): int
    {
        return $this->previewCommissionAmount(
            $this->commissionContextFromBooking($booking),
            $this->bookingNaturalSubtotal($booking),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function previewCommissionAmount(array $context, int $subtotalBeforeCommission): int
    {
        if ($this->isCommissionExemptFromContext($context)) {
            return 0;
        }

        if ($subtotalBeforeCommission <= 0) {
            return 0;
        }

        return $this->fixedAmount();
    }

    /**
     * @param  array<string, mixed>  $pricing
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function overlayPricing(array $pricing, array $context = []): array
    {
        if ($pricing === []) {
            return $pricing;
        }

        $subtotalBeforeCommission = (int) ($pricing['subtotal_before_commission'] ?? $pricing['total_price'] ?? 0);
        $commission = $this->previewCommissionAmount($context, $subtotalBeforeCommission);

        $pricing['subtotal_before_commission'] = $subtotalBeforeCommission;
        $pricing['platform_commission_amount'] = $commission;
        $pricing['total_price'] = $subtotalBeforeCommission + $commission;

        return $pricing;
    }

    /**
     * @param  array<string, mixed>  $pricing
     * @return array<string, mixed>
     */
    public function overlayPricingForBooking(array $pricing, Booking $booking): array
    {
        return $this->overlayPricing($pricing, $this->commissionContextFromBooking($booking));
    }

    public function bookingSubtotalBeforeCommission(Booking $booking): int
    {
        $total = (int) $booking->total_price;
        if ($total <= 0 || $this->isCommissionExempt($booking)) {
            return $total;
        }

        $natural = $this->bookingNaturalSubtotal($booking);
        $commission = $natural > 0 ? $this->fixedAmount() : 0;

        if ($commission > 0 && $total === $natural + $commission) {
            return $natural;
        }

        return $total;
    }

    public function bookingNaturalSubtotal(Booking $booking): int
    {
        return max(0, (int) $booking->base_price - (int) $booking->discount_amount);
    }

    public function isCommissionExempt(Booking $booking): bool
    {
        return $this->isCommissionExemptFromContext($this->commissionContextFromBooking($booking));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function isCommissionExemptFromContext(array $context): bool
    {
        if (($context['booking_source'] ?? null) === 'program') {
            return true;
        }

        if (!empty($context['is_credit']) || ($context['payment_method'] ?? null) === Booking::PAYMENT_CREDIT) {
            return true;
        }

        if (!empty($context['is_medical_accommodation']) || ($context['payment_method'] ?? null) === Booking::PAYMENT_MEDICAL_ACCOMMODATION) {
            return true;
        }

        return false;
    }

    /** @return array<string, mixed> */
    public function commissionContextFromBooking(Booking $booking): array
    {
        return [
            'booking_source'           => $booking->booking_source,
            'payment_method'           => $booking->payment_method,
            'is_credit'                => $booking->isCredit(),
            'is_medical_accommodation' => $booking->isMedicalAccommodation(),
        ];
    }

    public function walletBalance(): int
    {
        return (int) PlatformCommissionEntry::query()->sum('commission_amount');
    }

    public function syncBookingCommissions(Booking $booking, ?User $actor = null): void
    {
        DB::transaction(function () use ($booking, $actor) {
            $booking->loadMissing(['services.serviceCatalog', 'accommodation']);

            if ($booking->status === 'cancelled') {
                $this->reverseAllForBooking($booking, $actor);

                return;
            }

            if ($booking->status !== 'confirmed') {
                return;
            }

            $this->reconcileBookingCommissions($booking, $actor);
        });
    }

    /**
     * @return array<string, array{
     *   category: string,
     *   category_key: string,
     *   service_catalog_id: ?int,
     *   service_name: ?string,
     *   transaction_amount: int,
     *   commission_amount: int,
     *   meta: array<string, mixed>
     * }>
     */
    public function buildCommissionTargets(Booking $booking): array
    {
        $commissionAmount = $this->calculateBookingCommission($booking);
        $bookingAmount = $this->bookingSubtotalBeforeCommission($booking);

        if ($commissionAmount <= 0 && $bookingAmount <= 0) {
            return [];
        }

        $servicesTotal = (int) $booking->services->sum('total');

        return [
            'accommodation' => [
                'category'             => PlatformCommissionEntry::CATEGORY_ACCOMMODATION,
                'category_key'         => 'accommodation',
                'service_catalog_id'   => null,
                'service_name'         => null,
                'transaction_amount'   => $bookingAmount,
                'commission_amount'    => $commissionAmount,
                'meta'                 => $this->baseMeta($booking) + [
                    'description'           => 'هزینه رزرو',
                    'commission_model'      => 'fixed_per_booking',
                    'fixed_commission'      => $this->fixedAmount(),
                    'services_total'        => $servicesTotal,
                    'accommodation_amount'  => max(0, $bookingAmount - $servicesTotal),
                    'platform_commission_amount' => $commissionAmount,
                    'subtotal_before_commission' => $bookingAmount,
                    'is_program_booking'             => $booking->isProgram(),
                    'is_credit_booking'              => $booking->isCredit(),
                    'is_medical_accommodation_booking' => $booking->isMedicalAccommodation(),
                    'is_commission_exempt'           => $this->isCommissionExempt($booking),
                    'nights'                => $booking->nights,
                    'guests'                => $booking->guests,
                    'base_price'            => $booking->base_price,
                    'discount_amount'       => $booking->discount_amount,
                ],
            ],
        ];
    }

    private function reconcileBookingCommissions(Booking $booking, ?User $actor): void
    {
        $targets = $this->buildCommissionTargets($booking);
        $netted = $this->netCommissionByCategory($booking);
        $allKeys = collect(array_keys($targets))->merge(array_keys($netted))->unique();

        foreach ($allKeys as $categoryKey) {
            $targetCommission = $targets[$categoryKey]['commission_amount'] ?? 0;
            $currentNet = $netted[$categoryKey] ?? 0;
            $delta = $targetCommission - $currentNet;

            if ($delta === 0) {
                continue;
            }

            $target = $targets[$categoryKey] ?? null;
            $previousTransaction = $this->lastTransactionAmount($booking, $categoryKey);

            $this->createEntry($booking, [
                'category'             => $target['category'] ?? PlatformCommissionEntry::CATEGORY_ACCOMMODATION,
                'category_key'         => $categoryKey,
                'service_catalog_id'   => $target['service_catalog_id'] ?? null,
                'service_name'         => $target['service_name'] ?? null,
                'entry_type'           => PlatformCommissionEntry::TYPE_ADJUSTMENT,
                'reason'               => PlatformCommissionEntry::REASON_AMOUNT_ADJUSTED,
                'transaction_amount'   => $target['transaction_amount'] ?? 0,
                'commission_amount'    => $delta,
                'meta'                 => ($target['meta'] ?? $this->baseMeta($booking)) + [
                    'previous_transaction_amount' => $previousTransaction,
                    'previous_net_commission'     => $currentNet,
                    'new_transaction_amount'      => $target['transaction_amount'] ?? 0,
                    'new_target_commission'       => $targetCommission,
                ],
            ], $actor);
        }
    }

    private function reverseAllForBooking(Booking $booking, ?User $actor): void
    {
        $netted = $this->netCommissionByCategory($booking);

        foreach ($netted as $categoryKey => $netAmount) {
            if ($netAmount === 0) {
                continue;
            }

            $lastEntry = $this->lastEntryForCategory($booking, $categoryKey);

            $this->createEntry($booking, [
                'category'             => $lastEntry?->category ?? PlatformCommissionEntry::CATEGORY_ACCOMMODATION,
                'category_key'         => $categoryKey,
                'service_catalog_id'   => $lastEntry?->service_catalog_id,
                'service_name'         => $lastEntry?->service_name,
                'entry_type'           => PlatformCommissionEntry::TYPE_REVERSAL,
                'reason'               => PlatformCommissionEntry::REASON_BOOKING_CANCELLED,
                'transaction_amount'   => $lastEntry?->transaction_amount ?? 0,
                'commission_amount'    => -$netAmount,
                'meta'                 => $this->baseMeta($booking) + [
                    'reversed_net_commission' => $netAmount,
                    'cancelled_at'            => now()->toIso8601String(),
                ],
            ], $actor);
        }
    }

    /** @return array<string, int> */
    private function netCommissionByCategory(Booking $booking): array
    {
        return PlatformCommissionEntry::query()
            ->where('booking_id', $booking->id)
            ->selectRaw('category_key, SUM(commission_amount) as net')
            ->groupBy('category_key')
            ->pluck('net', 'category_key')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    private function lastEntryForCategory(Booking $booking, string $categoryKey): ?PlatformCommissionEntry
    {
        return PlatformCommissionEntry::query()
            ->where('booking_id', $booking->id)
            ->where('category_key', $categoryKey)
            ->latest('id')
            ->first();
    }

    private function lastTransactionAmount(Booking $booking, string $categoryKey): int
    {
        return (int) (PlatformCommissionEntry::query()
            ->where('booking_id', $booking->id)
            ->where('category_key', $categoryKey)
            ->where('commission_amount', '>', 0)
            ->latest('id')
            ->value('transaction_amount') ?? 0);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createEntry(Booking $booking, array $data, ?User $actor): PlatformCommissionEntry
    {
        $isFirstCredit = ($data['commission_amount'] ?? 0) > 0
            && !PlatformCommissionEntry::query()
                ->where('booking_id', $booking->id)
                ->where('category_key', $data['category_key'])
                ->exists();

        if ($isFirstCredit) {
            $data['entry_type'] = PlatformCommissionEntry::TYPE_CREDIT;
            $data['reason'] = PlatformCommissionEntry::REASON_BOOKING_CONFIRMED;
        }

        return PlatformCommissionEntry::create([
            'booking_id'            => $booking->id,
            'accommodation_id'      => $booking->accommodation_id,
            'category'              => $data['category'],
            'category_key'          => $data['category_key'],
            'service_catalog_id'    => $data['service_catalog_id'] ?? null,
            'service_name'          => $data['service_name'] ?? null,
            'entry_type'            => $data['entry_type'],
            'reason'                => $data['reason'],
            'transaction_amount'    => $data['transaction_amount'],
            'commission_percentage' => 0,
            'commission_cap'        => $this->fixedAmount(),
            'commission_amount'     => $data['commission_amount'],
            'meta'                  => $data['meta'] ?? [],
            'created_by'            => $actor?->id ?? Auth::id(),
        ]);
    }

    /** @return array<string, mixed> */
    private function baseMeta(Booking $booking): array
    {
        return [
            'tracking_code'      => $booking->tracking_code,
            'booking_source'     => $booking->booking_source,
            'booking_status'     => $booking->status,
            'check_in'           => $booking->check_in?->format('Y-m-d'),
            'check_out'          => $booking->check_out?->format('Y-m-d'),
            'accommodation_id'   => $booking->accommodation_id,
            'accommodation_name' => $booking->accommodation?->name,
            'booker_name'        => $booking->bookerName(),
            'booker_mobile'      => $booking->bookerMobile(),
            'payment_method'     => $booking->payment_method,
            'total_price'        => $booking->total_price,
        ];
    }
}
