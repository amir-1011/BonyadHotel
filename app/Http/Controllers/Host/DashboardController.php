<?php

namespace App\Http\Controllers\Host;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Review;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $accommodationIds = $user->accommodations()->pluck('id');

        $stats = [
            'accommodations'  => $accommodationIds->count(),
            'active_acc'      => $user->accommodations()->where('is_active', true)->count(),
            'total_bookings'  => Booking::whereIn('accommodation_id', $accommodationIds)->count(),
            'confirmed'       => Booking::whereIn('accommodation_id', $accommodationIds)->where('status','confirmed')->count(),
            'pending'         => Booking::whereIn('accommodation_id', $accommodationIds)->where('status','pending')->count(),
            'revenue'         => Booking::whereIn('accommodation_id', $accommodationIds)->where('status','confirmed')->sum('total_price'),
            'pending_reviews' => Review::whereIn('accommodation_id', $accommodationIds)->whereNull('host_reply')->count(),
        ];

        $recentBookings = Booking::whereIn('accommodation_id', $accommodationIds)
            ->with('user', 'accommodation')
            ->latest()->limit(8)->get();

        $myAccommodations = $user->accommodations()
            ->withCount(['bookings' => fn($q) => $q->where('status','confirmed')])
            ->with('city')
            ->get();

        return view('host.dashboard', compact('stats', 'recentBookings', 'myAccommodations'));
    }
}
