<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\PlatformCommissionEntry;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlatformCommissionService
{
    public function percentage(): int
    {
        return (int) config('platform_commission.percentage', 5);
    }

    public function cap(): int
    {
        return (int) config('platform_commission.cap', 50_000);
    }

    public function calculateCommission(int $transactionAmount): int
    {
        if ($transactionAmount <= 0) {
            return 0;
        }

        $raw = (int) round($transactionAmount * $this->percentage() / 100);

        return min($this->cap(), $raw);
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
        $targets = [];
        $servicesTotal = (int) $booking->services->sum('total');
        $accommodationAmount = max(0, (int) $booking->total_price - $servicesTotal);

        if ($accommodationAmount > 0) {
            $targets['accommodation'] = [
                'category'             => PlatformCommissionEntry::CATEGORY_ACCOMMODATION,
                'category_key'         => 'accommodation',
                'service_catalog_id'   => null,
                'service_name'         => null,
                'transaction_amount'   => $accommodationAmount,
                'commission_amount'    => $this->calculateCommission($accommodationAmount),
                'meta'                 => $this->baseMeta($booking) + [
                    'description'      => 'هزینه اقامت',
                    'nights'           => $booking->nights,
                    'guests'           => $booking->guests,
                    'base_price'       => $booking->base_price,
                    'discount_amount'  => max(0, $booking->discount_amount - (int) $booking->services->sum('discount_amount')),
                ],
            ];
        }

        foreach ($this->groupedServices($booking->services) as $group) {
            if ($group['transaction_amount'] <= 0) {
                continue;
            }

            $targets[$group['category_key']] = [
                'category'             => PlatformCommissionEntry::CATEGORY_SERVICE,
                'category_key'         => $group['category_key'],
                'service_catalog_id'   => $group['service_catalog_id'],
                'service_name'         => $group['service_name'],
                'transaction_amount'   => $group['transaction_amount'],
                'commission_amount'    => $this->calculateCommission($group['transaction_amount']),
                'meta'                 => $this->baseMeta($booking) + [
                    'description'      => 'خدمت: ' . $group['service_name'],
                    'quantity'         => $group['quantity'],
                    'unit_price'       => $group['unit_price'],
                    'discount_amount'  => $group['discount_amount'],
                    'free_units'       => $group['free_units'],
                    'service_catalog_key'=> $group['service_catalog_key'],
                    'lines'            => $group['lines'],
                ],
            ];
        }

        return $targets;
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
                'category'             => $target['category'] ?? $this->inferCategory($categoryKey),
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
                'category'             => $lastEntry?->category ?? $this->inferCategory($categoryKey),
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

    /**
     * @param  Collection<int, \App\Models\BookingService>  $services
     * @return array<int, array{
     *   category_key: string,
     *   service_catalog_id: ?int,
     *   service_name: string,
     *   service_catalog_key: ?string,
     *   transaction_amount: int,
     *   quantity: int,
     *   unit_price: int,
     *   discount_amount: int,
     *   free_units: int,
     *   lines: array<int, array<string, mixed>>
     * }>
     */
    private function groupedServices(Collection $services): array
    {
        $groups = [];

        foreach ($services as $service) {
            $catalogId = $service->service_catalog_id;
            $categoryKey = $catalogId
                ? 'service:catalog:' . $catalogId
                : 'service:custom:' . Str::slug($service->name ?: 'custom');

            if (!isset($groups[$categoryKey])) {
                $groups[$categoryKey] = [
                    'category_key'        => $categoryKey,
                    'service_catalog_id'  => $catalogId,
                    'service_name'        => $service->name,
                    'service_catalog_key' => $service->serviceCatalog?->key,
                    'transaction_amount'  => 0,
                    'quantity'            => 0,
                    'unit_price'          => 0,
                    'discount_amount'     => 0,
                    'free_units'          => 0,
                    'lines'               => [],
                ];
            }

            $groups[$categoryKey]['transaction_amount'] += (int) $service->total;
            $groups[$categoryKey]['quantity'] += (int) $service->quantity;
            $groups[$categoryKey]['discount_amount'] += (int) $service->discount_amount;
            $groups[$categoryKey]['free_units'] += (int) $service->free_units;
            $groups[$categoryKey]['lines'][] = [
                'id'                  => $service->id,
                'name'                => $service->name,
                'unit_price'          => $service->unit_price,
                'quantity'            => $service->quantity,
                'free_units'          => $service->free_units,
                'discount_amount'     => $service->discount_amount,
                'total'               => $service->total,
            ];
        }

        return array_values($groups);
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
            'commission_percentage' => $this->percentage(),
            'commission_cap'        => $this->cap(),
            'commission_amount'     => $data['commission_amount'],
            'meta'                  => $data['meta'] ?? [],
            'created_by'            => $actor?->id ?? Auth::id(),
        ]);
    }

    private function inferCategory(string $categoryKey): string
    {
        return str_starts_with($categoryKey, 'service:')
            ? PlatformCommissionEntry::CATEGORY_SERVICE
            : PlatformCommissionEntry::CATEGORY_ACCOMMODATION;
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
