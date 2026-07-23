<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingService;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;

class HostDashboardDataService
{
    /**
     * @param  array<int>|null  $scopedAccommodationIds  Null uses all managed accommodations.
     * @return array<string, mixed>
     */
    public function build(User $user, ?array $scopedAccommodationIds = null): array
    {
        $managedIds = $user->managedAccommodationIds()->map(fn ($id) => (int) $id);

        if ($scopedAccommodationIds !== null) {
            $allowed = array_flip($managedIds->all());
            $ids = array_values(array_filter(
                array_map('intval', $scopedAccommodationIds),
                fn (int $id) => isset($allowed[$id]),
            ));
        } else {
            $ids = $managedIds->all();
        }

        $accommodationIds = collect($ids);

        // Single aggregate query instead of 4 separate COUNT/SUM queries for the base booking stats.
        $bookingAggregate = Booking::whereIn('accommodation_id', $ids)
            ->selectRaw(
                "COUNT(*) as total, ".
                "SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed, ".
                "SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending, ".
                "SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled, ".
                "SUM(CASE WHEN status = 'confirmed' THEN total_price ELSE 0 END) as revenue, ".
                "SUM(CASE WHEN status = 'confirmed' AND created_at >= ? THEN total_price ELSE 0 END) as this_month, ".
                "SUM(CASE WHEN status = 'confirmed' AND created_at >= ? AND created_at < ? THEN total_price ELSE 0 END) as last_month, ".
                "SUM(CASE WHEN status = 'confirmed' AND created_at >= ? THEN total_price ELSE 0 END) as today_revenue",
                [
                    now()->startOfMonth(),
                    now()->subMonth()->startOfMonth(), now()->startOfMonth(),
                    today(),
                ]
            )
            ->first();

        $thisMonth = (float) $bookingAggregate->this_month;
        $lastMonth = (float) $bookingAggregate->last_month;

        $stats = [
            'accommodations'  => $accommodationIds->count(),
            'active_acc'      => $user->accommodations()->whereIn('accommodations.id', $ids)->where('is_active', true)->count(),
            'total_bookings'  => (int) $bookingAggregate->total,
            'confirmed'       => (int) $bookingAggregate->confirmed,
            'pending'         => (int) $bookingAggregate->pending,
            'cancelled'       => (int) $bookingAggregate->cancelled,
            'revenue'         => (float) $bookingAggregate->revenue,
            'services_revenue'=> BookingService::whereHas('booking', fn ($q) => $q->whereIn('accommodation_id', $ids)->where('status', 'confirmed'))->sum('total'),
            'pending_reviews' => Review::whereIn('accommodation_id', $ids)->whereNull('host_reply')->count(),
        ];

        $stats['growth_rate'] = $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1) : null;
        $stats['this_month']  = $thisMonth;
        $stats['today_revenue'] = (float) $bookingAggregate->today_revenue;

        $recentBookings = Booking::whereIn('accommodation_id', $ids)
            ->with('user', 'accommodation', 'roomType', 'bookingRooms.room')
            ->latest()->limit(12)->get();

        $myAccommodations = $user->accommodations()
            ->whereIn('accommodations.id', $ids)
            ->withCount([
                'bookings as total_bookings_count',
                'bookings as confirmed_count' => fn ($q) => $q->where('status', 'confirmed'),
                'bookings as pending_count'   => fn ($q) => $q->where('status', 'pending'),
                'bookings as cancelled_count' => fn ($q) => $q->where('status', 'cancelled'),
            ])
            ->withSum(['bookings as total_revenue' => fn ($q) => $q->where('status', 'confirmed')], 'total_price')
            ->with('city')
            ->get();

        $driver    = DB::getDriverName();
        $dayExpr   = match ($driver) {
            'sqlite' => "strftime('%Y-%m-%d', created_at)",
            'pgsql'  => "to_char(created_at, 'YYYY-MM-DD')",
            default  => "DATE(created_at)",
        };
        $monthExpr = match ($driver) {
            'sqlite' => "strftime('%Y-%m', created_at)",
            'pgsql'  => "to_char(created_at, 'YYYY-MM')",
            default  => "DATE_FORMAT(created_at, '%Y-%m')",
        };

        $rawDaily = Booking::whereIn('accommodation_id', $ids)
            ->where('status', 'confirmed')
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw("{$dayExpr} as day, SUM(total_price) as total, COUNT(*) as count")
            ->groupBy('day')->orderBy('day')->get()->keyBy('day');

