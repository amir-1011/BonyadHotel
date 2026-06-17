<?php

namespace App\Livewire\Admin;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'داشبورد مدیریت', 'pageTitle' => 'داشبورد مدیریت'])]
class Dashboard extends Component
{
    public function render()
    {
        $stats = [
            'users'          => User::count(),
            'hosts'          => User::role('host')->count(),
            'accommodations' => Accommodation::count(),
            'active_acc'     => Accommodation::where('is_active', true)->count(),
            'bookings'       => Booking::count(),
            'confirmed'      => Booking::where('status', 'confirmed')->count(),
            'pending'        => Booking::where('status', 'pending')->count(),
            'cancelled'      => Booking::where('status', 'cancelled')->count(),
            'revenue'        => Booking::where('status', 'confirmed')->sum('total_price'),
            'reviews'        => Review::count(),
        ];

        $recentBookings    = Booking::with('user', 'accommodation.city')->latest()->limit(8)->get();
        $recentUsers       = User::latest()->limit(6)->get();
        $topAccommodations = Accommodation::withCount(['bookings' => fn($q) => $q->where('status', 'confirmed')])
            ->with('city')->orderByDesc('bookings_count')->limit(5)->get();

        $driver         = DB::getDriverName();
        $monthExpr      = match ($driver) {
            'sqlite' => "strftime('%Y-%m', created_at)",
            'pgsql'  => "to_char(created_at, 'YYYY-MM')",
            default  => "DATE_FORMAT(created_at, '%Y-%m')",
        };
        $monthlyRevenue = Booking::where('status', 'confirmed')
            ->where('created_at', '>=', now()->subMonths(6))
            ->selectRaw("{$monthExpr} as month, SUM(total_price) as total")
            ->groupBy('month')->orderBy('month')->get();

        $accommodationsSales = Accommodation::with('city')
            ->withCount([
                'bookings as total_bookings_count',
                'bookings as confirmed_count' => fn($q) => $q->where('status', 'confirmed'),
                'bookings as pending_count'   => fn($q) => $q->where('status', 'pending'),
                'bookings as cancelled_count' => fn($q) => $q->where('status', 'cancelled'),
            ])
            ->withSum(['bookings as total_revenue' => fn($q) => $q->where('status', 'confirmed')], 'total_price')
            ->orderByDesc('total_revenue')->get();

        $dayExpr   = match ($driver) {
            'sqlite' => "strftime('%Y-%m-%d', created_at)",
            'pgsql'  => "to_char(created_at, 'YYYY-MM-DD')",
            default  => "DATE(created_at)",
        };
        $bulkDaily = Booking::where('status', 'confirmed')
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw("accommodation_id, {$dayExpr} as day, SUM(total_price) as total")
            ->groupBy('accommodation_id', 'day')->get()->groupBy('accommodation_id');

        $sparklineDays = [];
        for ($i = 6; $i >= 0; $i--) {
            $sparklineDays[] = now()->subDays($i)->format('Y-m-d');
        }
        $sparklineData = [];
        foreach ($accommodationsSales as $acc) {
            $rows = $bulkDaily->get($acc->id) ?? collect();
            $sparklineData[$acc->id] = array_map(
                fn($d) => (float) ($rows->firstWhere('day', $d)?->total ?? 0),
                $sparklineDays
            );
        }

        $accTodayRevenue     = Booking::where('status', 'confirmed')->whereDate('created_at', today())
            ->selectRaw('accommodation_id, SUM(total_price) as total')->groupBy('accommodation_id')
            ->pluck('total', 'accommodation_id');
        $accWeekRevenue      = Booking::where('status', 'confirmed')->where('created_at', '>=', now()->startOfWeek())
            ->selectRaw('accommodation_id, SUM(total_price) as total')->groupBy('accommodation_id')
            ->pluck('total', 'accommodation_id');
        $accMonthRevenue     = Booking::where('status', 'confirmed')->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('accommodation_id, SUM(total_price) as total')->groupBy('accommodation_id')
            ->pluck('total', 'accommodation_id');
        $accLastMonthRevenue = Booking::where('status', 'confirmed')
            ->where('created_at', '>=', now()->subMonth()->startOfMonth())
            ->where('created_at', '<', now()->startOfMonth())
            ->selectRaw('accommodation_id, SUM(total_price) as total')->groupBy('accommodation_id')
            ->pluck('total', 'accommodation_id');

        // ─ Geographic distribution of confirmed bookings (province + city)
        $geoProvince = Booking::query()
            ->where('bookings.status', 'confirmed')
            ->join('accommodations', 'bookings.accommodation_id', '=', 'accommodations.id')
            ->join('cities', 'accommodations.city_id', '=', 'cities.id')
            ->join('provinces', 'cities.province_id', '=', 'provinces.id')
            ->selectRaw('provinces.name as province, COUNT(*) as bookings, SUM(bookings.total_price) as revenue')
            ->groupBy('provinces.name')
            ->orderByDesc('bookings')
            ->get();

        $topCities = Booking::query()
            ->where('bookings.status', 'confirmed')
            ->join('accommodations', 'bookings.accommodation_id', '=', 'accommodations.id')
            ->join('cities', 'accommodations.city_id', '=', 'cities.id')
            ->join('provinces', 'cities.province_id', '=', 'provinces.id')
            ->selectRaw('cities.name as city, provinces.name as province, COUNT(*) as bookings, SUM(bookings.total_price) as revenue')
            ->groupBy('cities.name', 'provinces.name')
            ->orderByDesc('bookings')
            ->limit(8)
            ->get();

        return view('admin.dashboard', compact(
            'stats', 'recentBookings', 'recentUsers', 'topAccommodations', 'monthlyRevenue',
            'accommodationsSales', 'sparklineData', 'sparklineDays',
            'accTodayRevenue', 'accWeekRevenue', 'accMonthRevenue', 'accLastMonthRevenue',
            'geoProvince', 'topCities'
        ));
    }

    public function updateBookingStatus(int $bookingId, string $status): void
    {
        $allowed = ['pending', 'confirmed', 'cancelled'];
        if (!in_array($status, $allowed, true)) return;

        Booking::findOrFail($bookingId)->update(['status' => $status]);
        $this->dispatch('toast', type: 'success', message: 'وضعیت رزرو به‌روز شد.');
    }
}
