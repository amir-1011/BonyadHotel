<?php

namespace App\Services;

use App\Models\Accommodation;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;

class AccommodationSalesReportService
{
    public function build(Accommodation $accommodation): array
    {
        $accommodation->load('city.province', 'host', 'roomTypes');

        $driver  = DB::getDriverName();
        $dayExpr = match ($driver) {
            'sqlite' => "strftime('%Y-%m-%d', created_at)",
            'pgsql'  => "to_char(created_at, 'YYYY-MM-DD')",
            default  => "DATE(created_at)",
        };
        $monthExpr = match ($driver) {
            'sqlite' => "strftime('%Y-%m', created_at)",
            'pgsql'  => "to_char(created_at, 'YYYY-MM')",
            default  => "DATE_FORMAT(created_at, '%Y-%m')",
        };

        $rawDaily = Booking::where('accommodation_id', $accommodation->id)
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

        $rawMonthly = Booking::where('accommodation_id', $accommodation->id)
            ->where('status', 'confirmed')
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw("{$monthExpr} as month, SUM(total_price) as total, COUNT(*) as count")
            ->groupBy('month')->orderBy('month')->get()->keyBy('month');

        $monthlyRevenue = [];
        for ($i = 11; $i >= 0; $i--) {
            $carbon = now()->subMonths($i);
            $m = $carbon->format('Y-m');
            $monthlyRevenue[] = [
                'month' => Jalalian::fromCarbon($carbon)->format('Y/m'),
                'total' => (float) ($rawMonthly[$m]->total ?? 0),
                'count' => (int)   ($rawMonthly[$m]->count ?? 0),
            ];
        }

        $statusBreakdown = Booking::where('accommodation_id', $accommodation->id)
            ->selectRaw('status, COUNT(*) as count, SUM(total_price) as total')
            ->groupBy('status')->get();

        $today     = Booking::where('accommodation_id', $accommodation->id)->where('status', 'confirmed')->whereDate('created_at', today())->sum('total_price');
        $thisWeek  = Booking::where('accommodation_id', $accommodation->id)->where('status', 'confirmed')->where('created_at', '>=', now()->startOfWeek())->sum('total_price');
        $thisMonth = Booking::where('accommodation_id', $accommodation->id)->where('status', 'confirmed')->where('created_at', '>=', now()->startOfMonth())->sum('total_price');
        $lastMonth = Booking::where('accommodation_id', $accommodation->id)->where('status', 'confirmed')
            ->where('created_at', '>=', now()->subMonth()->startOfMonth())
            ->where('created_at', '<',  now()->startOfMonth())
            ->sum('total_price');
        $totalRevenue     = Booking::where('accommodation_id', $accommodation->id)->where('status', 'confirmed')->sum('total_price');
        $totalBookings    = Booking::where('accommodation_id', $accommodation->id)->count();
        $totalConfirmed   = Booking::where('accommodation_id', $accommodation->id)->where('status', 'confirmed')->count();
        $totalPending     = Booking::where('accommodation_id', $accommodation->id)->where('status', 'pending')->count();
        $totalCancelled   = Booking::where('accommodation_id', $accommodation->id)->where('status', 'cancelled')->count();
        $growthRate       = $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1) : null;
        $avgRevPerBooking = $totalConfirmed > 0 ? round($totalRevenue / $totalConfirmed) : 0;

        $roomTypeBreakdown = Booking::where('bookings.accommodation_id', $accommodation->id)
            ->where('bookings.status', 'confirmed')
            ->join('room_types', 'bookings.room_type_id', '=', 'room_types.id')
            ->selectRaw('room_types.name as rt_name, COUNT(bookings.id) as count, SUM(bookings.total_price) as total')
            ->groupBy('room_types.name')->orderByDesc('total')->get();

        $recentBookings = Booking::where('accommodation_id', $accommodation->id)
            ->with('user', 'roomType')
            ->latest()->limit(20)->get();

        $avgRating   = $accommodation->averageRating();
        $reviewCount = $accommodation->reviewCount();

        return compact(
            'accommodation', 'dailyRevenue', 'monthlyRevenue', 'statusBreakdown',
            'today', 'thisWeek', 'thisMonth', 'lastMonth', 'growthRate',
            'totalRevenue', 'totalBookings', 'totalConfirmed', 'totalPending', 'totalCancelled',
            'avgRevPerBooking', 'roomTypeBreakdown', 'recentBookings', 'avgRating', 'reviewCount'
        );
    }
}
