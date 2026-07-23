<?php

namespace App\Services;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminDashboardDataService
{
    /**
     * @param  array<int>|null  $accommodationIds  Null means all accommodations.
     * @return array<string, mixed>
     */
    public function build(?array $accommodationIds = null): array
    {
        $scoped = $this->normalizeScope($accommodationIds);
        $hasScope = $scoped !== null;

        $bookingQuery = Booking::query();
        if ($hasScope) {
            $bookingQuery->whereIn('accommodation_id', $scoped);
        }

        $accommodationQuery = Accommodation::query();
        if ($hasScope) {
            $accommodationQuery->whereIn('id', $scoped);
        }

        // Single aggregate query instead of 2 separate COUNT queries for accommodations.
        $accAggregate = (clone $accommodationQuery)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active')
            ->first();

        // Single aggregate query instead of 4 separate COUNT/SUM queries for bookings.
        $bookingAggregate = (clone $bookingQuery)
            ->selectRaw(
                "COUNT(*) as total, ".
                "SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed, ".
                "SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending, ".
                "SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled, ".
                "SUM(CASE WHEN status = 'confirmed' THEN total_price ELSE 0 END) as revenue"
            )
            ->first();

        $stats = [
            'users'             => User::count(),
            'hosts'             => User::role('host')->count(),
            'accommodations'    => (int) $accAggregate->total,
            'active_acc'        => (int) $accAggregate->active,
            'bookings'          => (int) $bookingAggregate->total,
            'confirmed'         => (int) $bookingAggregate->confirmed,
            'pending'           => (int) $bookingAggregate->pending,
            'cancelled'         => (int) $bookingAggregate->cancelled,
            'revenue'           => (float) $bookingAggregate->revenue,
            'commission_wallet' => app(PlatformCommissionService::class)->walletBalance(),
            'reviews'           => $hasScope
                ? Review::whereIn('accommodation_id', $scoped)->count()
                : Review::count(),
        ];

        $recentBookings = (clone $bookingQuery)
            ->with('user', 'accommodation.city')
            ->latest()
            ->limit(8)
            ->get();

        $recentUsers = User::with('roles')->latest()->limit(6)->get();

        $topAccommodations = (clone $accommodationQuery)
            ->withCount(['bookings' => fn ($q) => $q->where('status', 'confirmed')])
            ->with('city')
            ->orderByDesc('bookings_count')
            ->limit(5)
            ->get();

        $driver = DB::getDriverName();
        $monthExpr = match ($driver) {
            'sqlite' => "strftime('%Y-%m', created_at)",
            'pgsql'  => "to_char(created_at, 'YYYY-MM')",
            default  => "DATE_FORMAT(created_at, '%Y-%m')",
        };

        $monthlyRevenue = (clone $bookingQuery)
            ->where('status', 'confirmed')
            ->where('created_at', '>=', now()->subMonths(6))
            ->selectRaw("{$monthExpr} as month, SUM(total_price) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $accommodationsSales = (clone $accommodationQuery)
            ->with('city.province')
            ->withCount([
                'bookings as total_bookings_count',
                'bookings as confirmed_count' => fn ($q) => $q->where('status', 'confirmed'),
                'bookings as pending_count'   => fn ($q) => $q->where('status', 'pending'),
                'bookings as cancelled_count' => fn ($q) => $q->where('status', 'cancelled'),
            ])
            ->withSum(['bookings as total_revenue' => fn ($q) => $q->where('status', 'confirmed')], 'total_price')
            ->orderByDesc('total_revenue')
            ->get();

        $dayExpr = match ($driver) {
            'sqlite' => "strftime('%Y-%m-%d', created_at)",
            'pgsql'  => "to_char(created_at, 'YYYY-MM-DD')",
            default  => "DATE(created_at)",
        };

        $bulkDaily = (clone $bookingQuery)
            ->where('status', 'confirmed')
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw("accommodation_id, {$dayExpr} as day, SUM(total_price) as total")
            ->groupBy('accommodation_id', 'day')
            ->get()
            ->groupBy('accommodation_id');

        $sparklineDays = [];
        for ($i = 6; $i >= 0; $i--) {
            $sparklineDays[] = now()->subDays($i)->format('Y-m-d');
        }

        $sparklineData = [];
        foreach ($accommodationsSales as $acc) {
            $rows = $bulkDaily->get($acc->id) ?? collect();
            $sparklineData[$acc->id] = array_map(
                fn ($d) => (float) ($rows->firstWhere('day', $d)?->total ?? 0),
                $sparklineDays,
            );
        }

        $confirmedBookingQuery = (clone $bookingQuery)->where('status', 'confirmed');

        $accTodayRevenue = (clone $confirmedBookingQuery)
            ->whereDate('created_at', today())
            ->selectRaw('accommodation_id, SUM(total_price) as total')
            ->groupBy('accommodation_id')
            ->pluck('total', 'accommodation_id');

        $accWeekRevenue = (clone $confirmedBookingQuery)
            ->where('created_at', '>=', now()->startOfWeek())
            ->selectRaw('accommodation_id, SUM(total_price) as total')
            ->groupBy('accommodation_id')
            ->pluck('total', 'accommodation_id');

        $accMonthRevenue = (clone $confirmedBookingQuery)
            ->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('accommodation_id, SUM(total_price) as total')
            ->groupBy('accommodation_id')
            ->pluck('total', 'accommodation_id');

        $accLastMonthRevenue = (clone $confirmedBookingQuery)
            ->where('created_at', '>=', now()->subMonth()->startOfMonth())
            ->where('created_at', '<', now()->startOfMonth())
            ->selectRaw('accommodation_id, SUM(total_price) as total')
            ->groupBy('accommodation_id')
            ->pluck('total', 'accommodation_id');

        $geoProvince = $this->geoProvinceStats($scoped);
        $topCities = $this->topCityStats($scoped);

        return compact(
            'stats',
            'recentBookings',
            'recentUsers',
            'topAccommodations',
            'monthlyRevenue',
            'accommodationsSales',
            'sparklineData',
            'sparklineDays',
            'accTodayRevenue',
            'accWeekRevenue',
            'accMonthRevenue',
            'accLastMonthRevenue',
            'geoProvince',
            'topCities',
        );
    }

    /** @param  array<int>|null  $accommodationIds */
    private function geoProvinceStats(?array $accommodationIds): Collection
    {
        $query = Booking::query()
            ->where('bookings.status', 'confirmed')
            ->join('accommodations', 'bookings.accommodation_id', '=', 'accommodations.id')
            ->join('cities', 'accommodations.city_id', '=', 'cities.id')
            ->join('provinces', 'cities.province_id', '=', 'provinces.id');

        if ($accommodationIds !== null) {
            $query->whereIn('bookings.accommodation_id', $accommodationIds);
        }

        return $query
            ->selectRaw('provinces.name as province, COUNT(*) as bookings, SUM(bookings.total_price) as revenue')
            ->groupBy('provinces.name')
            ->orderByDesc('bookings')
            ->get();
    }

    /** @param  array<int>|null  $accommodationIds */
    private function topCityStats(?array $accommodationIds): Collection
    {
        $query = Booking::query()
            ->where('bookings.status', 'confirmed')
            ->join('accommodations', 'bookings.accommodation_id', '=', 'accommodations.id')
            ->join('cities', 'accommodations.city_id', '=', 'cities.id')
            ->join('provinces', 'cities.province_id', '=', 'provinces.id');

        if ($accommodationIds !== null) {
            $query->whereIn('bookings.accommodation_id', $accommodationIds);
        }

        return $query
            ->selectRaw('cities.name as city, provinces.name as province, COUNT(*) as bookings, SUM(bookings.total_price) as revenue')
            ->groupBy('cities.name', 'provinces.name')
            ->orderByDesc('bookings')
            ->limit(8)
            ->get();
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