        $dailyRevenue = [];
        for ($i = 29; $i >= 0; $i--) {
            $carbon = now()->subDays($i);
            $d = $carbon->format('Y-m-d');
            $dailyRevenue[] = [
                'day'   => Jalalian::fromCarbon($carbon)->format('Y/m/d'),
                'total' => (float) ($rawDaily[$d]->total ?? 0),
                'count' => (int)   ($rawDaily[$d]->count ?? 0),
            ];
        }

        $statusBreakdown = Booking::whereIn('accommodation_id', $ids)
            ->selectRaw('status, COUNT(*) as count, SUM(total_price) as total')
            ->groupBy('status')->get();

        $bulkDaily = Booking::whereIn('accommodation_id', $ids)
            ->where('status', 'confirmed')
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw("accommodation_id, {$dayExpr} as day, SUM(total_price) as total")
            ->groupBy('accommodation_id', 'day')->get()->groupBy('accommodation_id');

        $sparklineDays = [];
        for ($i = 6; $i >= 0; $i--) {
            $sparklineDays[] = now()->subDays($i)->format('Y-m-d');
        }
        $sparklineData = [];
        foreach ($myAccommodations as $acc) {
            $rows = $bulkDaily->get($acc->id) ?? collect();
            $sparklineData[$acc->id] = array_map(
                fn ($d) => (float) ($rows->firstWhere('day', $d)?->total ?? 0),
                $sparklineDays
            );
        }

        $accTodayRevenue = Booking::whereIn('accommodation_id', $ids)->where('status', 'confirmed')
            ->whereDate('created_at', today())
            ->selectRaw('accommodation_id, SUM(total_price) as total')->groupBy('accommodation_id')
            ->pluck('total', 'accommodation_id');
        $accWeekRevenue = Booking::whereIn('accommodation_id', $ids)->where('status', 'confirmed')
            ->where('created_at', '>=', now()->startOfWeek())
            ->selectRaw('accommodation_id, SUM(total_price) as total')->groupBy('accommodation_id')
            ->pluck('total', 'accommodation_id');
        $accMonthRevenue = Booking::whereIn('accommodation_id', $ids)->where('status', 'confirmed')
            ->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('accommodation_id, SUM(total_price) as total')->groupBy('accommodation_id')
            ->pluck('total', 'accommodation_id');

        $checkoutsToday = Booking::whereIn('accommodation_id', $ids)
            ->where('status', 'confirmed')
            ->whereDate('check_out', today())
            ->with('user', 'accommodation', 'roomType', 'bookingRooms.room')
            ->orderBy('check_out')
            ->get();

        $checkoutsSoon = Booking::whereIn('accommodation_id', $ids)
            ->where('status', 'confirmed')
            ->whereDate('check_out', '>', today())
            ->whereDate('check_out', '<=', today()->addDays(2))
            ->with('user', 'accommodation', 'roomType', 'bookingRooms.room')
            ->orderBy('check_out')
            ->get();

        $checkinsToday = Booking::whereIn('accommodation_id', $ids)
            ->whereIn('status', ['confirmed', 'pending'])
            ->whereDate('check_in', today())
            ->with('user', 'accommodation', 'roomType')
            ->orderBy('check_in')
            ->get();

        $soldServices = BookingService::query()
            ->whereHas('booking', fn ($q) => $q->whereIn('accommodation_id', $ids)->where('status', 'confirmed'))
            ->with(['booking.accommodation', 'booking.user', 'serviceCatalog'])
            ->latest('booking_services.id')
            ->limit(40)
            ->get();

        $serviceSummary = BookingService::query()
            ->whereHas('booking', fn ($q) => $q->whereIn('accommodation_id', $ids)->where('status', 'confirmed'))
            ->selectRaw('name, SUM(quantity) as total_qty, SUM(total) as total_revenue, COUNT(*) as line_count, SUM(discount_amount) as total_discount')
            ->groupBy('name')
            ->orderByDesc('total_revenue')
            ->get();

        $activeStays = Booking::whereIn('accommodation_id', $ids)
            ->where('status', 'confirmed')
            ->whereDate('check_in', '<=', today())
            ->whereDate('check_out', '>', today())
            ->with('user', 'accommodation', 'roomType', 'bookingRooms.room')
            ->orderBy('check_out')
            ->get();

        return compact(
            'stats', 'recentBookings', 'myAccommodations', 'dailyRevenue', 'statusBreakdown',
            'sparklineData', 'sparklineDays', 'accTodayRevenue', 'accWeekRevenue', 'accMonthRevenue',
            'checkoutsToday', 'checkoutsSoon', 'checkinsToday',
            'soldServices', 'serviceSummary', 'activeStays'
        );
    }
}
