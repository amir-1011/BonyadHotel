<?php

namespace App\Services;

use App\Models\Accommodation;
use App\Models\Booking;
use Illuminate\Support\Collection;

class AdminVeteranDiscountStatsService
{
    public function __construct(
        private readonly VeteranPolicyProvisioner $provisioner,
        private readonly BookingReceiptBreakdownService $breakdown,
    ) {}

    /**
     * @param  array<int>|null  $accommodationIds
     * @return array{
     *     groups: Collection<int, array{key: string, label: string, discount_pct: int, nights: int, discount_amount: int, bookings_count: int}>,
     *     totals: array{nights: int, discount_amount: int, bookings_count: int}
     * }
     */
    public function build(?array $accommodationIds = null): array
    {
        $scoped = $this->normalizeScope($accommodationIds);

        $stats = [];
        foreach ($this->provisioner->groupDefinitions() as $def) {
            $stats[$def['key']] = [
                'key'              => $def['key'],
                'label'            => $def['label'],
                'discount_pct'     => (int) $def['accommodation_discount'],
                'nights'           => 0,
                'discount_amount'  => 0,
                'bookings_count'   => 0,
            ];
        }

        $bookingIdsPerGroup = array_fill_keys(array_keys($stats), []);

        $query = Booking::query()
            ->where('status', 'confirmed')
            ->where(function ($q) {
                $q->where(function ($inner) {
                    $inner->whereNotNull('veteran_type_applied')
                        ->where('veteran_type_applied', '!=', '');
                })
                    ->orWhere(function ($inner) {
                        $inner->whereNotNull('secondary_veteran_type_applied')
                            ->where('secondary_veteran_type_applied', '!=', '');
                    })
                    ->orWhereNotNull('veteran_accommodation_group_usage');
            });

        if ($scoped !== null) {
            if ($scoped === []) {
                return $this->emptyResult($stats);
            }

            $query->whereIn('accommodation_id', $scoped);
        }

        $query
            ->select('id')
            ->orderBy('id')
            ->chunkById(40, function ($chunk) use (&$stats, &$bookingIdsPerGroup) {
                $bookings = Booking::query()
                    ->whereIn('id', $chunk->pluck('id'))
                    ->with(['services', 'guestDetails', 'accommodation', 'bookingRooms.roomType', 'bookingRooms.roomRate', 'user'])
                    ->get();

                foreach ($bookings as $booking) {
                    $pricing = $this->breakdown->forBooking($booking);
                    $groupsInBooking = [];

                    foreach ($pricing['accommodation_discount_breakdown'] ?? [] as $item) {
                        $key = (string) ($item['veteran_group_key'] ?? '');
                        if ($key === '' || !isset($stats[$key])) {
                            continue;
                        }

                        $stats[$key]['nights'] += (int) ($item['units'] ?? 0);
                        $stats[$key]['discount_amount'] += (int) ($item['discount_amount'] ?? 0);
                        $groupsInBooking[$key] = true;
                    }

                    if ($groupsInBooking === []) {
                        foreach ($pricing['veteran_accommodation_group_usage'] ?? [] as $key => $nights) {
                            if (!isset($stats[$key]) || (int) $nights <= 0) {
                                continue;
                            }

                            $stats[$key]['nights'] += (int) $nights;
                            $groupsInBooking[$key] = true;
                        }
                    }

                    foreach (array_keys($groupsInBooking) as $key) {
                        $bookingIdsPerGroup[$key][$booking->id] = true;
                    }
                }
            });

        foreach ($bookingIdsPerGroup as $key => $bookingIds) {
            $stats[$key]['bookings_count'] = count($bookingIds);
        }

        $groups = collect($stats)->values();

        return [
            'groups' => $groups,
            'totals' => [
                'nights'          => (int) $groups->sum('nights'),
                'discount_amount' => (int) $groups->sum('discount_amount'),
                'bookings_count'  => (int) $groups->sum('bookings_count'),
            ],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $stats
     * @return array{
     *     groups: Collection<int, array{key: string, label: string, discount_pct: int, nights: int, discount_amount: int, bookings_count: int}>,
     *     totals: array{nights: int, discount_amount: int, bookings_count: int}
     * }
     */
    private function emptyResult(array $stats): array
    {
        $groups = collect($stats)->values();

        return [
            'groups' => $groups,
            'totals' => [
                'nights'          => 0,
                'discount_amount' => 0,
                'bookings_count'  => 0,
            ],
        ];
    }

    /**
     * @param  array<int>|null  $accommodationIds
     * @return array<int>|null
     */
    private function normalizeScope(?array $accommodationIds): ?array
    {
        if ($accommodationIds === null) {
            return null;
        }

        $ids = array_values(array_unique(array_map('intval', $accommodationIds)));

        if ($ids === []) {
            return [];
        }

        $total = Accommodation::query()->count();

        if (count($ids) >= $total) {
            return null;
        }

        return $ids;
    }
}
