<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
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

        $recentBookings = Booking::with('user', 'accommodation.city')
            ->latest()->limit(8)->get();

        $recentUsers = User::latest()->limit(6)->get();

        $topAccommodations = Accommodation::withCount(['bookings' => fn($q) => $q->where('status','confirmed')])
            ->with('city')
            ->orderByDesc('bookings_count')
            ->limit(5)->get();

        // Monthly revenue (last 6 months)
        $monthlyRevenue = Booking::where('status', 'confirmed')
            ->where('created_at', '>=', now()->subMonths(6))
            ->select(DB::raw("strftime('%Y-%m', created_at) as month"), DB::raw('sum(total_price) as total'))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return view('admin.dashboard', compact(
            'stats', 'recentBookings', 'recentUsers', 'topAccommodations', 'monthlyRevenue'
        ));
    }
}
