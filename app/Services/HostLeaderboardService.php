<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;

class HostLeaderboardService
{
    public const ALL_TIME_KEY = 'all';

    /** @return array{month: string, month_label: string, prev_month_label: string, check_in_from: string|null, check_in_to: string|null, all_time: bool, hosts: Collection<int, object>, month_options: Collection<int, array{value: string, label: string}>} */
    public function build(string $monthKey, int $limit = 10, ?array $accommodationIds = null): array
    {
        if ($monthKey === self::ALL_TIME_KEY) {
            return $this->buildAllTime($limit, $accommodationIds);
        }

        $monthStart = Carbon::createFromFormat('Y-m-d', $monthKey . '-01')->startOfMonth();
        $monthEnd   = $monthStart->copy()->endOfMonth();
        $prevStart  = $monthStart->copy()->subMonth()->startOfMonth();
        $prevEnd    = $monthStart->copy()->subSecond();

        $hostIds = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'host')->where('guard_name', 'web'))
            ->pluck('id');

        $currentStats = $this->reserverStatsForRange($hostIds, $monthStart, $monthEnd, $accommodationIds);
        $prevStats    = $this->reserverStatsForRange($hostIds, $prevStart, $prevEnd, $accommodationIds);

        $rankedIds = $this->rankReserverIds($currentStats, $limit);

        $users = User::query()
            ->whereIn('id', $rankedIds)
            ->get(['id', 'name', 'mobile'])
            ->keyBy('id');

        $accommodationsByHost = DB::table('accommodation_host as ah')
            ->join('accommodations as a', 'a.id', '=', 'ah.accommodation_id')
            ->whereIn('ah.user_id', $rankedIds)
            ->orderBy('a.name')
            ->select('ah.user_id', 'a.name')
            ->get()
            ->groupBy('user_id');

        $hosts = $rankedIds->map(function (int $id) use ($users, $currentStats, $prevStats, $accommodationsByHost) {
            $user    = $users->get($id);
            $current = $currentStats->get($id);
            $prev    = $prevStats->get($id);

            $revenue      = (float) ($current->revenue ?? 0);
            $prevRevenue  = (float) ($prev->revenue ?? 0);
            $bookings     = (int) ($current->bookings_count ?? 0);
            $prevBookings = (int) ($prev->bookings_count ?? 0);

            return (object) [
                'id'                 => $id,
                'name'               => $user?->name,
                'mobile'             => $user?->mobile,
                'bookings_count'     => $bookings,
                'revenue'            => $revenue,
                'prev_bookings_count'=> $prevBookings,
                'prev_revenue'       => $prevRevenue,
                'bookings_delta'     => $bookings - $prevBookings,
                'revenue_growth_pct' => $this->growthPercent($revenue, $prevRevenue),
                'accommodations'     => $accommodationsByHost->get($id, collect())->pluck('name')->values()->all(),
            ];
        })->values();

        return [
            'month'            => $monthKey,
            'month_label'      => Jalalian::fromCarbon($monthStart)->format('F Y'),
            'prev_month_label' => Jalalian::fromCarbon($prevStart)->format('F Y'),
            'check_in_from'    => Jalalian::fromCarbon($monthStart)->format('Y/m/d'),
            'check_in_to'      => Jalalian::fromCarbon($monthEnd)->format('Y/m/d'),
            'all_time'         => false,
            'hosts'            => $hosts,
            'month_options'    => $this->monthOptions(),
        ];
    }

    /** @return array{month: string, month_label: string, prev_month_label: string, check_in_from: null, check_in_to: null, all_time: true, hosts: Collection<int, object>, month_options: Collection<int, array{value: string, label: string}>} */
    public function buildAllTime(int $limit = 10, ?array $accommodationIds = null): array
    {
        $hostIds = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'host')->where('guard_name', 'web'))
            ->pluck('id');

        $currentStats = $this->reserverStatsAllTime($hostIds, $accommodationIds);

        $rankedIds = $this->rankReserverIds($currentStats, $limit);

        $users = User::query()
            ->whereIn('id', $rankedIds)
            ->get(['id', 'name', 'mobile'])
            ->keyBy('id');

        $accommodationsByHost = DB::table('accommodation_host as ah')
            ->join('accommodations as a', 'a.id', '=', 'ah.accommodation_id')
            ->whereIn('ah.user_id', $rankedIds)
            ->orderBy('a.name')
            ->select('ah.user_id', 'a.name')
            ->get()
            ->groupBy('user_id');

        $hosts = $rankedIds->map(function (int $id) use ($users, $currentStats, $accommodationsByHost) {
            $user    = $users->get($id);
            $current = $currentStats->get($id);

            return (object) [
                'id'                  => $id,
                'name'                => $user?->name,
                'mobile'              => $user?->mobile,
                'bookings_count'      => (int) ($current->bookings_count ?? 0),
                'revenue'             => (float) ($current->revenue ?? 0),
                'prev_bookings_count' => 0,
                'prev_revenue'        => 0.0,
                'bookings_delta'      => 0,
                'revenue_growth_pct'  => null,
                'accommodations'      => $accommodationsByHost->get($id, collect())->pluck('name')->values()->all(),
            ];
        })->values();

        return [
            'month'            => self::ALL_TIME_KEY,
            'month_label'      => 'همه تاریخ‌ها',
            'prev_month_label' => '',
            'check_in_from'    => null,
            'check_in_to'      => null,
            'all_time'         => true,
            'hosts'            => $hosts,
            'month_options'    => $this->monthOptions(),
        ];
    }

    /** @return Collection<int, array{value: string, label: string}> */
    public function monthOptions(int $count = 12): Collection
    {
        $options = collect();
        $cursor  = now()->startOfMonth();

        for ($i = 0; $i < $count; $i++) {
            $options->push([
                'value' => $cursor->format('Y-m'),
                'label' => Jalalian::fromCarbon($cursor)->format('F Y'),
            ]);
            $cursor = $cursor->copy()->subMonth();
        }

        return $options;
    }

    public function isValidMonthKey(string $monthKey): bool
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            return false;
        }

        return $this->monthOptions(24)->contains(fn (array $opt) => $opt['value'] === $monthKey);
    }

    /** @param  Collection<int, int|string>  $hostIds */
    private function reserverStatsAllTime(Collection $hostIds, ?array $accommodationIds = null): Collection
    {
        if ($hostIds->isEmpty()) {
            return collect();
        }

        $ids = $hostIds->all();

        $query = Booking::query()
            ->where('status', 'confirmed')
            ->where(function ($q) use ($ids) {
                $q->whereIn('created_by', $ids)
                    ->orWhere(function ($q2) use ($ids) {
                        $q2->whereNull('created_by')->whereIn('user_id', $ids);
                    });
            });

        $this->applyAccommodationScope($query, $accommodationIds);

        return $query
            ->selectRaw('COALESCE(created_by, user_id) as reserver_id')
            ->selectRaw('COUNT(*) as bookings_count')
            ->selectRaw('COALESCE(SUM(total_price), 0) as revenue')
            ->groupBy('reserver_id')
            ->get()
            ->keyBy(fn ($row) => (int) $row->reserver_id);
    }

    /** @param  Collection<int, object>  $stats */
    private function rankReserverIds(Collection $stats, int $limit): Collection
    {
        return $stats
            ->sort(function ($a, $b) {
                $byRevenue = (float) $b->revenue <=> (float) $a->revenue;
                if ($byRevenue !== 0) {
                    return $byRevenue;
                }

                return (int) $b->bookings_count <=> (int) $a->bookings_count;
            })
            ->keys()
            ->take($limit)
            ->values();
    }

    /** @param  Collection<int, int|string>  $hostIds */
    private function reserverStatsForRange(Collection $hostIds, Carbon $from, Carbon $_to, ?array $accommodationIds = null): Collection
    {
        if ($hostIds->isEmpty()) {
            return collect();
        }

        $ids = $hostIds->all();

        $query = Booking::query()
            ->where('status', 'confirmed')
            ->whereYear('created_at', $from->year)
            ->whereMonth('created_at', $from->month)
            ->where(function ($q) use ($ids) {
                $q->whereIn('created_by', $ids)
                    ->orWhere(function ($q2) use ($ids) {
                        $q2->whereNull('created_by')->whereIn('user_id', $ids);
                    });
            });

        $this->applyAccommodationScope($query, $accommodationIds);

        return $query
            ->selectRaw('COALESCE(created_by, user_id) as reserver_id')
            ->selectRaw('COUNT(*) as bookings_count')
            ->selectRaw('COALESCE(SUM(total_price), 0) as revenue')
            ->groupBy('reserver_id')
            ->get()
            ->keyBy(fn ($row) => (int) $row->reserver_id);
    }

    /** @param  array<int>|null  $accommodationIds */
    private function applyAccommodationScope($query, ?array $accommodationIds): void
    {
        if ($accommodationIds === null || $accommodationIds === []) {
            return;
        }

        $query->whereIn('accommodation_id', array_values(array_map('intval', $accommodationIds)));
    }

    private function growthPercent(float $current, float $previous): ?float
    {
        if ($previous <= 0) {
            return $current > 0 ? null : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
